# Instalare pe un calculator nou

Aceste instrucțiuni sunt pentru dezvoltare locală. Folderul `.tools` de pe calculatorul inițial nu este inclus în Git. Scriptul `start-local.ps1` gestionează instalarea portabilă de pe acel calculator; pentru un clone nou poți folosi servicii PostgreSQL și ClamAV instalate normal.

## Predare pentru serverul Linux

Decizie la 22 octombrie 2026: instalare pe serverul Linux dedicat al utilizatorului, de către o altă persoană. **O2a: configurațiile de predare sunt pregătite în [ops/linux](linux/); instalarea și funcționarea pe server nu sunt verificate.** Instrucțiunile locale de mai jos rămân separate de staging. Acceptarea mediului de test este în [O2 — staging](../docs/modules/operations.md).

- Confirmă distribuția/versiunea Linux, RAM-ul disponibil și site-urile existente. Pentru aplicație, PostgreSQL și ClamAV împreună se recomandă minimum 8 GB RAM pentru testare; consumul trebuie verificat pe server.
- Dependențele și extensiile sunt în secțiunea 1. Păstrează PostgreSQL și politicile RLS; rulează migrațiile cu rolul de administrare, aplică `ops/grant-runtime.sql`, apoi rulează aplicația și workerii cu `memoria_app`, fără BYPASSRLS sau proprietatea tabelelor.
- Construiește frontendul: rezultatul este `backend/public/app`, cu URL `/app/`. Configurează Nginx/PHP-FPM cu rădăcina web `backend/public` și API-ul pe aceeași origine. Nu publica rădăcina repository-ului.
- Creează configurație, chei, bază și stocare private separate: `APP_ENV=staging`, `APP_DEBUG=false`, `MEMORIA_DEMO=false`, URL HTTPS și cookie de sesiune sigur. Nu rula seederele demo din instrucțiunile locale și nu copia `.env` sau datele calculatorului inițial. Provisionarea conturilor de test trebuie stabilită separat.
- Păstrează fișierele private persistente, accesibile numai prin autorizarea aplicației. Configurează ClamAV local conform secțiunii 4 și actualizarea supravegheată a definițiilor.
- Configurațiile systemd livrate execută `calendar:deliver-reminders` și `outbox:deliver` fără `--watch`, prin timere separate, la 15 secunde după încheierea trecerii anterioare. Nu porni în paralel și workerii `--watch` ori un cron pentru aceleași comenzi. Scripturile PowerShell locale nu instalează servicii Linux.
- Verifică pe server autentificarea, izolarea familiilor, uploadul/scanarea/downloadul privat, granturile după migrații și execuția workerilor. Configurează și probează backupul DB + fișiere înainte de utilizarea datelor reale. Nu deduce acceptarea Linux din testele locale consemnate.

### O2a — configurații pentru instalator

Acest pas pregătește un singur mediu staging, fără acces la server. Nu instalează pachete, certificate, conturi sau date. Înlocuiește domeniul `family.example.com`, căile certificatelor și utilizatorul/grupul Nginx din exemple după confirmarea serverului. Calea PHP presupusă este `/usr/bin/php` cu versiunea 8.4 și extensiile de la secțiunea 1, atât în CLI, cât și în FPM. Folosește pachetele de securitate curente ale distribuției, fără a copia runtime-ul Windows.

| Fișier                                                                                                     | Destinație/rol                                                                |
| ---------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| [linux/.env.example](linux/.env.example)                                                                   | Model pentru `/srv/memoria/shared/.env`; cheia și parola DB sunt necompletate |
| [linux/nginx.conf](linux/nginx.conf)                                                                       | Virtual host dedicat HTTPS, rădăcină `current/backend/public`                 |
| [linux/php-fpm.conf](linux/php-fpm.conf)                                                                   | Pool PHP-FPM `memoria`, socket `/run/php/memoria.sock`                        |
| [linux/memoria-outbox.service](linux/memoria-outbox.service), [timer](linux/memoria-outbox.timer)          | O trecere outbox per activare                                                 |
| [linux/memoria-reminders.service](linux/memoria-reminders.service), [timer](linux/memoria-reminders.timer) | O trecere a reminderelor in-app per activare                                  |

