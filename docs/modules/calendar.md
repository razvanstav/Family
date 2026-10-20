# Calendar

Stare: planificat, fără implementare de produs. Etapa M07 cere contractele de scadență, outbox și transportul de email/notificări.

## Scop

Taskuri și evenimente manuale, scadențe derivate, responsabili/participanți și remindere in-app/email. Calendar deține planificarea și ocurențele reminderelor; modulul sursă deține data unei obligații derivate.

## Pași

- [ ] **C1 — Evenimente manuale.** Definește contractul, accesul, data fără oră versus momentul cu oră și fusul orar. Livrează listă, creare, editare și arhivare cu controlul versiunii.
- [ ] **C2 — Scadențe derivate.** Consumă snapshoturi versionate ale surselor. Editarea sursei actualizează proiecția, iar arhivarea/dezactivarea o oprește; o proiecție nu poate modifica direct data sursei. Probează un document cu expirare proprie.
- [ ] **C3 — Remindere.** Adaugă destinatari autorizați, offset, in-app/email, anulare, retry și stări pentru livrare incertă. Jobul reverifică sursa, destinatarul, grantul și modulul înaintea trimiterii. Nu promite email exact o singură dată când furnizorul nu confirmă rezultatul.
- [ ] **C4 — Verificări de timp și reconciliere.** Testează Europe/Bucharest la ambele schimbări de oră, date fără oră, editare după planificare, anulare, dublu job și eveniment vechi. Reconciliază joburile întârziate și proiecțiile după crash.

## Acceptare

- O scadență are o singură sursă editabilă; schimbarea ei nu lasă reminderul vechi activ.
- Reminderele către un membru revocat sunt anulate; notificarea nu dezvăluie date la care destinatarul nu mai are acces.
- DST nu produce omisiuni sau dublări necontrolate; ceasul este controlabil în teste.
- Probele document → Calendar → email local includ modificare și anulare. Pets/Vehicles repetă ulterior fluxul pe sursele lor reale.

## Surse și căi viitoare

Consultă operațiile `Calendar_`, `CalendarItem_`, contractele de deadline/Notifications și scenariile de date canonice din pachetul v1.2.

Implementarea va sta în `backend/app/Modules/Calendar/` și `frontend/src/modules/calendar/`. Transportul comun rămâne în Platformă.

Sincronizarea bidirecțională Google/Apple, recurențele arbitrare și pushul nativ nu intră în lansarea web inițială. Aceste remindere sunt funcții viitoare ale aplicației, separate de evidența de lucru a asistentului din STATE.
