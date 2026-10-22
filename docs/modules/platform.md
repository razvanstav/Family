# Platformă

Stare: izolare pe familie, stocare privată locală, scanare ClamAV și audit minimal există. **P3 este implementat și verificat local pentru K4b**, la 22 octombrie 2026: outbox tranzacțional, livrare per handler și primul consumator Kitchen. Restul M04 rămâne separat.

## Fișiere existente

- `backend/app/Platform/Contracts/PrivateMedia.php`.
- `backend/app/Platform/Media/LocalPrivateMedia.php`, `Scanner.php`, `ClamAvScanner.php`.
- `backend/app/Platform/Families/TenantContext.php`, `backend/app/Platform/Audit/Audit.php`.
- `backend/database/migrations/2026_10_20_000001_create_document_vertical.php`, `2026_10_20_215355_create_family_invitations_table.php`.
- RLS, FK, audit și rollback: `backend/tests/Feature/DocumentSecurityTest.php`.

## Pași

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

- Contractele sunt `Platform/Contracts/Outbox.php`, `StoredEvent.php`, `EventHandler.php`; implementarea este `Platform/Events/DatabaseOutbox.php`. Composition root înregistrează explicit `kitchen.activity.v1`. Nu există descoperire de pluginuri, broker nou sau dependențe noi.
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

## Referințe și limite

Pentru pasul activ consultă numai interfețele relevante din `Memoria_Familiei_Review_v1.2/contracts.reference.php`, structura din `monorepo.txt` și scenariul necesar din `acceptance-scenarios.feature`. Aceste contracte sunt propuneri de adaptat codului existent, nu implementări deja prezente.

Nu adăuga un motor generic de permisiuni, încărcător de pluginuri sau Repository pentru fiecare model. Interfața unui modul este contractul public; integrările folosesc contracte, fără acces la persistența altui modul.