Structura de instalare:

```text
/srv/memoria/
  releases/<commit>/       surse + vendor + frontend construit
  current -> releases/<commit>
  shared/.env             configurație și cheie persistente
  shared/storage/         bytes privați, sesiuni, cache, loguri
```

Separă utilizatorul de deploy de contul de serviciu `memoria`, fără shell de login. Sursele sunt deținute de utilizatorul de deploy și pot fi citite de `memoria`; Nginx are numai acces de citire/traversare la `backend/public` și la părinții lui. Numai `shared/storage` și `backend/bootstrap/cache` din fiecare release sunt inscriptibile pentru `memoria`. Fișierul `shared/.env` este deținut de deploy, grup `memoria`, mod `0640`. Nu acorda Nginx acces la storage sau la secrete. Creează în storage directoarele `app/private`, `app/public`, `framework/cache/data`, `framework/cache/locks`, `framework/sessions`, `framework/views` și `logs`, deținute de `memoria`.

Pe clusterul PostgreSQL, creează separat baza `memoria_staging`, deținută de `memoria_admin`. Păstrează rolurile din secțiunea 2: `memoria_app` fără SUPERUSER/BYPASSRLS și fără proprietatea tabelelor. Scriptul `create-databases.sql` este pentru dezvoltare și creează și `memoria_test`; nu îl aplica automat pe server. Nu reutiliza parolele locale. Completează modelul `.env` cu parola runtime, domeniul HTTPS și baza staging; nu stoca parola administratorului în acest fișier. Generează `APP_KEY` o singură dată la prima instalare, apoi păstreaz-o la actualizări.

Pregătește fiecare release ca utilizator de deploy, folosind un hash complet ales din Git, nu un checkout modificat în loc:

```bash
set -e
MEMORIA_REVISION=COMMIT_SHA_COMPLET
MEMORIA_RELEASE="/srv/memoria/releases/$MEMORIA_REVISION"
git clone --no-checkout https://github.com/razvanstav/Family.git "$MEMORIA_RELEASE"
git -C "$MEMORIA_RELEASE" checkout --detach "$MEMORIA_REVISION"
ln -s /srv/memoria/shared/.env "$MEMORIA_RELEASE/backend/.env"
# Storage-ul din clone conține numai structura inițială, fără date.
rm -r "$MEMORIA_RELEASE/backend/storage"
ln -s /srv/memoria/shared/storage "$MEMORIA_RELEASE/backend/storage"
composer install --working-dir="$MEMORIA_RELEASE/backend" --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm --prefix "$MEMORIA_RELEASE/frontend" ci
npm --prefix "$MEMORIA_RELEASE/frontend" run build
```

Înainte de Artisan, acordă contului `memoria` citire pentru surse și scriere pentru `bootstrap/cache`, inclusiv fișierele generate de Composer. La prima instalare, completează cheia prin `php artisan key:generate` într-o sesiune a utilizatorului de deploy care poate scrie `shared/.env`; nu regenera cheia la următoarele release-uri.

Migrarea este o operație unică, efectuată de instalator. La upgrade, oprește întâi cele două timere și așteaptă încheierea serviciilor active; oprirea timerului nu întrerupe trecerea deja pornită. Pune aplicația curentă în mentenanță cu `sudo -u memoria /usr/bin/php /srv/memoria/current/backend/artisan down --retry=60` și lasă cererile existente să se încheie înaintea migrației. La prima instalare, menține virtual hostul inaccesibil până la încheierea pașilor. Din shellul în care `MEMORIA_RELEASE` este definit:

