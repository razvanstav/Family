# Calendar

Stare: C1 implementat și verificat local la 21 octombrie 2026. C2–C4 rămân planificate. Noua ordine este C1 → C3 in-app pentru evenimente manuale → dashboard; sursele derivate și emailul vin separat. Etapa M07 completă cere în continuare contractele de scadență, outbox și transportul de email/notificări.

## Scop

Taskuri și evenimente manuale, scadențe derivate, responsabili/participanți și remindere in-app/email. Calendar deține planificarea și ocurențele reminderelor; modulul sursă deține data unei obligații derivate.

## Pași

- [x] **C1 — Evenimente manuale.** Calendar/listă, creare, detaliu, editare, arhivare/restaurare cu versiune; acces privat/familie și date cu/fără oră. Verificat mai jos.
- [ ] **C2 — Scadențe derivate.** Consumă snapshoturi versionate ale surselor. Editarea sursei actualizează proiecția, iar arhivarea/dezactivarea o oprește; o proiecție nu poate modifica direct data sursei. Probează un document cu expirare proprie.
- [ ] **C3 — Remindere.** Adaugă destinatari autorizați, offset, in-app/email, anulare, retry și stări pentru livrare incertă. Jobul reverifică sursa, destinatarul, grantul și modulul înaintea trimiterii. Nu promite email exact o singură dată când furnizorul nu confirmă rezultatul.
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
- Nu sunt implementate încă taskuri, participanți individuali, acces selectiv, recurențe, intervale de mai multe zile, surse derivate sau remindere. Schema C1 este contractul restrâns de mai sus, nu întreaga schemă viitoare din blueprint.

## Verificare C1 — 21 octombrie 2026

- Migrarea `2026_10_21_115209_create_calendar_items_table.php` este aplicată în `memoria` și `memoria_test`; permisiunile runtime au fost acordate. Nu s-a resetat baza aplicației.
- `CalendarEventTest.php`: 9 teste / 80 aserțiuni trecute pentru CRUD/arhivare/restaurare, vară/iarnă, ambele tranziții DST, validări, acces privat/familie, context RLS, membru limitat/revocat și versiuni concurente. După adăugarea respingerii lunilor `0000-01` și `2101-01`, s-a reluat exclusiv testul de validare afectat: 1 test / 12 aserțiuni trecute.
- `frontend/tests/calendar.test.mjs`: 3 teste noi trecute pentru ziua curentă în București, început de săptămână/an bisect, schimbarea lunii peste an și stabilitatea datelor fără oră la DST. Build TypeScript/Vite și Pint trecute.
- Browser local: creare cu acces familie și toată ziua; editare la 22 octombrie, ora 11:00; arhivare, listă arhivată, restaurare; două ferestre cu versiuni concurente → 412 și reîncărcarea versiunii noi. Navigare între luni, filtrare pe zi și Popescu → Ionescu → Popescu verificate.
- Desktop 1366 × 900: calendar și agendă în două coloane. Mobil 360 × 800: calendar deasupra agendei, formular ca foaie, fără overflow; după corecție, zilele au 45 × 44 px și butonul de adăugare are iconiță cu nume accesibil. Captura `.runtime/c1-calendar-desktop.jpg` include evenimentul fictiv, eliminat ulterior împreună cu auditul său (zero înregistrări rămase pentru acel ID).
- Nu s-au reluat suitele F/D/A. Nu s-au verificat dispozitive fizice, alte fusuri orare ori livrarea notificărilor; C4 rămâne deschis pentru reconciliere/joburi. Ramura de eroare de rețea a calendarului nu a fost provocată separat în browser.

## Surse și căi

Consultă operațiile `Calendar_`, `CalendarItem_`, contractele de deadline/Notifications și scenariile de date canonice din pachetul v1.2.

Implementarea C1 este în `backend/app/Modules/Calendar/Http/CalendarController.php`, migrarea de mai sus, `backend/routes/web.php` și `frontend/src/modules/calendar/` (`Calendar.tsx`, `calendarDates.ts`, `calendar.css`). Navigația este în `frontend/src/app/App.tsx`. Transportul comun viitor rămâne în Platformă.

Sincronizarea bidirecțională Google/Apple, recurențele arbitrare și pushul nativ nu intră în lansarea web inițială. Aceste remindere sunt funcții viitoare ale aplicației, separate de evidența de lucru a asistentului din STATE.
