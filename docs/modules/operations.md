# Operare și verificare

**NR1, 24 octombrie 2026:** reminderele și outbox rulează la un minut. Sursele automate se reconciliază numai după modificări sau schimbarea zilei, prin checkpointul Calendar; fără scanări complete repetate la 15 secunde. Migrația aditivă `2026_10_24_153519`, granturile runtime și repornirea workerilor sunt obligatorii. Timerele systemd se reinstalează cu noile fișiere și `daemon-reload`; nu porni simultan timere și `--watch`. Emailurile folosesc același contract și transport Brevo centralizat, cu autorizarea finală păstrată. [Contracte și verificări NR1](calendar.md#nr1--reconciliere-la-schimbări-și-email-comun-24-octombrie-2026).

**NS1, 24 octombrie 2026:** workerul existent `calendar:deliver-reminders` livrează și emailuri pentru canalele activate personal în Contul meu → Setări. Migrațiile aditive `2026_10_24_141611`, `141721`, `170100`, granturile runtime și repornirea proceselor `--watch` sunt necesare la instalare; nu se adaugă cron/worker separat. Brevo trebuie configurat și activat în Administrare, iar emailul este implicit debifat. SMTP indisponibil produce amânare înaintea trimiterii; răspunsul incert nu este retrimis automat. Stările și verificările cu transport fals sunt în [NS1](calendar.md#ns1--setări-personale-și-emailuri-automate-24-octombrie-2026). Acceptarea SMTP nu confirmă inboxul.

## Publicare H5/NR1/DC1/NS1/MP1, 24 octombrie 2026

Push confirmat în repositoryul public razvanstav/Family, după aprobarea explicită a utilizatorului: commit **4c40a50**, ramura codex/documente-design. [Deployul 40110325769](https://github.com/razvanstav/Family/actions/runs/40110325769) a reușit: cele șase migrații 132325/141611/141721/144808/153519/170100 aplicate, GRANT executat, containerele aplicației/web/ClamAV recreate și pornite, verificările HTTP și HTTPS origin trecute. Verificare suplimentară HTTPS public: /up și /app/ HTTP 200, bundle index-BswbLztS.js identic cu buildul verificat local. Datele locale și credențialele nu sunt publicate. Configurația efectivă a workerilor din Dockerul extern se verifică separat, fără execuția livrării drept probă.

## Lucru pe mai multe calculatoare

Codul este comun prin Git; bazele locale, media și fișierele de mediu rămân separate pe fiecare calculator. Înainte de o sesiune, verifică starea locală și preia ramura remote. Pentru lucru simultan, folosește ramuri distincte cu prefixul codex/ și integrează schimbările după verificare. Un push refuzat cere fetch și compararea istoriilor; nu se rezolvă prin force push. Migrațiile aditive și granturile se aplică separat fiecărui mediu, fără copierea sau resetarea bazei altui PC.

Stare: mediul local și scripturile de pornire/test există. CI de bază se introduce în M04; stagingul și acceptarea pentru beta se închid în M11.

## O2b — Eficientizare deploy, 24 octombrie 2026

**Implementat, publicat și verificat în Actions.** Intrare: [workflowul de staging](../../.github/workflows/deploy-family.yml). Cererea curentă este reducerea timpului și a rulărilor inutile, fără schimbări în aplicație sau în procedura externă de migrare.

- Baseline observat prin API-ul GitHub: [rularea 40083569029](https://github.com/razvanstav/Family/actions/runs/40083569029) a avut jobul de 30 s, cu build/deploy 11 s; [40086579720](https://github.com/razvanstav/Family/actions/runs/40086579720) a avut 58 s, cu build/deploy 39 s. Ambele au reușit. SSH, rsync și verificările au ocupat împreună aproximativ 12–13 s. Duratele joburilor exclud așteptarea runnerului și nu estimează facturarea.
- Pushurile exclusiv Markdown, `docs/`, `artifacts/` sau blueprint nu pornesc publicarea; schimbările mixte și pornirea manuală rămân disponibile. Aceleași referințe și directoarele locale `.runtime/`/`.tools/` sunt excluse din rsync. Nu se folosește `--delete-excluded`; fișierele persistente deja excluse rămân protejate.
- Testul SSH, sincronizarea, buildul și cele două verificări reutilizează o conexiune OpenSSH, cu verificarea gazdei și autentificarea păstrate. Conexiunea și cheia runnerului sunt curățate la încheiere. Verificarea HTTP locală are limite de conectare/răspuns. Deployurile rămân seriale, fără anularea celui activ în timpul migrării.
- Verificări locale trecute: actionlint 1.7.12, parsare YAML, sintaxă Bash pentru fiecare pas, `ssh -G` fără conexiune și rsync dry-run cu fișiere reprezentative: codul se transferă, codul învechit se șterge, iar `.env`, storage, cache și configurația Docker a serverului sunt protejate. Revizie independentă a diff-ului; calea configurației SSH din rsync este absolută. Fără suite/build de aplicație, fiind schimbat numai workflowul.
- **Verificat pe server:** commitul `6e42871`, [rularea 40089157338](https://github.com/razvanstav/Family/actions/runs/40089157338), reușit în 22 s pentru job: SSH inițial 3 s, rsync 2 s, build/deploy 9 s, verificări 1 s cumulat și închidere SSH reușită. Ambele straturi HTTP/HTTPS au trecut. SSH, rsync și verificările au coborât împreună la 6 s în această probă.
- Limite: scriptul `/srv/family/deploy/deploy.sh` și configurația Docker sunt externe repositoryului. Cache-ul Docker nu a fost inspectat sau modificat. Proba nouă nu schimbă codul aplicației; buildul de 9 s nu prezice durata unui release cu schimbări de cod/dependențe. Baseline-ul are numai două deployuri reușite.

## AP1 — Administrare Amora, 24 octombrie 2026

**Implementat și verificat local, acceptare vizuală deschisă:** logo comun în dashboard, cont propriu de administrator al aplicației, deschidere în Administrare după login, statistici reale și configurare Brevo SMTP. Commit și push sunt autorizate. Workflowul existent `Deploy Family Staging` publică automat schimbările de cod/configurație pe `codex/documente-design`, cu filtrele O2b; deployul commitului `105bddf` a reușit în rularea `40086579720`.

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
- Serviciile `Type=oneshot` sunt pornite prin timere la 60 s după încheierea trecerii anterioare, fără `--watch`, cron sau scheduler suplimentar. La upgrade se opresc timerele și se așteaptă terminarea serviciilor, apoi se schimbă release-ul.
- La cererea utilizatorului, nu se scriu și nu se rulează teste. Revizia statică include comenzile existente, rutele, căile, linkurile locale și `git diff --check`. Pe acest checkout macOS nu sunt disponibile Nginx, PHP-FPM sau systemd pentru validarea nativă a configurațiilor; nu s-au pornit servicii, builduri sau migrații.
- Limite: exemplele cer adaptare la server și certificat existent; provisionarea conturilor staging nu este implementată. Scanarea rămâne sincronă; cleanupul, backupul/restaurarea și alertarea rămân deschise. Nu sunt probate deployul, rebootul, autentificarea ori lucrul cu date pe Linux.

În STATE se păstrează doar comanda relevantă, rezultatul și limita rămasă. Nu recopia outputuri mari în fișe. Testele din blueprint sunt specificații, nu rezultate executate.

Pentru documentație: verifică linkurile și referințele, coerența modulelor/pașilor și `git diff --check`. Nu porni servicii și întreaga suită pentru o schimbare exclusiv de documentație.
