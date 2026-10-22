# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Bucătărie, **K4a — acces și arhivare a rețetelor — implementat și verificat local**. Contractele și probele sunt în [fișa Bucătărie](modules/kitchen.md#k4a--acces-și-arhivare).
- **Prioritatea utilizatorului:** după K2, a ales K4 — acces și arhivare. K4a livrează acces privat/familie/persoane alese, retragere/revocare, arhivare/restaurare. Outboxul și evenimentele sunt separate în K4b, fără a începe P3 sau alt modul.
- **Rezultat:** politica comună autorizează lista, istoricul, fotografiile și sursa din mese. Arhivarea retrage partajarea; autorul poate citi arhiva, istoricul și fotografiile. Mesele/voturile rămân pe versiunea folosită, fără sursa arhivată. Restaurarea folosește „Doar eu”. `revision` protejează concurența între editare/acces/arhivare; `version` rămâne numărul versiunii de conținut. Rândurile istorice nu se modifică.
- **Verificări automate:** 9 teste noi K4a. Rularea finală Kitchen + FamilyMembership + FamilyInvitation + CalendarReminder: **80 teste / 999 aserțiuni**, toate trecute pe `memoria_test`. Cele **20 de teste frontend**, buildul TypeScript/Vite și Pint au trecut. Suitele fără contract modificat și ClamAV live nu s-au reluat.
- **Verificări UI:** Mihai/Popescu a creat rețeta de probă, a partajat-o către Ana, a arhivat-o și a restaurat-o cu acces privat. Două ferestre au demonstrat conflictul de acces, păstrarea alegerii nesalvate și reîncărcarea explicită. Desktop **1376 × 1000**, mobil **360 × 800**, build real în iframe-uri locale temporare, fără overflow și cu controale minimum 44 px. Focus inițial în dialog și Escape spre detaliu observate. Capturi ignorate `.runtime/k4-access-desktop.png`, `.runtime/k4-access-mobile.png`, `.runtime/k4-conflict-mobile.png`.
- **Curățare:** proba `f42b4fe4-db5a-4324-b644-ebbfd94fa4b5`, o versiune, un grant și cinci evenimente audit eliminate strict după ID, autor și titlu. DB confirmă zero resurse ale probei. Fără mese/fotografii UI noi, resetări de identități, apartenențe, parole ori alte date. Ferestrele și paginile auxiliare eliminate.
- **Limite:** rolurile, revocarea, RLS/FK, istoricul/fotografiile și accesul sursei din mese sunt verificate prin API; nu toate repetate în browser. Erorile reale de rețea/închiderea în timpul scrierii, fiecare ciclu de Tab, dispozitivele fizice și producția nu sunt verificate. Outbox/timeline, K3b, editarea/arhivarea meselor și planificarea/cumpărăturile rămân nelivrate.
- **Livrare:** commitul K4a include implementarea, migrația, testele, documentația și acest punct de reluare, la cererea utilizatorului. Ramură `codex/documente-design`, remote `origin` (`razvanstav/Family`); sincronizarea se verifică din Git. Datele locale, secretele, capturile și runtime-ul rămân ignorate.
- **Blocaje:** niciunul pentru K4a.

## Prima acțiune la reluare

K4a este încheiat. Citește [fișa Bucătărie](modules/kitchen.md), secțiunea „Următorul pas”, și cererea nouă a utilizatorului înainte de a alege K4b sau K3b. Nu începe automat alt pas/modul. K4b cere infrastructura durabilă P3; K3b are scopul de stabilit. Nu modifica rândurile recipe_versions; mesele păstrează versiunea exactă.

Păstrează instrucțiunile `AGENTS.md`, Helvetica, paleta caldă și etichetele directe. Ordinea rămâne Acasă → Familia mea → Documente → Bucătărie → Calendar, identică desktop/mobil; Acasă implicită. Referința Bucătărie: `artifacts/kitchen-reference.png`.

## Mediu local

- Aplicație: http://127.0.0.1:8000/app/, sesiune demo Mihai, familia Popescu. Aplicația și workerul de remindere sunt pornite. PostgreSQL `127.0.0.1:55432`; ClamAV `127.0.0.1:3310`.
- În această instalare PHP este `.tools/php84/php.exe` (8.4.26); vechea cale `C:/Users/razva/php84/php.exe` nu există. Composer ignorat `.tools/composer.phar`. Laravel instalat 13.38.0, Pint 1.32.1; Vite 6.4.4. PHPUnit folosește exclusiv `memoria_test`, niciodată `memoria`.
- Pornire `.\ops\start-local.ps1 -PhpPath "$PWD/.tools/php84/php.exe"`, oprire `.\ops\stop-local.ps1`. Procese ascunse; worker `calendar:deliver-reminders --watch`, la 15 s. După schimbări ale livrării se repornește workerul. Nu s-a schimbat livrarea în K4a.
- După pull au fost aplicate aditiv migrațiile lipsă Calendar/Kitchen și K4a pe ambele baze, plus `ops/grant-runtime.sql`. Nu s-a resetat baza aplicației și nu s-au creat automat exemple noi de rețete. Vechea descriere a datelor demo de pe altă instalare nu este probă pentru baza locală actuală.
- Secretele rămân în fișierele locale ignorate; nu afișa `.env` ori credențiale. La `psql -c` pe Windows folosește SQL ASCII cu escape Unicode ori fișiere UTF-8.
