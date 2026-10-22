# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Operare, **O2a — pregătirea predării Linux — implementat în surse, revizuit static**. Fișierele și limitele sunt în [fișa Operare](modules/operations.md#o2a--22-octombrie-2026) și [procedura instalatorului](../ops/SETUP.md#o2a--configurații-pentru-instalator). O2 nu este închis.
- **Prioritatea utilizatorului:** continuarea utilă după pull, fără teste, urmată de commit și push. S-a continuat prioritatea salvată pentru predarea serverului Linux dedicat unei alte persoane. Aplicația rămâne Laravel/PHP + React + PostgreSQL.
- **Rezultat:** modele Nginx HTTPS, pool PHP-FPM, `.env` staging și două perechi serviciu/timer systemd. Procedura folosește release-uri pe commit, `.env`/storage persistente, migrare separată cu rol administrativ, granturi runtime și cache generat fără credențialele administratorului. Timerele execută câte o trecere, fără `--watch`; la upgrade se așteaptă încheierea lor.
- **Verificări:** citirea comenzilor/rutelor/configurațiilor și a testelor relevante existente; revizie statică a căilor, contractelor și linkurilor locale; `git diff --check`. Nu s-au scris sau rulat teste, conform cererii utilizatorului. Nu sunt modificări ale aplicației, schemei sau dependențelor; nu s-au rulat builduri ori migrații. Nginx/PHP-FPM/systemd nu sunt disponibile aici pentru validarea nativă a configurațiilor.
- **Limite:** configurațiile cer adaptare la distribuție, domeniu, certificate, conturi OS și resurse. Nu s-au instalat servicii și nu s-a accesat serverul. Provisionarea conturilor staging, scanarea/cleanupul în worker, backupul/restaurarea și alertarea rămân deschise; deployul, rebootul și fluxurile pe Linux nu sunt verificate.
- **Livrare:** O2a și punctul de reluare se publică pe `codex/documente-design`; commitul și sincronizarea se verifică din Git. K4b/P3 era deja livrat prin `624d0a7`, K4a prin `06d3a42`. Secretele și datele private nu sunt incluse.
- **Blocaje:** niciunul pentru pregătirea O2a. Instalarea O2b necesită datele serverului și accesul instalatorului.

## Prima acțiune la reluare

Predarea pentru serverul Linux are prioritate: pornește de la [O2a — configurații pentru instalator](../ops/SETUP.md#o2a--configurații-pentru-instalator) și [O2b — instalare și acceptare](modules/operations.md). Confirmă distribuția/versiunea, resursele, domeniul, certificatele și serviciile existente înainte de adaptarea și activarea configurațiilor. Stabilește provisionarea conturilor sintetice; nu copia mediul local, conturile demo sau datele personale. O2a nu dovedește funcționarea pe Linux.

K4b/P3 este încheiat local. La revenirea la funcționalități, citește [fișa Bucătărie](modules/kitchen.md), secțiunea „Următorul pas”, și cererea nouă a utilizatorului. K3b (preparări libere/participanți) are scopul de stabilit; integrarea vizibilă în timeline aparține AM3. Nu începe automat alt pas/modul. Nu modifica `recipe_versions`; mesele păstrează versiunea exactă. Pentru un consumator nou folosește contractele publice `Activity`/`Outbox`, cu reconciliere și autorizare canonică.

Păstrează instrucțiunile `AGENTS.md`, Helvetica, paleta caldă și etichetele directe. Ordinea rămâne Acasă → Familia mea → Documente → Bucătărie → Calendar, identică desktop/mobil; Acasă implicită. Referința Bucătărie: `artifacts/kitchen-reference.png`.

## Mediu local

- Checkout curent pe macOS, clonat prin pull din `Family`. În sesiunea O2a nu s-au instalat runtime-uri/dependențe și nu s-au pornit aplicația, DB, ClamAV sau workerii.
- Mediul Windows anterior folosea http://127.0.0.1:8000/app/, PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310` și PHP `.tools/php84/php.exe`. Starea proceselor de acolo nu este cunoscută în acest checkout. Rezultatele K4b/P3 anterioare rămân în [fișa Bucătărie](modules/kitchen.md#k4b--evenimente-durabile); nu au fost reluate aici. PHPUnit trebuie să folosească exclusiv `memoria_test`, niciodată `memoria`.
- Pornire `.\ops\start-local.ps1 -PhpPath "$PWD/.tools/php84/php.exe"`, oprire `.\ops\stop-local.ps1`. Procese ascunse; `calendar:deliver-reminders --watch` și `outbox:deliver --watch`, la 15 s. Loguri outbox `.runtime/outbox.out.log` / `.runtime/outbox.err.log`. După schimbarea handlerilor se repornește workerul outbox.
- Operații țintite: `php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=<UUID>`; `--replay=kitchen.activity.v1` reprogramează handlerul pentru familia explicită. `kitchen:rebuild-activity --family=<UUID>` reconstruiește din sursele canonice fără schimbarea lor. Vezi [SETUP](../ops/SETUP.md).
- `--limit` este maximum per familie/per trecere, nu promisiunea golirii tuturor evenimentelor într-o singură comandă. Replay/rebuild nu trimit notificări externe și nu creează surse duplicate.
- Secretele rămân în fișierele locale ignorate; nu afișa `.env` ori credențiale. La `psql -c` pe Windows folosește SQL ASCII cu escape Unicode ori fișiere UTF-8.