```bash
sudo -u memoria bash -c '
  set -e
  export DB_USERNAME=memoria_admin
  read -r -s -p "Parola memoria_admin: " DB_PASSWORD
  printf "\n"
  export DB_PASSWORD
  /usr/bin/php "$1/artisan" migrate --force --no-interaction
' memoria-migrate "$MEMORIA_RELEASE/backend"
psql -h 127.0.0.1 -p 5432 -U memoria_admin -W -d memoria_staging -f "$MEMORIA_RELEASE/ops/grant-runtime.sql"
sudo -u memoria /usr/bin/php "$MEMORIA_RELEASE/backend/artisan" config:cache --no-interaction
sudo -u memoria /usr/bin/php "$MEMORIA_RELEASE/backend/artisan" view:cache --no-interaction
```

Adaptează hostul/portul PostgreSQL dacă diferă. La orice eroare, oprește publicarea și păstrează mentenanța și timerele oprite. Rulează migrațiile înainte de `config:cache`: release-ul nou nu trebuie să conțină un cache de configurație anterior, iar cache-ul final trebuie generat cu rolul runtime din `.env`, după ieșirea din subshellul administrativ. Nu rula seederele demo. Provisionarea conturilor sintetice și a familiilor rămâne de stabilit; nu există încă înregistrare publică sau comandă dedicată de creare a unui cont staging.

Instalează poolul FPM și virtual hostul în directoarele distribuției, fără a înlocui configurațiile celorlalte site-uri. Nginx trimite HTML-ul `/app/` prin Laravel pentru a păstra politica CSP și revalidarea; numai asseturile construite sunt servite direct cu cache lung. Nu executa `storage:link`: fișierele private rămân accesibile prin API-ul autorizat. Emite certificatul prin mecanismul existent al serverului înainte de activarea configurației HTTPS; exemplul nu configurează validarea ACME.

După pregătirea release-ului și acordarea permisiunilor, schimbă legătura `current` prin redenumire pe același filesystem, ca utilizator de deploy:

```bash
ln -s "$MEMORIA_RELEASE" /srv/memoria/current.next
mv -Tf /srv/memoria/current.next /srv/memoria/current
```

`current.next` trebuie să nu existe înainte de comandă. Reîncarcă serviciul PHP-FPM al distribuției pentru noul cod/opcache. Copiază cele patru fișiere `memoria-*.service`/`memoria-*.timer` în `/etc/systemd/system`, apoi aplică `systemctl daemon-reload`. Înainte de activare, instalatorul verifică sintaxa configurațiilor adaptate cu `nginx -t`, comanda `php-fpm8.4 -t` (numele diferă între distribuții) și `systemd-analyze verify` asupra celor două servicii și timere. Aceste comenzi nu au fost executate în sesiunea de pregătire.

Scoate mentenanța cu `sudo -u memoria /usr/bin/php /srv/memoria/current/backend/artisan up`, reîncarcă Nginx și activează `systemctl enable --now memoria-outbox.timer memoria-reminders.timer`. Serviciile citesc `.env` prin Laravel; nu folosi `EnvironmentFile` cu un fișier dotenv și nu introduce credențiale administrative în unități. Timerul nu pornește o a doua instanță a aceluiași serviciu cât timp acesta rulează. `TimeoutStartSec=0` permite încheierea loturilor lungi; o execuție blocată trebuie investigată, nu presupusă sănătoasă. O eroare este reîncercată la activarea următoare, conform backoffului outbox; nu există încă alertare automată.

Pentru urmărire pe server: `systemctl list-timers 'memoria-*'`, `systemctl status memoria-outbox.service memoria-reminders.service`, `journalctl -u memoria-outbox.service -u memoria-reminders.service` și logurile din storage. La actualizare oprește timerele și așteaptă terminarea serviciilor înaintea schimbării release-ului; nu adăuga separat `schedule:work`, queue worker sau cron, deoarece aceste două comenzi nu le folosesc. Scanarea uploadurilor rămâne sincronă, iar ClamAV/FreshClam trebuie supravegheate de serviciile distribuției conform secțiunii 4.

Rămân neînchise în O2: adaptarea și instalarea pe distribuția reală, conturile sintetice, scanarea/cleanupul în worker și verificările serverului. `/up` confirmă bootul Laravel, fără să demonstreze DB, ClamAV, autorizarea ori backupul. Revenirea legăturii `current` nu anulează o migrație; nu rula rollback de schemă automat. Backupul DB + storage și compatibilitatea release-ului anterior se stabilesc separat în O3/O4.

