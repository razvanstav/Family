# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Amintiri, **AM1 — momente și note — implementat în surse**, cu build frontend reușit; backendul/migrația și acceptarea runtime rămân neverificate. Contractul și limitele sunt în [fișa Amintiri](modules/memory.md#am1--momente-și-note).
- **Prioritatea utilizatorului:** continuarea modulelor pentru un MVP cu funcții, fără teste, urmată de commit și push. Această cerere înlocuiește prioritatea instalării Linux; aplicația rămâne Laravel/PHP + React + PostgreSQL.
- **Rezultat:** API și pagină Amintiri cu creare, listă, căutare în titlu/text, filtre, paginare, detaliu și editare cu If-Match. Momente/note cu dată și acces privat/familie, menționarea membrilor activi fără acordare implicită de acces. RLS/FK, politica autorului, audit minimal și tratarea conflictelor sunt implementate. Amintiri apare după Bucătărie pe desktop și mobil.
- **Verificări:** `npm --prefix frontend ci`, Prettier și `npm --prefix frontend run build` reușite; revizie statică a căilor/contractelor/linkurilor locale și `git diff --check`. Nu s-au scris și nu s-au rulat teste, conform cererii utilizatorului. Nu s-au schimbat dependențele sau lockfile-urile.
- **Limite:** PHP/Composer/vendor lipsesc aici, deci Artisan, Pint, lintul PHP, migrațiile, granturile și fluxurile HTTP/DB nu au fost executate; nu s-a pornit aplicația pentru verificare vizuală. Fotografii, acces selectiv, arhivare/restaurare, subiecți fără cont și timeline rămân pentru pașii următori. AM1 nu închide întregul MVP.
- **Livrare:** AM1 și punctul de reluare se publică pe `codex/documente-design`; hashul și sincronizarea se verifică din Git. O2a a fost păstrat în commit separat `14596a6`, cu configurații de predare revizuite static, fără instalare pe server. K4b/P3 era livrat prin `624d0a7`. Secretele și datele private nu sunt incluse.
- **Blocaje:** niciunul pentru publicarea surselor. Utilizarea AM1 în mediul țintă cere migrația `2026_10_22_170000_create_memory_moments.php`, apoi reaplicarea `ops/grant-runtime.sql` cu rolul administrativ, înaintea accesului cu rolul runtime.

## Prima acțiune la reluare

Continuă [Amintiri, pasul AM2](modules/memory.md#următorul-pas): fotografii prin pipeline-ul Media existent, acces selectiv și arhivare/restaurare. Păstrează prioritatea funcțiilor pentru MVP și cererea fără teste; nu reveni automat la configurarea serverului. Starea de implementare și verificarea runtime trebuie consemnate separat.

K4b/P3 este încheiat local. La revenirea la funcționalități, citește [fișa Bucătărie](modules/kitchen.md), secțiunea „Următorul pas”, și cererea nouă a utilizatorului. K3b (preparări libere/participanți) are scopul de stabilit; integrarea vizibilă în timeline aparține AM3. Nu începe automat alt pas/modul. Nu modifica `recipe_versions`; mesele păstrează versiunea exactă. Pentru un consumator nou folosește contractele publice `Activity`/`Outbox`, cu reconciliere și autorizare canonică.

Păstrează instrucțiunile `AGENTS.md`, Helvetica, paleta caldă și etichetele directe. Ordinea este Acasă → Familia mea → Documente → Bucătărie → Amintiri → Calendar, identică desktop/mobil; Acasă implicită. Referința Bucătărie: `artifacts/kitchen-reference.png`. Pregătirea Linux rămâne în [O2a](../ops/SETUP.md#o2a--configurații-pentru-instalator), fără deploy verificat.

## Mediu local

- Checkout curent pe macOS, clonat prin pull din `Family`. Dependențele frontend au fost instalate prin `npm ci`, iar buildul este generat local și ignorat de Git. Nu s-au instalat PHP/dependențe backend și nu s-au pornit aplicația, DB, ClamAV sau workerii.
- Mediul Windows anterior folosea http://127.0.0.1:8000/app/, PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310` și PHP `.tools/php84/php.exe`. Starea proceselor de acolo nu este cunoscută în acest checkout. Rezultatele K4b/P3 anterioare rămân în [fișa Bucătărie](modules/kitchen.md#k4b--evenimente-durabile); nu au fost reluate aici. PHPUnit trebuie să folosească exclusiv `memoria_test`, niciodată `memoria`.
- Pornire `.\ops\start-local.ps1 -PhpPath "$PWD/.tools/php84/php.exe"`, oprire `.\ops\stop-local.ps1`. Procese ascunse; `calendar:deliver-reminders --watch` și `outbox:deliver --watch`, la 15 s. Loguri outbox `.runtime/outbox.out.log` / `.runtime/outbox.err.log`. După schimbarea handlerilor se repornește workerul outbox.
- Operații țintite: `php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=<UUID>`; `--replay=kitchen.activity.v1` reprogramează handlerul pentru familia explicită. `kitchen:rebuild-activity --family=<UUID>` reconstruiește din sursele canonice fără schimbarea lor. Vezi [SETUP](../ops/SETUP.md).
- `--limit` este maximum per familie/per trecere, nu promisiunea golirii tuturor evenimentelor într-o singură comandă. Replay/rebuild nu trimit notificări externe și nu creează surse duplicate.
- Secretele rămân în fișierele locale ignorate; nu afișa `.env` ori credențiale. La `psql -c` pe Windows folosește SQL ASCII cu escape Unicode ori fișiere UTF-8.
