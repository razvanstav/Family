# Platformă

Stare: izolare pe familie, stocare privată locală, scanare ClamAV și audit minimal există. **P3 este implementat și verificat local pentru K4b**, la 22 octombrie 2026. **P1a este implementat și verificat local la 24 octombrie:** compoziție UI, contracte backend și gărzi de arhitectură pentru cuplările existente. Registrul de subiecți, activarea modulelor și restul P1/P4 nu sunt închise de P1a.

## Fișiere existente

- `backend/app/Platform/Contracts/PrivateMedia.php`.
- `backend/app/Platform/Media/LocalPrivateMedia.php`, `Scanner.php`, `ClamAvScanner.php`.
- `backend/app/Platform/Families/TenantContext.php`, `backend/app/Platform/Audit/Audit.php`.
- `backend/database/migrations/2026_10_20_000001_create_document_vertical.php`, `2026_10_20_215355_create_family_invitations_table.php`.
- RLS, FK, audit și rollback: `backend/tests/Feature/DocumentSecurityTest.php`.

## Pași

- [x] **P1a — Eliminarea cuplărilor existente (24 octombrie 2026).** Cerere explicită: păstrarea funcționalității, teste și actualizare MD după fiecare pas. Inventar rezolvat: UI Amintiri → `MealViewer`/`mealTypes`, Familie → `Documents`; backend Home/Auto/profil → implementări concrete Calendar/Documente/Amintiri; Familia → granturi Documente; Calendar → sursa concretă a aniversărilor. P1a.1–P1a.4 și subpasul P1a.3b sunt verificate mai jos. Integrările funcționale rămân prin compoziție/contracte publice; fără redesign, migrații, date noi în baza aplicației sau dependențe de pachete.

- [ ] **P1 — Limite și contracte.** Definește explicit proprietarul fiecărui tabel/resursă, contractele publice și registrul minim de subiecți per familie. User, membru al familiei și subiect sunt identități distincte. Subiectul nu conține datele de domeniu și nu acordă acces. Verifică FK compuse, RLS cu rolul runtime și înregistrarea explicită a modulelor.
- [ ] **P2 — Media și execuție în fundal.** Mută scanarea în worker cu stări explicite, rezervări atomice de cotă, retry și cleanup după crash. Media se revendică atomic de o singură resursă; downloadul îi folosește politica actuală. Adaugă storage privat înlocuibil și verifică DB+bytes, fără a expune obiecte publice. Închide fiecare subflux înainte de următorul.
- [x] **P3 — Evenimente durabile.** Outbox în tranzacția sursei, receipturi per handler, versiunea sursei, retry/replay și idempotency implementate pentru K4b. Evenimentele rămase după commit sunt recuperate de procese noi; eroarea dintre proiecție și receipt retrage ambele efecte. Duplicate, ordine inversată, doi workeri și reconstruirea sunt verificate. Handlerii curenți au exclusiv efecte DB; transporturile externe nu sunt livrate de P3.
- [ ] **P4 — Providere și transport.** Adaugă contracte restrânse pentru resurse autorizate, căutare, timeline și scadențe, pe măsură ce au consumatori. Notifications transportă mesaje și gestionează retry/preferințe; Calendar calculează reminderele. Jobul are context de familie explicit și reverifică accesul și activarea modulului înainte de livrare.

## Acceptare

- Rolul runtime nu deține tabelele, nu are superuser/BYPASSRLS; contextul tranzacțional nu se scurge între familii. Bootstrapul de identitate rămâne filtrat explicit de aplicație.
- O legătură între module sau un ID de media nu permite extinderea accesului; politica resursei canonice este obligatorie și pentru joburi.
- Rollback, crash și retry nu produc fișiere accesibile neautorizat, depășiri de cotă sau proiecții duplicate.
- Evenimentele/auditul au identificatori și date minimale; fără conținut privat, parole, tokenuri sau identificatori auto sensibili în loguri.