Referințe pentru instalator: [deploy Laravel 13](https://laravel.com/docs/13.x/deployment), [pool PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php), [timere systemd](https://www.freedesktop.org/software/systemd/man/latest/systemd.timer.html).

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

## Administrare Amora AP1

La actualizarea din 24 octombrie, aplică toate cele trei migrații noi (`application_email_settings`, `is_platform_admin`, `email_deliveries`) cu rolul administrativ, apoi `ops/grant-runtime.sql`. Acesta refuză rolului runtime modificarea indicatorului de administrator; permisiunile pe coloanele obișnuite ale utilizatorilor se păstrează.

Completează `APP_ADMIN_EMAILS` în `backend/.env` cu adresa contului autorizat (mai multe adrese se separă prin virgulă). Este o restricție suplimentară: simpla înscriere cu acea adresă nu acordă administrarea. Creează contul în mediul vizat prin fluxul de înscriere sau prin provisionare privată, apoi, în contextul DB administrativ folosit pentru migrații și înainte de cacheul final al configurației, rulează:

```sh
php backend/artisan app:grant-admin administrator@example.test --no-interaction
```

Înlocuiește numai adresa exemplu. Comanda operează exclusiv pe un cont existent și nu creează ori resetează parole. Revino la configurația DB runtime înainte de `config:cache` și repornește procesele de lungă durată la instalarea codului. Ulterior, setările SMTP sunt recitite la fiecare trimitere, fără restart pentru fiecare editare.

Conturile, parolele, cheile și setările locale nu se transferă prin Git. Nu pune credențiale în migrații, seedere, argumente de comandă sau documentație. Contul creat local pentru această implementare trebuie provisionat și autorizat separat pe server.

După login, administratorul intră în **Administrare → Email**: copiază Login și SMTP key din Brevo, completează expeditorul validat și opțional adresa de răspuns, activează trimiterea și salvează. Serverul/portul/TLS sunt fixe pentru Brevo. Cheia goală păstrează cheia existentă, fără să o afișeze. „Trimite email de test” folosește setările salvate și destinatarul introdus. Statisticile înregistrează rezultatul acceptării SMTP, fără corpul emailului și fără confirmarea livrării în inbox. SMTP pornește dezactivat; niciun email real nu a fost trimis în verificările AP1.

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

În checkoutul Windows verificat la 21 octombrie 2026, distribuția portabilă [ClamAV 1.5.4](https://www.clamav.net/downloads) este în `.tools/clamav/clamav-1.5.4.win.x64/`. Configurațiile locale `.runtime/clamd.conf` și `.runtime/freshclam.conf` folosesc `DatabaseDirectory` cu calea absolută către `.runtime/clamav-db`; FreshClam folosește `DatabaseMirror database.clamav.net`. Configurația clamd include limitele de mai sus. Aceste fișiere, semnăturile și executabilele sunt ignorate de Git și se recreează la altă clonare.

Actualizarea manuală a semnăturilor și pornirea pe acest Windows, din rădăcina proiectului:

```powershell
& .\.tools\clamav\clamav-1.5.4.win.x64\freshclam.exe --quiet --config-file=.runtime/freshclam.conf
.\ops\start-local.ps1
```

Scriptul caută PHP în PATH, apoi în `~/php84/php.exe`; poți indica altă instalare prin `-PhpPath`. Pornește și workerul `calendar:deliver-reminders --watch`, care verifică reminderele in-app la fiecare 15 secunde. Logurile lui sunt `.runtime/reminders.out.log` și `.runtime/reminders.err.log`; pentru o singură verificare rulează `php -d extension=pdo_pgsql backend/artisan calendar:deliver-reminders`. În afara acestui mediu local, comanda trebuie ținută pornită de managerul de procese al serverului.

După migrațiile K4b/P3 și aplicarea `ops/grant-runtime.sql`, același script pornește ascuns și `outbox:deliver --watch`, la 15 s. Loguri: `.runtime/outbox.out.log` / `.runtime/outbox.err.log`; fără mesaje repetate când nu există lucru. Pentru o singură trecere, din rădăcina proiectului:

```sh
php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=11111111-1111-4111-8111-111111111111
php -d extension=pdo_pgsql backend/artisan kitchen:rebuild-activity --family=11111111-1111-4111-8111-111111111111
php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=11111111-1111-4111-8111-111111111111 --replay=kitchen.activity.v1
php -d extension=pdo_pgsql backend/artisan memory:rebuild-timeline --family=11111111-1111-4111-8111-111111111111
php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=11111111-1111-4111-8111-111111111111 --replay=memory.timeline.v1
```

Înlocuiește UUID-ul demo cu familia dorită. Rebuildul reconstruiește indexul din sursele actuale, inclusiv cele existente înainte de outbox, fără schimbarea surselor. Replay reprogramează handlerul pentru evenimentele păstrate; repetă livrarea până la epuizarea loturilor (implicit 100/familie, maximum 1000 prin `--limit`). Efectele handlerului și confirmarea sunt DB-atomice; emailul/alte servicii externe nu intră în acest contract. După modificarea codului handlerilor, oprește și repornește workerul outbox; în afara mediului local folosește un manager de procese. Nu porni două instanțe de teste pe aceeași bază de test și nu direcționa workerii de test către `memoria`.

La instalarea AM3, aplică migrația `2026_10_22_190000_create_memory_timeline_sources.php` și granturile runtime înaintea publicării buildului și a pornirii workerului nou. Rulează `memory:rebuild-timeline` separat pentru fiecare familie existentă, apoi repornește workerul pentru înregistrarea handlerului `memory.timeline.v1`. Acesta consumă și `kitchen.meal.changed`; o masă nouă are receipts distincte pentru Kitchen și Memory. Replay-ul nu înlocuiește rebuildul pentru sursele fără evenimente istorice. Nu rula seedere sau resetări de date pentru instalarea cronologiei.

FreshClam rulează la cerere, fără actualizare programată configurată aici. Logurile sunt `.runtime/freshclam.log`, `.runtime/clamd.log` și `.runtime/clamd.err.log`. După pornire, clamd încarcă bazele în memorie înainte să poată scana. `ops/stop-local.ps1` oprește procesele acestui checkout, inclusiv workerii pentru remindere și outbox.

Din rădăcina proiectului, cu extensiile PHP deja activate:

```sh
php -d upload_max_filesize=10M -d post_max_size=12M -S 127.0.0.1:8000 -t backend/public ops/router.php
```

Deschide [aplicația](http://127.0.0.1:8000/app/) sau [previzualizarea mobilă](http://127.0.0.1:8000/preview/mobile). Apasă Ana, apoi „Intră în cont”. Serverul din terminal se oprește cu Ctrl+C. PostgreSQL și ClamAV se opresc separat, prin mecanismul instalării lor.

La instalarea SC1 (Sarcini în Calendar), aplică migrațiile, inclusiv `2026_10_23_232810_allow_private_task_calendar_sources.php`, apoi rulează `php -d extension=pdo_pgsql backend/artisan tasks:sync-calendar` pentru sarcinile existente, înaintea deschiderii noului build. Comanda păstrează sarcinile și se poate repeta fără duplicarea proiecțiilor; nu configurează remindere. Nu folosi seederul pentru această actualizare.

La instalarea S3 și Animale, aplică `2026_10_23_235040_add_task_recurrence.php` și `2026_10_24_000128_create_pet_tables.php` înaintea noului build. Reaplică granturile runtime din `ops/grant-runtime.sql`; noile tabele sunt `pets`, `pet_entries` și `pet_documents`. Actualizarea este aditivă și nu necesită seed/reset sau animale demonstrative. Recurența este inițial oprită pentru sarcinile existente. Scadențele animalelor folosesc workerul existent `calendar:deliver-reminders`, cu notificări in-app; acest pas nu configurează email sau push nativ.

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
