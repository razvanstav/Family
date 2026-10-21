# Calendar

Stare: C1 implementat și verificat local la 21 octombrie 2026. C3 in-app pentru evenimente manuale este implementat; verificările sunt mai jos. C2, emailul din C3 și reconcilierea extinsă C4 rămân planificate. Ordinea cerută este C1 → C3 in-app → dashboard; sursele derivate și emailul vin separat. Etapa M07 completă cere în continuare contractele de scadență, outbox generic și transportul de email.

## Scop

Taskuri și evenimente manuale, scadențe derivate, responsabili/participanți și remindere in-app/email. Calendar deține planificarea și ocurențele reminderelor; modulul sursă deține data unei obligații derivate.

## Pași

- [x] **C1 — Evenimente manuale.** Calendar/listă, creare, detaliu, editare, arhivare/restaurare cu versiune; acces privat/familie și date cu/fără oră. Verificat mai jos.
- [ ] **C2 — Scadențe derivate.** Consumă snapshoturi versionate ale surselor. Editarea sursei actualizează proiecția, iar arhivarea/dezactivarea o oprește; o proiecție nu poate modifica direct data sursei. Probează un document cu expirare proprie.
- [ ] **C3 — Remindere.** Partea in-app pentru evenimente manuale este implementată: destinatari autorizați, offset, anulare, replanificare și retry prin intenții durabile. Emailul și stările de livrare externă incertă rămân planificate. Jobul reverifică sursa și destinatarii înaintea livrării; calendarul manual nu are încă activare/dezactivare de modul sau granturi selective. Nu promite email exact o singură dată când furnizorul nu confirmă rezultatul.
- [ ] **C4 — Verificări de timp și reconciliere.** Testează Europe/Bucharest la ambele schimbări de oră, date fără oră, editare după planificare, anulare, dublu job și eveniment vechi. Reconciliază joburile întârziate și proiecțiile după crash.

## Acceptare pentru pașii viitori

- O scadență are o singură sursă editabilă; schimbarea ei nu lasă reminderul vechi activ.
- Reminderele către un membru revocat sunt anulate; notificarea nu dezvăluie date la care destinatarul nu mai are acces.
- DST nu produce omisiuni sau dublări necontrolate; ceasul este controlabil în teste.
- Probele document → Calendar → email local includ modificare și anulare. Pets/Vehicles repetă ulterior fluxul pe sursele lor reale.

## Contract C1 implementat

- `calendar_items` conține evenimente manuale: `title` (160), `notes` (2000, opțional), `event_date`, `all_day`, `starts_at` UTC când există oră, `timezone`, `owner_id`, `visibility`, `version`, `archived_at` și familia. Intervalul acceptat este 1900–2100. Datele fără oră rămân date calendaristice, fără conversie UTC.
- Fusul este momentan Europe/Bucharest. Ora introdusă este validată prin conversie dus-întors; orele inexistente primăvara și cele ambigue toamna sunt respinse cu explicație. Alegerea explicită a unuia dintre cele două momente din ora repetată nu este încă disponibilă.
- Acces implicit privat; opțiunea „Familia mea” permite citirea de către membrii activi. Numai creatorul poate modifica/arhiva/restaura, dacă rolul său nu este `limited`. Rolul limitat poate citi evenimentele familiei, fără creare sau modificare. Se reutilizează `TenantContext`, cu RLS pe noua tabelă.
- Rutele autentificate sunt sub `/api/v1/families/{family}/calendar-items`: GET listă cu `month=YYYY-MM` și `archived=0|1`, POST creare, GET/PATCH `/{event}`, POST `/{event}/archive` și `/{event}/restore`. PATCH și acțiunile cer `If-Match`; lipsa versiunii dă 428, conflictul 412. Arhivarea este reversibilă; un eveniment arhivat nu se editează înainte de restaurare.
- Calendarul lunar începe lunea; lista poate arăta luna sau ziua selectată. Schimbarea familiei remontează modulul. Interfața include stări de încărcare, listă goală, eroare/reîncercare și reîncărcare după conflict.
- Nu sunt implementate încă taskuri, participanți individuali, acces selectiv, recurențe, intervale de mai multe zile sau surse derivate. Reminderele in-app sunt extensia C3 descrisă mai jos. Schema existentă nu reprezintă întreaga schemă viitoare din blueprint.

