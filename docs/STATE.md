# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Bucătărie, **K4b — evenimente durabile — implementat și verificat local**, cu [P3 strict necesar](modules/platform.md#p3--contract-și-execuție). Contractele și probele sunt în [fișa Bucătărie](modules/kitchen.md#k4b--evenimente-durabile).
- **Prioritatea utilizatorului:** publicarea tuturor modificărilor K4b/P3 în Git pentru predarea instalării pe serverul Linux dedicat propriu, unei alte persoane. Aplicația rămâne Laravel/PHP + React + PostgreSQL; nu s-a aprobat o rescriere sau migrarea la MySQL. Deployul Linux este planificat, nu implementat sau verificat. Ecranul timeline și modulul Amintiri nu au fost începute.
- **Rezultat:** schimbările rețetelor și ale meselor/feedbackului salvează evenimentul în tranzacția sursei/auditului. Proiecția și receiptul sunt atomice; mesajele repetate/vechi recitesc sursa actuală. Indexul nu copiază titluri sau ACL. Citirea reverifică accesul, inclusiv înainte de procesarea unui eveniment de retragere. `activity_revision` al mesei nu schimbă versiunea rețetei folosite; versiunile istorice nu sunt actualizate.
- **Verificări automate:** `KitchenOutboxTest`: **15 teste / 243 aserțiuni**. Regresia Kitchen + FamilyMembership + FamilyInvitation + CalendarReminder: **95 teste / 1.242 aserțiuni**, trecute pe `memoria_test`. Cele 15 teste au fost reluate după legarea cititorului la contractul `Activity`. Doi workeri PHP reali au confirmat o singură livrare; sunt demonstrate rollbackul dintre proiecție și receipt, backoff, handler independent, replay, ordine inversată, arhivare, revocare, RLS/FK/context, paginare criptată și rebuild atomic.
- **Verificări operaționale:** Pint, sintaxa scripturilor PowerShell, ruta API și ajutorul CLI trecute. Migrația `2026_10_22_095156_create_durable_kitchen_events.php` aplicată pe ambele baze cu granturi runtime; rollback/reaplicare verificate numai pe `memoria_test`. Local, rebuild Popescu: 0 surse; livrare unică: 0 livrate / 0 eșuate. Worker outbox pornit ascuns prin scriptul de pornire. Nu există modificări frontend, deci nu s-au repetat buildul, testele frontend sau verificarea vizuală K4a. Suitele backend fără contract modificat și ClamAV live nu au fost reluate.
- **Date locale:** K4b nu a creat probe în `memoria` și nu a resetat identități, apartenențe, parole ori surse. Testele mută date exclusiv în `memoria_test`. Nu există date temporare de produs K4b de curățat.
- **Limite:** index eventual consistent, polling implicit la 15 s; handlerii au numai efecte DB. Nu s-au provocat kill OS în tranzacție ori avarie PostgreSQL; eroarea intermediară este simulată controlat. Nu există UI timeline/AM3, transport extern, retenție/curățare outbox, activare/dezactivare module sau idempotency general la POST-urile HTTP de creare. Nu sunt verificate producția sau recuperarea după avarie fizică.
- **Livrare:** K4b/P3 se livrează împreună cu acest punct de reluare și documentația operațională pe `codex/documente-design`, la cererea utilizatorului. K4a a fost livrat prin `06d3a42`; hashul nou și sincronizarea cu origin se verifică din Git. Datele locale, secretele, logurile și runtime-ul sunt excluse.
- **Blocaje:** niciunul pentru K4b/P3.

## Prima acțiune la reluare

Predarea pentru serverul Linux are prioritate: pornește de la [nota pentru instalator](../ops/SETUP.md#predare-pentru-serverul-linux) și [O2 — staging](modules/operations.md). Distribuția, versiunea Linux, resursele și serviciile deja găzduite trebuie confirmate înaintea configurării. Nu copia mediul local, conturile demo sau datele personale pe server. Deployul nu este verificat și nu există încă o configurație Linux gata de aplicat.

K4b/P3 este încheiat local. La revenirea la funcționalități, citește [fișa Bucătărie](modules/kitchen.md), secțiunea „Următorul pas”, și cererea nouă a utilizatorului. K3b (preparări libere/participanți) are scopul de stabilit; integrarea vizibilă în timeline aparține AM3. Nu începe automat alt pas/modul. Nu modifica `recipe_versions`; mesele păstrează versiunea exactă. Pentru un consumator nou folosește contractele publice `Activity`/`Outbox`, cu reconciliere și autorizare canonică.

Păstrează instrucțiunile `AGENTS.md`, Helvetica, paleta caldă și etichetele directe. Ordinea rămâne Acasă → Familia mea → Documente → Bucătărie → Calendar, identică desktop/mobil; Acasă implicită. Referința Bucătărie: `artifacts/kitchen-reference.png`.

## Mediu local

- Aplicație: http://127.0.0.1:8000/app/, sesiune demo Mihai, familia Popescu. PostgreSQL `127.0.0.1:55432`; ClamAV `127.0.0.1:3310`. Aplicația, workerul de remindere și workerul outbox sunt pornite.
- PHP local: `.tools/php84/php.exe` (8.4.26); Composer ignorat `.tools/composer.phar`. Laravel instalat 13.38.0. PHPUnit folosește exclusiv `memoria_test`, niciodată `memoria`.
- Pornire `.\ops\start-local.ps1 -PhpPath "$PWD/.tools/php84/php.exe"`, oprire `.\ops\stop-local.ps1`. Procese ascunse; `calendar:deliver-reminders --watch` și `outbox:deliver --watch`, la 15 s. Loguri outbox `.runtime/outbox.out.log` / `.runtime/outbox.err.log`. După schimbarea handlerilor se repornește workerul outbox.
- Operații țintite: `php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=<UUID>`; `--replay=kitchen.activity.v1` reprogramează handlerul pentru familia explicită. `kitchen:rebuild-activity --family=<UUID>` reconstruiește din sursele canonice fără schimbarea lor. Vezi [SETUP](../ops/SETUP.md).
- `--limit` este maximum per familie/per trecere, nu promisiunea golirii tuturor evenimentelor într-o singură comandă. Replay/rebuild nu trimit notificări externe și nu creează surse duplicate.
- Secretele rămân în fișierele locale ignorate; nu afișa `.env` ori credențiale. La `psql -c` pe Windows folosește SQL ASCII cu escape Unicode ori fișiere UTF-8.
