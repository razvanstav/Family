# Operare și verificare

Stare: mediul local și scripturile de pornire/test există. CI de bază se introduce în M04; stagingul și acceptarea pentru beta se închid în M11.

Decizie de găzduire la 22 octombrie 2026: utilizatorul predă instalarea pe serverul Linux dedicat propriu unei alte persoane. Cerințele și limitele sunt în [nota pentru instalator](../../ops/SETUP.md#predare-pentru-serverul-linux). O2a are configurații și procedură de predare implementate; adaptarea, instalarea și acceptarea O2 pe server rămân neînchise.

## Fișiere și comenzi existente

- Instalare: [ops/SETUP.md](../../ops/SETUP.md). Consultă-l la instalare ori la o problemă de mediu, nu la fiecare reluare.
- Pornire: `./ops/start-local.ps1`; oprire: `./ops/stop-local.ps1`.
- Suita locală cu antivirus real și build: `./ops/test-local.ps1`.
- `./ops/test-local.ps1 -WithoutAntivirus` omite integrarea ClamAV reală; nu poate închide acceptarea scannerului real.
- Pentru teste restrânse, folosește `Resolve-MemoriaPhp` din `ops/resolve-php.ps1` și PHPUnit din `backend`, cu extensiile `pdo_pgsql`/`gd` și fișierul ori filtrul afectat. Testele folosesc `memoria_test`, nu datele de dezvoltare.
- Frontend: `npm.cmd run check` sau `npm.cmd run build` din `frontend`, după natura schimbării.

## Pași

- [ ] **O1 — CI de bază în M04.** Fixează mediul reproductibil, PostgreSQL de test și rolul runtime real; rulează verificările backend, TypeScript și buildul. Adaugă verificarea contractelor și limitelor de import pe măsură ce apar. Schimbările PHP respectă formatterul și instrucțiunile locale; noile migrații sunt probate pe o bază curată și prin upgrade.
- [ ] **O2 — Staging.** Configurează separat DB, storage, email și chei, numai cu date sintetice. Livrează builduri versionate, migration job unic, worker/scheduler și HTTPS cu cookie sigur. Conturile demo și fixture-urile sunt excluse din publicare. Mută scanarea și cleanupul în execuție supravegheată înainte de utilizare reală.
  - [x] **O2a — Pregătirea predării Linux.** Modele Nginx/PHP-FPM, mediu staging și timere systemd, plus ordinea release → migrare → granturi runtime → cache → activare. Implementate în surse; revizuite static, fără instalare sau validare a configurațiilor de către serviciile Linux.
  - [ ] **O2b — Instalare și acceptare pe server.** Confirmă distribuția, resursele, domeniul și serviciile existente; adaptează fișierele, provisionarea conturilor sintetice și scanarea/cleanupul. Rulează validarea configurațiilor și verificările staging înainte de a declara O2 încheiat.
- [ ] **O3 — Backup și restaurare.** Stabilește țintele de pierdere/recuperare acceptate; implementează backup pentru DB și obiecte, apoi restaurează într-un mediu izolat. Verifică consistența fișierelor, replayul ștergerilor și timpul măsurat. Nu deduce protecția bytes din existența unui backup DB.
- [ ] **O4 — Monitorizare și release.** Verifică readiness, erori redactate, corelare cereri/joburi, lag outbox, retry, quarantine, cotă și livrări incerte. Probează alarmele, drenarea workerilor și rollbackul compatibil cu schema; altfel folosește remediere înainte, fără rollback distructiv.
- [ ] **O5 — Acceptare beta.** Rulează regresia integrată, verificările de accesibilitate/mobil, cele trei fluxuri Bucătărie–Amintiri, Animale–Calendar și Vehicule–Calendar, plus revocarea pe toate suprafețele. Măsoară interogările și paginarea timeline pe un volum sintetic reprezentativ, inclusiv reperul de 100.000 de elemente din blueprint. Închide defectele blocante înainte de beta.

## Acceptare pentru beta

- Toate cele șase module din blueprint sunt utile și au fluxurile declarate acceptate; nu există defect blocant cunoscut de izolare, acces la fișiere sau pierdere de date.
- Restoreul DB+objects a fost executat și măsurat; logurile/backupurile nu ignoră ștergerile finalizate.
- Migrațiile, joburile și buildurile pot fi reproduse; secretele și datele reale nu sunt în Git sau în fixture-uri.
- Limitările, suportul și comportamentul la eroare sunt clare pentru utilizatorii beta. Publicarea aplicației este o acțiune separată de pushul surselor pe Git.

## Evidența verificărilor

### Rulare locală Windows — 23 octombrie 2026

- Repornire după pull la `5cf779d`: buildul TypeScript/Vite a trecut, iar `./ops/start-local.ps1` folosește PHP din PATH (`C:/Users/razva/php84/php.exe`). Aplicată numai migrația restantă `create_family_invitation_codes_table` în `memoria`, cu rolul administrativ, apoi `ops/grant-runtime.sql`; workerii au fost opriți înainte și reporniți după. Fără reset, seed sau modificări în `memoria_test`.
- Verificat după această repornire: HTTP 200 pentru `/app/`, `/up`, `/api/v1/session`; PostgreSQL acceptă conexiuni pe 55432, ClamAV răspunde PONG pe 3310, iar rolul runtime poate interoga noul tabel. Workerii remindere/outbox sunt activi, cu loguri de erori goale; logul de erori ClamAV este gol. Browserul aplicației a primit cererea de deschidere. Fără suită de teste sau verificare vizuală/autentificată; pornirea și conexiunile locale au necesitat execuție în afara sandboxului.
- La cererea utilizatorului, aplicația a fost pornită după pull cu `./ops/start-local.ps1 -PhpPath "$PWD/.tools/php84/php.exe"`. PHP portabil existent: 8.4.26. Buildul TypeScript/Vite a reușit; serverul ascultă la `http://127.0.0.1:8000/app/`.
- Baza `memoria` avea șapte migrații restante, de la `create_memory_moments` până la `create_vehicles_tables`. Aplicate cu rolul administrativ, urmate de `ops/grant-runtime.sql` și `memory:rebuild-timeline` pentru cele două familii existente (zero referințe la această rulare). Fără reset sau seed. Workerii opriți înainte de migrare și reporniți după granturi, cu rolul runtime.
- Verificat: toate migrațiile apar aplicate pe `memoria`; HTTP 200 pentru `/app/`, `/up` și `/api/v1/session`; PostgreSQL acceptă conexiuni pe 55432, ClamAV răspunde PONG pe 3310. Logurile de erori ale reminderelor, outbox și ClamAV sunt goale după repornire. Buildul și pornirea au necesitat execuție în afara sandboxului din cauza permisiunilor Windows.
- Limite: fără teste noi sau suită rulată, fără actualizarea/verificarea bazei `memoria_test`, fără verificare vizuală ori flux autentificat în această sesiune. Acceptarea vizuală Auto/UT2 rămâne pentru feedback; fără publicare în producție.

### O2a — 22 octombrie 2026

- Intrări: [ops/linux](../../ops/linux/) și [procedura de predare](../../ops/SETUP.md#o2a--configurații-pentru-instalator). Nu se schimbă aplicația, schema sau dependențele.
- Modelele folosesc un release identificat prin commit, `current` și `.env`/storage persistente. Migrațiile folosesc temporar rolul administrativ, iar cache-ul, HTTP și serviciile folosesc `memoria_app`, cu RLS. Parolele și cheia nu sunt completate în Git.
- HTML-ul aplicației trece prin ruta Laravel existentă pentru CSP și cache; numai asseturile sunt servite direct. Nginx publică exclusiv `backend/public`. Poolul FPM păstrează limitele 10 MiB pentru fișier și 12 MiB pentru request.
- Serviciile `Type=oneshot` sunt pornite prin timere la 15 s după încheierea trecerii anterioare, fără `--watch`, cron sau scheduler suplimentar. La upgrade se opresc timerele și se așteaptă terminarea serviciilor, apoi se schimbă release-ul.
- La cererea utilizatorului, nu se scriu și nu se rulează teste. Revizia statică include comenzile existente, rutele, căile, linkurile locale și `git diff --check`. Pe acest checkout macOS nu sunt disponibile Nginx, PHP-FPM sau systemd pentru validarea nativă a configurațiilor; nu s-au pornit servicii, builduri sau migrații.
- Limite: exemplele cer adaptare la server și certificat existent; provisionarea conturilor staging nu este implementată. Scanarea rămâne sincronă; cleanupul, backupul/restaurarea și alertarea rămân deschise. Nu sunt probate deployul, rebootul, autentificarea ori lucrul cu date pe Linux.

În STATE se păstrează doar comanda relevantă, rezultatul și limita rămasă. Nu recopia outputuri mari în fișe. Testele din blueprint sunt specificații, nu rezultate executate.

Pentru documentație: verifică linkurile și referințele, coerența modulelor/pașilor și `git diff --check`. Nu porni servicii și întreaga suită pentru o schimbare exclusiv de documentație.