## Verificare C1 — 21 octombrie 2026

- Migrarea `2026_10_21_115209_create_calendar_items_table.php` este aplicată în `memoria` și `memoria_test`; permisiunile runtime au fost acordate. Nu s-a resetat baza aplicației.
- `CalendarEventTest.php`: 9 teste / 80 aserțiuni trecute pentru CRUD/arhivare/restaurare, vară/iarnă, ambele tranziții DST, validări, acces privat/familie, context RLS, membru limitat/revocat și versiuni concurente. După adăugarea respingerii lunilor `0000-01` și `2101-01`, s-a reluat exclusiv testul de validare afectat: 1 test / 12 aserțiuni trecute.
- `frontend/tests/calendar.test.mjs`: 3 teste noi trecute pentru ziua curentă în București, început de săptămână/an bisect, schimbarea lunii peste an și stabilitatea datelor fără oră la DST. Build TypeScript/Vite și Pint trecute.
- Browser local: creare cu acces familie și toată ziua; editare la 22 octombrie, ora 11:00; arhivare, listă arhivată, restaurare; două ferestre cu versiuni concurente → 412 și reîncărcarea versiunii noi. Navigare între luni, filtrare pe zi și Popescu → Ionescu → Popescu verificate.
- Desktop 1366 × 900: calendar și agendă în două coloane. Mobil 360 × 800: calendar deasupra agendei, formular ca foaie, fără overflow; după corecție, zilele au 45 × 44 px și butonul de adăugare are iconiță cu nume accesibil. Captura `.runtime/c1-calendar-desktop.jpg` include evenimentul fictiv, eliminat ulterior împreună cu auditul său (zero înregistrări rămase pentru acel ID).
- Nu s-au reluat suitele F/D/A. Nu s-au verificat dispozitive fizice, alte fusuri orare ori livrarea notificărilor; C4 rămâne deschis pentru reconciliere/joburi. Ramura de eroare de rețea a calendarului nu a fost provocată separat în browser.

## Curățare C1 — 21 octombrie 2026

- Backend: `owner_name` vine prin `JOIN users` în query-ul de bază (fără interogare per eveniment); `starts_at` este parsat o singură dată; regula redundantă `date_format:Y-m` a fost eliminată, regexul acoperă formatul și intervalul.
- Frontend: fereastra evenimentului se deschide direct din elementul deja încărcat în listă; `GET /{event}` se face doar la „Reîncarcă evenimentul” după conflict sau când elementul nu este în listă. Conflictele rămân detectate prin `If-Match`.
- Fusul orar nu mai apare ca identificator tehnic; câmpul `Ora` din formular are o singură mențiune, „Ora României”. Lista și detaliul nu îl mai repetă.
- Verificat pe acest calculator: `CalendarEventTest.php` 9 teste / 82 aserțiuni, 20 teste frontend, build TypeScript/Vite, Pint și `git diff --check`. Tabela `calendar_items` lipsea local; migrarea și `grant-runtime.sql` au fost aplicate în `memoria` și `memoria_test`, fără resetare de date. Nu a fost făcută verificare vizuală în browser după curățare.

## Contract C3 in-app — 21 octombrie 2026

