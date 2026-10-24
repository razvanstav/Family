# Operare și verificare

Stare: mediul local și scripturile de pornire/test există. CI de bază se introduce în M04; stagingul și acceptarea pentru beta se închid în M11.

## AP1 — Administrare Amora, 24 octombrie 2026

**Implementat și verificat local, acceptare vizuală deschisă:** logo comun în dashboard, cont propriu de administrator al aplicației, deschidere în Administrare după login, statistici reale și configurare Brevo SMTP. Commit și push sunt autorizate. Workflowul existent `Deploy Family Staging` publică automat pushurile pe `codex/documente-design`; rezultatul fiecărui deploy se consemnează numai după observare.

- Prezentare: număr de utilizatori, utilizatori noi în ultimele 30 de zile, familii, trimiteri email acceptate de SMTP/eșuate și ultimele 10 înregistrări. Nu se inventează date și nu se prezintă acceptarea SMTP drept confirmare în inbox. Istoricul începe cu implementarea jurnalului, fără reconstituirea trimiterilor anterioare.
- Email: server Brevo și port/TLS fixe, Login și cheie SMTP, nume/adresă expeditor, adresă opțională pentru răspunsuri, activare și test explicit către destinatarul ales. Cheia se salvează criptat, fără afișare la citire; câmpul gol păstrează cheia. Formularele folosesc versiune și reîncărcare explicită după conflict.
- Acces: rol global separat de `owner`, acordat numai contului existent prin `app:grant-admin`, cu adresă permisă în `APP_ADMIN_EMAILS`. Înscrierea fără verificare email nu poate acorda automat administrarea. Rolul runtime nu poate modifica indicatorul de administrator. Setările și jurnalul email au acces DB separat de familii.
- Intrări: `frontend/src/modules/administration/`, `shared/Brand.tsx`, compoziția/navigația din `app/`; backend `Platform/Administration/`, `GrantApplicationAdmin.php`, cele trei migrații din 24 octombrie și `ops/grant-runtime.sql`. API: `GET /api/v1/admin/overview`, `GET/PUT /api/v1/admin/email-settings` și `POST /api/v1/admin/email-test`. `/session` expune numai indicatorul `user.is_admin`; `/account` își păstrează contractul, iar frontendul păstrează indicatorul când actualizează numele.
- Configurarea Brevo folosește [Login și SMTP key](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP), nu cheia API; server fix `smtp-relay.brevo.com:587`, STARTTLS obligatoriu și timeout 10 secunde. Mailerul implicit al aplicației este `amora`, recitește setările la fiecare trimitere și refuză trimiterea dacă sunt dezactivate/incomplete. Vechiul `MAIL_MAILER` nu activează un fallback. Testul are limită separată de trei încercări/minut/administrator. Câmpurile se păstrează numai în RAM la trecerea într-un alt tab pentru copierea datelor Brevo și se golesc la logout, schimbarea contului sau pierderea autorizării; cheia nu intră în browser storage.
- **Verificat automat:** 44 teste backend / 501 aserțiuni (15 teste admin noi, Account, Registration și ModuleBoundaries), serial exclusiv pe `memoria_test`; cele 15 teste admin au trecut din nou după ultimele adnotări. Acoperă acces guest/owner/admin și allowlist fără grant, RLS, interdicția modificării flagului de către runtime, cheia criptată/mascată/păstrată/înlocuită, 412, validare, TLS/timeout, limitare separată, transport mock, configurare recitită și jurnal/statistici/To-Cc-Bcc/envelope. Build TypeScript/Vite, 10 teste frontend relevante, Prettier, Pint și diff check trecute. Nu s-au rulat alte suite fără o schimbare relevantă.
- **Verificat în browser pe 8000:** login cu contul autorizat → Administrare, reîncărcare și logout/relogin; un utilizator, zero familii/emailuri reale, fără valori fictive. Expeditorul Amora salvat cu trimiterea dezactivată și regăsit la remontare. Desktop 1280×900, mobil 390×844 și 320×800, fără overflow, controale minimum 44 px și consola observată fără erori/warninguri. Probă cu draft și cheie temporare, nesalvate: se păstrează după deschiderea/închiderea altui tab și dispar după logout/relogin. Capturi ignorate `.runtime/ap1-{admin,email}-{desktop,mobile}.png` și `ap1-email-320.png`; viewportul temporar resetat la încheiere.
- **Instalare locală:** toate cele trei migrații aditive și granturile sunt aplicate în `memoria` și `memoria_test`. Contul propriu a fost creat numai local, cu hash pentru parolă, și autorizat separat prin CLI; nicio parolă/adresă personală/cheie nu intră în fișierele versionabile. Nu s-au adăugat familii sau exemple în baza aplicației. Workerii de remindere/outbox au fost reporniți; SMTP rămâne dezactivat, fără cheie ori expeditor email configurat.
- **Limite:** niciun email real trimis, nicio confirmare inbox/webhook Brevo, fără suite de regresie integrală sau dispozitiv fizic. Lista populată de emailuri este acoperită de API/testele de transport; browserul aplicației arată starea reală goală. Crearea contului și configurația sa locală nu se transferă prin Git; pentru server sunt necesare instalarea și autorizarea descrise în [SETUP](../../ops/SETUP.md#administrare-amora-ap1).

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

### Rulare locală macOS — 24 octombrie 2026

- După pull la `acffe69`, instalate local PHP 8.4.1 (Herd Lite, cu pdo_pgsql/GD), Composer și PostgreSQL 18.6 (Postgres.app), numai în `.tools/`; dependențele Composer provin din lockfile. Buildul TypeScript/Vite a trecut.
- Checkoutul nu avea `backend/.env`, vendor sau bază locală. Configurație și parole generate în fișiere ignorate, cu permisiuni restrictive; PostgreSQL ascultă numai pe 127.0.0.1:55432. Create roluri separate admin/runtime și bazele `memoria`/`memoria_test`; toate cele 26 de migrații aplicate numai pe `memoria`, urmate de granturile runtime. Fără reset sau seed; baza de test rămâne nemigrată și nu s-au rulat suite. Datele Windows nu sunt incluse în Git.
- Pornire repetabilă în acest checkout: `node .runtime/start-local.cjs` (helper local ignorat); pornește PostgreSQL, serverul PHP pe 8000 și workerii remindere/outbox dacă lipsesc. Comenzile executate o dată pentru sincronizarea Sarcini, remindere și outbox au trecut, cu zero evenimente.
- Verificate HTTP 200 pentru `/app/`, `/up`, `/api/v1/session`, plus formularul Creează cont în browser. Instalare fără conturi/date demo (MEMORIA_DEMO=false); fără probă de autentificare sau creare de cont. ClamAV nu este instalat: uploadurile noi rămân în carantină până la configurarea scannerului. Fără modificări de produs sau deploy.

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
