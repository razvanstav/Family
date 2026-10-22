# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Amintiri, **AM2 — fotografii și partajare — implementat**, cu fluxurile principale verificate manual în mediul Windows. Acceptarea completă a AM1/AM2 rămâne deschisă pentru scenariile neexercitate din [fișa Amintiri](modules/memory.md#am2--fotografii-și-partajare).
- **Prioritatea utilizatorului:** un MVP demonstrabil în zilele următoare; continuăm AM2 și verificăm fluxul local. Se păstrează cererea anterioară fără teste, urmată de commit și push. Aplicația rămâne Laravel/PHP + React + PostgreSQL; instalarea Linux nu este pasul curent.
- **Rezultat:** fotografie opțională prin Media la momente/note, păstrare/înlocuire/eliminare, acces pentru persoane alese separat de menționare, retragere de acces și arhivare/restaurare cu revenire privată. Fotografia folosește politica actuală a amintirii; granturile sunt încărcate fără N+1. `PrivatePhoto` este comun cu Bucătărie. Regulile noi de layout și UX/DX sunt în `AGENTS.md`.
- **Verificări:** build TypeScript/Vite, Prettier, Pint, lint PHP, rute și `git diff --check`. Migrațiile restante din pull și AM2, apoi granturile runtime, aplicate local fără reset/seed. În browser: moment cu foto, notă fără foto, acces selectiv în contul destinatarului și retragere (listă goală + bytes refuzați), înlocuire/eliminare foto, arhivare/restaurare, conflict între două editări cu draft păstrat și reîncărcare explicită. Listă/detaliu/formular verificate vizual desktop și 390 px. Nu s-au scris/rulat suite de teste; dependențele și lockfile-urile nu s-au schimbat.
- **Limite:** scenariile neexercitate sunt în fișa AM2, inclusiv eșec forțat după scrierea media, carantină/respingere, rol limitat și cross-tenant. Cleanupul fizic al media desprinse rămâne P2. Timeline-ul, albumele și subiecții fără cont nu sunt implementate; întregul MVP nu este declarat închis.
- **Livrare:** commituri locale `acb1330` (regulile AGENTS) și `4851172` (AM2). Push-ul pe `origin/codex/documente-design` a fost respins de verificarea automată de aprobare: lipsește acordul explicit pentru trimiterea codului/documentației către GitHub. Este necesară confirmarea utilizatorului pentru publicare. O2a rămâne pregătire Linux, fără deploy. Secretele, datele demo locale și capturile nu intră în commit.
- **Blocaje:** numai publicarea remote, până la autorizarea explicită a push-ului către `https://github.com/razvanstav/Family.git`. Demonstrația locală AM2 funcționează. În alt mediu aplică migrațiile AM1/AM2 și `ops/grant-runtime.sql` înaintea buildului nou cu rolul runtime.

## Prima acțiune la reluare

Continuă [Amintiri, pasul AM3](modules/memory.md#următorul-pas): timeline cu momente și preparări prin contractele Activity/Outbox, cu paginare și autorizare la citire. Păstrează prioritatea unui MVP demonstrabil, cererea fără suite de teste și acceptarea restantă AM1/AM2. Nu reveni automat la configurarea serverului și nu începe alt modul.

K4b/P3 este disponibil pentru AM3. Citește numai contractul public necesar din [fișa Bucătărie](modules/kitchen.md#k4b--evenimente-durabile), fără a începe K3b sau a modifica `recipe_versions`; mesele păstrează versiunea exactă. Pentru timeline folosește `Activity`/`Outbox`, cu reconciliere și autorizare canonică.

Păstrează instrucțiunile `AGENTS.md`, Helvetica, paleta caldă și etichetele directe. Ordinea este Acasă → Familia mea → Documente → Bucătărie → Amintiri → Calendar, identică desktop/mobil; Acasă implicită. Referința Bucătărie: `artifacts/kitchen-reference.png`. Pregătirea Linux rămâne în [O2a](../ops/SETUP.md#o2a--configurații-pentru-instalator), fără deploy verificat.

## Mediu local

- Checkout curent pe Windows, actualizat prin pull la `993f719` înainte de AM2. PHP 8.4.24 disponibil în PATH la `C:\Users\razva\php84\php.exe`; `backend/vendor` și `frontend/node_modules` există. Buildul frontend este generat în `backend/public/app/` și ignorat de Git.
- Aplicația a fost pornită la http://127.0.0.1:8000/app/, previzualizare mobilă la http://127.0.0.1:8000/preview/mobile, PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Fluxurile demo AM2 au folosit `memoria`, fără resetarea datelor; orice viitoare suită PHPUnit trebuie să folosească exclusiv `memoria_test`, niciodată `memoria`.
- Pornire `.\ops\start-local.ps1` (rezolvă PHP din PATH), oprire `.\ops\stop-local.ps1`. Procese ascunse; `calendar:deliver-reminders --watch` și `outbox:deliver --watch`, la 15 s. Loguri outbox `.runtime/outbox.out.log` / `.runtime/outbox.err.log`. După schimbarea handlerilor se repornește workerul outbox. Starea proceselor se reverifică la reluare.
- Operații țintite: `php -d extension=pdo_pgsql backend/artisan outbox:deliver --family=<UUID>`; `--replay=kitchen.activity.v1` reprogramează handlerul pentru familia explicită. `kitchen:rebuild-activity --family=<UUID>` reconstruiește din sursele canonice fără schimbarea lor. Vezi [SETUP](../ops/SETUP.md).
- `--limit` este maximum per familie/per trecere, nu promisiunea golirii tuturor evenimentelor într-o singură comandă. Replay/rebuild nu trimit notificări externe și nu creează surse duplicate.
- Secretele rămân în fișierele locale ignorate; nu afișa `.env` ori credențiale. La `psql -c` pe Windows folosește SQL ASCII cu escape Unicode ori fișiere UTF-8.