- Autorul configurează opțional un singur moment de reminder și unul sau mai mulți destinatari activi din aceeași familie. La acces privat se poate alege doar autorul; la acces familie sunt eligibili și membrii cu rol limitat. Configurarea rămâne a autorului și este inclusă în payloadul listei, cu versiune și stările livrărilor; deschiderea editorului din listă nu cere încă un GET de detaliu.
- Evenimente cu oră: 0/15/60/1440 minute înaintea momentului UTC canonic. Fără oră: 09:00 în România în ziua evenimentului sau în ziua calendaristică precedentă. O zi calendaristică nu este redusă la 24 de ore în jurul DST. Dacă momentul a trecut la configurare/replanificare, ocurența este `expired`, fără trimitere retroactivă.
- `calendar_items.reminder_offset_minutes`, `calendar_reminder_recipients` și `calendar_reminder_deliveries` sunt deținute de Calendar. Migrarea `2026_10_21_150743_create_calendar_reminder_deliveries.php` adaugă FK compuse, RLS și unicitate pe eveniment/versiune/destinatar. Intenția durabilă și modificarea evenimentului sunt în aceeași tranzacție; validarea respinsă nu lasă evenimente ori setări parțiale.
- Livrarea in-app este tranziția atomică `pending` → `delivered` a aceleiași ocurențe; nu există un apel extern cu rezultat incert. Workerul blochează familia, setează contextul RLS și reverifică versiunea/sursa activă, apartenența autorului și a destinatarului și accesul actual. După rollback, intenția rămâne disponibilă pentru retry; o a doua executare nu o dublează. Alte stări: `cancelled`, `expired`.
- Editarea și arhivarea anulează ocurențele vechi, inclusiv notificările deja livrate; restaurarea replanifică pentru versiunea nouă, cu `expired` pentru momentele deja trecute. Evenimentul public `MembershipRevoked` anulează livrările către membrul revocat și cele ale evenimentelor sale, în aceeași tranzacție cu revocarea. Readmiterea nu reactivează ocurențele anulate. Listele de notificări verifică accesul și versiunea actuală la fiecare request; nu păstrează copii ale titlurilor/notițelor private.
- Un worker întârziat livrează cât timp evenimentul cu oră nu a depășit începutul cu mai mult de un minut, respectiv până la miezul nopții local următor pentru evenimente fără oră. După aceste limite marchează `expired`. `calendar:deliver-reminders` execută o verificare (maximum 100 de ocurențe scadente pe familie); `--watch` reia la 15 secunde și reîncearcă după erori. `ops/start-local.ps1` îl pornește ascuns, `ops/stop-local.ps1` îl oprește. Nu folosește automatizări Codex.
- Notificările sunt accesibile din antet, cu indicator real de necitite, stări citit/necitit, încărcare, eroare/reîncărcare, listă goală și paginare de 30. Actualizare automată la 30 secunde cât aplicația este vizibilă și fereastra de notificări este închisă; în fereastră există „Reîncarcă”. Salvarea/arhivarea/restaurarea unui eveniment invalidează și reîncarcă notificările și indicatorul din antet. Deschiderea unei notificări, inclusiv a uneia deja citite, reverifică accesul, marchează citirea și deschide evenimentul din Calendar.
- API: GET `/api/v1/families/{family}/calendar-notifications?page=1`; PATCH `/{notification}` cu `read: true|false`. Configurarea se salvează pe rutele C1 cu `reminder_offset_minutes: null|0|15|60|1440` și `reminder_recipients: UUID[]`; PATCH păstrează setările dacă aceste câmpuri lipsesc. Limită: 100 de destinatari. Fără email, push nativ, preferințe globale ori transport generic pentru alte module.

## Verificare C3 in-app — 21 octombrie 2026