## P3 — contract și execuție

- Contractele sunt `Platform/Contracts/Outbox.php`, `StoredEvent.php`, `EventHandler.php`; implementarea este `Platform/Events/DatabaseOutbox.php`. Composition root înregistrează explicit `kitchen.activity.v1` și, din [AM3](memory.md#am3--cronologie), `memory.timeline.v1`. Al doilea consumă evenimente Memory și Meal, cu receipts independente și aceeași reconciliere canonică; nu sunt descoperire de pluginuri, broker nou sau dependențe noi.
- `append(familyId, name, sourceKind, sourceId, sourceVersion)` cere tranzacția tenantului deschisă. Persistă numai UUID-uri, numele/tipul evenimentului, schema 1, versiunea sursei și momentul UTC; fără conținut sau destinatari. Același triplet familie/sursă/versiune refolosește evenimentul existent. Triggerul `outbox_events_immutable` respinge UPDATE asupra faptelor salvate.
- Migrația `2026_10_22_095156_create_durable_kitchen_events.php` creează `outbox_events` și `outbox_deliveries`, cu RLS forțat și FK compus familie/eveniment. Delivery are cheie eveniment + handler; `delivered_at` este receiptul. La publicare se creează intențiile handlerilor în aceeași tranzacție cu sursa și auditul.
- `deliverFamily` începe în afara altei tranzacții. Fiecare tranzacție blochează familia, setează contextul local PostgreSQL, apoi blochează receiptul. Handlerul citește sursa canonică și scrie proiecția pe aceeași conexiune; proiecția și confirmarea se comit împreună. Un al doilea worker reverifică receiptul. La întreruperea tranzacției nu rămâne un claim permanent; PostgreSQL retrage efectele necomise și eliberează lockurile.
- Eșecul păstrează evenimentul și receiptul neconfirmat. O tranzacție separată crește `attempts`, salvează numai codul `handler_failed` și amână retry cu 30 s, 60 s etc., până la o oră. Nu se salvează mesajele excepțiilor în receipt/logul comenzii. O schemă necunoscută nu este confirmată tacit. Handlerii independenți au progres și retry separate.
- `outbox:deliver` procesează implicit maximum 100 de livrări per familie/per trecere; `--limit=1..1000`, `--family=<UUID>` și `--watch` la 15 s sunt disponibile. Fără `--family`, familiile sunt enumerate în loturi de 100 înainte de contextul tenantului. `--replay=<handler>` cere o familie explicită și nu se combină cu watch; reprogramează numai acel handler și creează intențiile lipsă pentru un handler înregistrat ulterior. Replay presupune handleri idempotenți și poate fi reluat în loturi până la epuizare.
- Primul consumator real este indexul Kitchen și cititorul lui autorizat, descrise în [K4b](kitchen.md#k4b--evenimente-durabile). Un eveniment nu acordă acces, iar indexul nu conține titluri/copii ale ACL-ului. P3 nu mută încă reminderele Calendar în outbox și nu implementează notificări externe sau dezactivarea modulelor P4.

### Verificări P3/K4b — 22 octombrie 2026

- `KitchenOutboxTest`: **15 teste / 243 aserțiuni**. Probele includ publicare atomică și rollback al sursei/media/auditului la eșecul delivery-ului, idempotency/guardul tenantului, eroare simulată după scrierea proiecției înainte de receipt, backoff și retry, doi handleri independenți, replay pentru handler nou, mesaje vechi/repetate, arhivare, surse eliminate, schemă necunoscută, RLS/FK/context și triggerul de imutabilitate.
- Două procese PHP reale, pornite pe `memoria_test` după commitul sursei, livrează aceeași înregistrare: un receipt confirmat, o încercare reușită și o referință. Nu s-a provocat un kill OS în mijlocul tranzacției sau o cădere a serverului PostgreSQL; rollbackul dintre efect și receipt a fost provocat prin excepție controlată. Aceste limite nu sunt prezentate drept teste de crash fizic.
- Regresie Kitchen + FamilyMembership + FamilyInvitation + CalendarReminder: **95 teste / 1.242 aserțiuni**, trecute pe `memoria_test`. Cele 15 teste K4b au fost reluate cu succes după legarea cititorului la contractul public `Activity`. Pint, sintaxa scripturilor PowerShell, rutele/ajutorul CLI, legăturile documentației și `git diff --check` au trecut.
- Migrația a fost aplicată pe `memoria_test` și aditiv pe `memoria`, cu granturile runtime. Reconstruirea locală Popescu a găsit 0 surse; livrarea unică a raportat 0 livrate / 0 eșuate. Workerul local outbox pornește ascuns prin scriptul existent. Nu au fost create/resetate identități sau resurse demo în baza aplicației.

## P1a — Eliminarea cuplărilor, 24 octombrie 2026

### P1a.1 — Amintiri UI, verificat la 24 octombrie 2026

`Memories` primește `renderMeal` și `mealTypeLabels`; `App.tsx` compune `MealViewer` și dicționarul din Bucătărie. Cronologia păstrează aceleași etichete, modal, cheie pe masă, callbackuri de închidere/acces și context familie/utilizator. Amintiri nu mai importă Bucătărie. Testele AST verifică importuri statice, de tip, reexporturi și importuri dinamice; gardă inițială pentru Amintiri. **23 teste frontend și build TypeScript/Vite trecute**. Proba comună în browser este consemnată în P1a.4; fără schimbări de layout sau API.

### P1a.2 — Familie UI, verificat la 24 octombrie 2026

`MemberProfile` publică tipul `ProfileDocumentsSlot`; `FamilyPage` transmite funcția `renderDocuments`, iar App compune componenta Documente. Sunt păstrate membrul subiect, dreptul `can_write`, deschiderea din căutare, callbackurile și cheia utilizator/familie/membru. Niciun modul frontend nu mai importă alt modul, iar `shared` nu importă module sau aplicația. Garda AST verifică întregul `src/modules` și `src/shared`, inclusiv importurile CSS. **23 teste frontend și build TypeScript/Vite trecute**. Proba comună în browser este consemnată în P1a.4; fără modificări CSS/API.

### P1a.3 — Contracte backend și ownership, verificat la 24 octombrie 2026

- Contracte noi în modulele proprietare: Documente `DocumentLinks`/`DocumentSummaries`, Amintiri `MomentSummaries`, Calendar `Overview`/`SourceItems`/`Birthdays`. Home, Auto și profilul Familiei consumă aceste interfețe; `AppServiceProvider` leagă implementările. Query-urile pentru asocieri rămân proiecții autorizate și filtrate pe familie, fără N+1 ori cereri HTTP noi. Tranzacțiile, JSON-ul și rutele existente sunt păstrate.
- `FamilyController` emite evenimentul existent `MembershipRevoked`; composition root îl transmite și către Documente, care elimină propriile granturi. Fără SQL direct Familie → tabele Documente. Curățarea este sincronă în aceeași tranzacție tenant, împreună cu celelalte module.
- Teste noi pentru readmitere fără restaurarea granturilor, rollback atomic la eșecul unui consumator și compunerea Home exclusiv cu substituenți ai contractelor. Garda backend scanează tokenuri PHP pentru dependențe private și acces la tabelele altui modul, inclusiv SQL literal; familiile/utilizatorii/media rămân infrastructură comună.
- **85 teste backend: 83 trecute, 883 aserțiuni, 2 omise explicit** pentru integrarea ClamAV reală neactivată. Rulare serială numai pe `memoria_test`: arhitectură/contracte, Home, Auto/VC1, BC1, profil/poveste, documente și roluri. Pint și diff check trecute. Un test Home avea așteptarea veche de 3 documente pentru adult limitat; actualizat la cele 4 surse permise de regula Casă/Amintiri deja existentă în seeder și politica Documente, cu verificarea titlurilor. Nicio schimbare a politicii de produs. Probele UI și verificarea finală MD urmează în P1a.4.

### P1a.3b — Sursa aniversărilor, verificat la 24 octombrie 2026

La verificarea finală, și dependența Calendar → implementarea sursei Familiei a fost inversată: `BirthdaySource extends DeadlineSource` expune ocurențele canonice, `FamilyBirthdaySource` îl implementează, iar `BirthdayCalendar` primește numai interfața. Legătura este în composition root; fără modificarea datelor, calculelor sau accesului. **13 teste / 155 aserțiuni trecute**, serial pe `memoria_test` (BC1, arhitectură, contracte); Pint trecut. Restul regresiei P1a.3 rămâne valabil, fără schimbări în celelalte fluxuri.

### P1a.4 — Încheiere și limite, 24 octombrie 2026

- Browser desktop, aplicația locală pe 8000, Ana/Popescu: Cronologie afișează eticheta Prânz; masă → rețeta folosită → revenire la masă → revenire la Cronologie cu focus restaurat. Votul/comentariul existent rămân vizibile; nu au fost salvate modificări. Profil → Documente afișează numai Personale/Sănătate și detaliul personal cu accesul și acțiunile existente. Căutare comună pornită din Amintiri → Dosarul meu personal deschide direct profilul și detaliul canonic. Capturi ignorate `.runtime/p1a-meal-desktop.png` și `.runtime/p1a-document-desktop.png`.
- Eliminat și prop-ul `userId` devenit inutil din `MemberProfile` și apelant. Buildul final TypeScript/Vite și cele **23 teste frontend** au trecut din nou după curățare; Prettier trecut. Fără CSS, layouturi ori requesturi noi. Documentația a fost actualizată după fiecare pas, cu contractele din fișele afectate, STATE, ROADMAP, index și regula permanentă AGENTS.
- Gărzi: `frontend/tests/moduleBoundaries.test.mjs` rulează prin `npm test`, cu rezolvare TypeScript, importuri/reexporturi/dependențe de tip/dinamice literale și importuri CSS; `backend/tests/Unit/ModuleBoundariesTest.php` rulează în suita PHPUnit și verifică modulele/platforma, contractele și persistența literală. Compoziția din App/Providers/rute/comenzi și infrastructura comună rămân permise. Nu sunt un analizor exhaustiv pentru cod ori SQL construit dinamic; convențiile de ownership trebuie păstrate și pentru modulele viitoare.
- Limite: probe UI desktop, fără dispozitiv fizic sau repetarea layouturilor mobile nemodificate; nu au fost salvate voturi, documente, permisiuni ori profiluri în aplicație. Două teste ClamAV real omise explicit, fără modificări în scanare/media. Testele DB au folosit numai `memoria_test`, serial. Fără migrații, schimbări de schemă, pachete noi, commit/push/deploy.
- Verificarea finală: **81 legături locale și ancore valide în 11 fișe/indexuri modificate**, Prettier și `git diff --check` trecute. HEAD observat `4e7d55c`, ramura `codex/documente-design`; schimbările anterioare VC1/BC1/CD1 sunt păstrate. Profilul Anei → Documente este lăsat deschis, fără dialog.

## Referințe și limite

Pentru pasul activ consultă numai interfețele relevante din `Memoria_Familiei_Review_v1.2/contracts.reference.php`, structura din `monorepo.txt` și scenariul necesar din `acceptance-scenarios.feature`. Aceste contracte sunt propuneri de adaptat codului existent, nu implementări deja prezente.

Nu adăuga un motor generic de permisiuni, încărcător de pluginuri sau Repository pentru fiecare model. Interfața unui modul este contractul public; integrările folosesc contracte, fără acces la persistența altui modul.
