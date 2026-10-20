# Instalare pe un calculator nou

Aceste instrucțiuni sunt pentru dezvoltare locală. Folderul `.tools` de pe calculatorul inițial nu este inclus în Git. Scriptul `start-local.ps1` gestionează instalarea portabilă de pe acel calculator; pentru un clone nou poți folosi servicii PostgreSQL și ClamAV instalate normal.

## 1. Surse și dependențe

```sh
git clone --branch codex/documente-design https://github.com/razvanstav/Family.git
cd Family
```

Instalează PHP 8.4, Composer 2, Node.js 22 LTS, PostgreSQL 18 și ClamAV. Activează în PHP extensiile `pdo_pgsql`, `fileinfo`, `mbstring`, `openssl`, `gd`, `dom`, `xml` și `xmlwriter`; GD trebuie să suporte JPEG, PNG și WebP. Adaugă `php`, `composer`, `node`, `npm` și `psql` în PATH. Versiunile exacte ale pachetelor aplicației sunt fixate în cele două lockfile-uri.

```sh
composer install --working-dir=backend
npm --prefix frontend ci
npm --prefix frontend run build
```

În Windows PowerShell, folosește `npm.cmd` dacă politica de execuție blochează `npm.ps1`.

## 2. Baze și roluri PostgreSQL

Pe un server local nou, folosind contul PostgreSQL de administrare:

```sh
psql -h 127.0.0.1 -p 5432 -U postgres -d postgres -f ops/create-databases.sql
```

Scriptul cere interactiv parole noi pentru `memoria_admin` și `memoria_app`, apoi creează `memoria` și `memoria_test`. Nu resetează baze existente. `memoria_admin` este proprietarul tabelelor și are BYPASSRLS pentru fixture-uri și verificări administrative în teste. Aplicația trebuie să ruleze întotdeauna ca `memoria_app`, fără BYPASSRLS și fără proprietatea tabelelor. Dacă instalarea folosește alt cont administrator sau alt port, adaptează comanda.

## 3. Configurație locală

Copiază `backend/.env.example` în `backend/.env`. Păstrează setările de sesiune și completează:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=memoria
DB_USERNAME=memoria_app
DB_PASSWORD="parola nouă a rolului runtime"
MEMORIA_DEMO=true
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310
```

Valorile dintre ghilimele de mai sus sunt substituenți, nu parole funcționale. Generează cheia aplicației:

```sh
php backend/artisan key:generate
```

Pentru migrații și date demo, suprascrie temporar utilizatorul DB doar în terminal. În PowerShell:

```powershell
$env:DB_USERNAME = 'memoria_admin'
$secret = Read-Host 'Parola memoria_admin' -AsSecureString
$env:DB_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
try {
    $env:DB_DATABASE = 'memoria'
    php backend/artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Migrarea a eșuat' }
    php backend/artisan db:seed --class=DemoSeeder --force
    if ($LASTEXITCODE -ne 0) { throw 'Datele demo nu au fost create' }
    $env:DB_DATABASE = 'memoria_test'
    php backend/artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Migrarea bazei de test a eșuat' }
} finally {
    Remove-Item Env:DB_USERNAME, Env:DB_PASSWORD, Env:DB_DATABASE -ErrorAction SilentlyContinue
    Remove-Variable secret -ErrorAction SilentlyContinue
}
```

În Bash, echivalentul (parola nu intră în istoricul comenzilor):

```bash
(
  set -e
  export DB_USERNAME=memoria_admin
  read -r -s -p 'Parola memoria_admin: ' DB_PASSWORD; printf '\n'
  export DB_PASSWORD
  export DB_DATABASE=memoria
  php backend/artisan migrate --force
  php backend/artisan db:seed --class=DemoSeeder --force
  export DB_DATABASE=memoria_test
  php backend/artisan migrate --force
)
```

Aplică permisiunile runtime după migrații, în ambele baze. `psql` solicită parola administratorului:

```sh
psql -h 127.0.0.1 -p 5432 -U memoria_admin -d memoria -f ops/grant-runtime.sql
psql -h 127.0.0.1 -p 5432 -U memoria_admin -d memoria_test -f ops/grant-runtime.sql
```

## 4. ClamAV și pornire

Actualizează semnăturile cu `freshclam`, apoi pornește serviciul `clamd` al instalării tale. În configurația lui, păstrează directorul de semnături corect pentru sistem și setează:

```text
TCPAddr 127.0.0.1
TCPSocket 3310
StreamMaxLength 12M
MaxFileSize 12M
MaxScanSize 40M
AlertExceedsMax yes
AlertEncrypted yes
```

Ascultarea TCP rămâne exclusiv locală. Fără un scanner funcțional, uploadurile rămân în carantină; autentificarea și explorarea documentelor demo funcționează.

Din rădăcina proiectului, cu extensiile PHP deja activate:

```sh
php -d upload_max_filesize=10M -d post_max_size=12M -S 127.0.0.1:8000 -t backend/public ops/router.php
```

Deschide [aplicația](http://127.0.0.1:8000/app/) sau [previzualizarea mobilă](http://127.0.0.1:8000/preview/mobile). Apasă Ana, apoi „Intră în cont”. Serverul din terminal se oprește cu Ctrl+C. PostgreSQL și ClamAV se opresc separat, prin mecanismul instalării lor.

## 5. Verificare și continuare

Copiază `.env` în `.env.testing`, apoi setează `APP_ENV=testing`, `DB_DATABASE=memoria_test` și `DB_ADMIN_PASSWORD` la parola administratorului local. Păstrează `DB_USERNAME=memoria_app` și parola runtime în ambele fișiere. Nu porni testele asupra bazei `memoria`: testele refac datele din baza dedicată `memoria_test`.

```sh
cd backend
php vendor/bin/phpunit --testdox
```

Setează variabila de mediu `CLAMAV_INTEGRATION=1` pentru cele două teste cu antivirus real. În Windows, `ops/test-local.ps1` le activează automat și construiește frontendul. Rulează din rădăcina proiectului; acceptă `-PhpPath` dacă PHP nu este în PATH.

La fiecare modificare UI, rulează `npm --prefix frontend run build` și reîncarcă browserul. Pentru backend, adaugă migrații și aplică din nou permisiunile runtime când apar tabele sau secvențe noi.

Ultima verificare pe calculatorul inițial: **45 teste / 274 aserțiuni**, inclusiv invitații, acceptare, anulare, schimbarea rolurilor, revocare și ClamAV real, și build TypeScript/Vite reușit. Designul a fost inspectat la 360 px, 390 px și desktop. Instalarea de la zero documentată aici nu a fost executată pe un al doilea calculator.

Punctul de continuare: finisarea celorlalte pagini. Documente, detaliu, upload și gestionarea accesului sunt funcționale; arhitectura extinsă din `Memoria_Familiei_Review_v1.2` descrie și module încă neimplementate. Fișierele și datele private existente pe calculatorul inițial nu sunt incluse în clone; seederul recreează doar exemplele fictive.