- Migrarea și permisiunile runtime sunt aplicate în `memoria` și `memoria_test`, fără resetarea bazei aplicației.
- `CalendarReminderTest.php`: ultima rulare, după corecția serializării UTC, **17 teste / 150 aserțiuni** trecute. Include păstrarea momentelor UTC în coloanele `timestamptz`, în sesiune PostgreSQL Europe/Bucharest, livrarea cu o secundă înainte și 15 secunde după scadență, plus momentele citirii. `CalendarEventTest.php`: **9 teste / 82 aserțiuni** trecute în rularea anterioară, după integrarea tranzacțiilor C3; nu s-a repetat după corecția exclusivă a timestampurilor reminderelor.
- Acoperire nouă: momentul notificării și DST la ambele tranziții, destinatari neautorizați/duplicați și rollback la validare, schimbarea accesului, editare/arhivare/restaurare și versiuni vechi, anularea la revocare/readmitere, acces după revocarea autorului, rollback și retry, job repetat, evenimente vechi, dezactivare, citit/necitit și izolare între familii/destinatari. Nu s-a simulat căderea procesului OS și nu s-au pornit doi workeri simultan în teste; C4 păstrează reconcilierea extinsă.
- Build TypeScript/Vite (inclusiv sincronizarea după mutații), 20 teste frontend existente, Pint și `git diff --check` trecute.
- Browser desktop 1376 × 1244 și mobil 360 × 800: configurare, destinatari activi (familie → privat restrânge selecția), lipsa overflowului în formular și notificări; livrare reală la 18:30:14 pentru evenimentul de la 18:30, citit/necitit, Documente → notificare → eveniment Calendar cu starea „Livrat”. După ultima ajustare s-a restaurat/replanificat proba la 18:35, s-a observat indicatorul „1 necitită”, apoi arhivarea a eliminat notificarea deja încărcată și indicatorul fără reîncărcare manuală. Popescu → Ionescu → Popescu păstrează Calendar și remontează notificările.
- Capturi ignorate de Git: `.runtime/c3-reminder-mobile.png`, `.runtime/c3-notifications-mobile.png`, `.runtime/c3-notifications-desktop.png`. Conțin exclusiv proba fictivă înainte de curățare. Evenimentul `2b57e3ee-0bc0-4c15-9687-949065f69590`, ocurențele, destinatarii săi și cele șase intrări de audit au fost eliminate după probe; verificare DB: zero rânduri pentru acel ID în toate cele patru tabele. Datele existente ale aplicației nu au fost resetate.
- Nu s-au reluat suitele F/D/A backend, nu s-a verificat pe dispozitive fizice și nu s-au provocat separat în browser erorile de rețea sau paginarea cu peste 30 de notificări. Emailul și reconcilierea C4 nu sunt declarate verificate.

## Surse C3

`backend/app/Modules/Calendar/Application/CalendarReminders.php`, `Http/CalendarNotificationController.php`, `backend/app/Console/Commands/DeliverCalendarReminders.php`, evenimentul `backend/app/Platform/Families/MembershipRevoked.php` și înregistrarea sa în `AppServiceProvider`. Interfața este în `ReminderFields.tsx`, `CalendarNotifications.tsx`, `notifications.css` și extensiile `Calendar.tsx`/`calendarTypes.ts` (redenumit la H1). Calendar deține intențiile și notificările acestei livrări restrânse; asta nu închide infrastructura generică P3/P4.

## Referințe

Consultă operațiile `Calendar_`, `CalendarItem_`, contractele de deadline/Notifications și scenariile de date canonice din pachetul v1.2.

Implementarea C1 este în `backend/app/Modules/Calendar/Http/CalendarController.php`, migrarea de mai sus, `backend/routes/web.php` și `frontend/src/modules/calendar/` (`Calendar.tsx`, `calendarTypes.ts`, `calendar.css`). După H1, politica comună este în `Application/CalendarAccess.php`, iar datele/calendarul lunar sunt în `frontend/src/shared/calendarDates.ts` și `CalendarMonth.tsx`, folosite și de Acasă. Navigația este în `frontend/src/app/App.tsx`. Transportul comun viitor rămâne în Platformă.

Sincronizarea bidirecțională Google/Apple, recurențele arbitrare și pushul nativ nu intră în lansarea web inițială. Aceste remindere sunt funcții viitoare ale aplicației, separate de evidența de lucru a asistentului din STATE.
