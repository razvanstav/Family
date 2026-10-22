# Amintiri

Stare la 22 octombrie 2026: **AM1 și AM2 implementate**, cu fluxurile principale demonstrate local pe Windows, desktop și mobil. Acceptarea completă rămâne deschisă pentru scenariile neexercitate de mai jos. Se păstrează prioritatea funcțiilor, fără suite de teste, înaintea instalării Linux. AM3 rămâne planificat.

## Scop

Momente și note private sau partajate, fotografii, asocieri cu persoane/subiecți și timeline autorizat. Momentele sunt surse canonice aici; activitățile altor module apar ca proiecții, fără copierea proprietății asupra datelor lor.

## Pași

- [ ] **AM1 — Momente și note.** Implementat; creare de moment/notă, listă, detaliu, menționare și editare demonstrate local, inclusiv conflict și reîncărcare. Acceptarea integrală a rolurilor, filtrelor și paginării nu este închisă. Subiecții care nu sunt membri așteaptă registrul P1, fără identități fictive sau acces implicit.
- [ ] **AM2 — Fotografii și partajare.** Implementat; upload, înlocuire/eliminare, acces selectiv/retragere și arhivare/restaurare demonstrate local. Rollbackul după scrierea fișierului și scenariile de carantină/respingere nu au fost provocate în acest pas.
- [ ] **AM3 — Timeline.** Integrează momentele și preparările din Bucătărie prin providere/outbox, cu paginare și proiecții reconstruibile. Verifică mesaje repetate/vechi, ștergerea sau arhivarea sursei și autorizarea actuală la citire.

## Acceptare

- Un moment și o preparare apar fiecare o singură dată în timeline și trimit la sursa permisă.
- Revocarea ascunde atât cardul, cât și fotografia/bytes; un titlu privat nu rămâne într-o proiecție accesibilă.
- Reconstruirea timeline-ului nu modifică sursele și nu reactivează elemente arhivate.
- Paginarea are ordine stabilă; afișarea datei respectă fusul orar stabilit fără schimbarea momentului canonic.

## Surse și căi viitoare

Consultă operațiile `Memory_` și `Moment_`, providerul de timeline și scenariile de revocare/replay din pachetul `Memoria_Familiei_Review_v1.2`.

Implementarea AM1 este în `backend/app/Modules/Memory/` și `frontend/src/modules/memory/`. Adaptoarele viitoare pentru timeline vor consuma contracte publice, fără interogarea directă a tabelelor Kitchen/Pets/Vehicles.

Albumele sofisticate, montajul video, embeddings și poveștile generate AI rămân în afara MVP.

## AM1 — momente și note

### Comportament și contract

- Un element are tip `moment`/`note`, titlu de maximum 160 de caractere, text de maximum 8.000, dată calendaristică 1900–2100 și acces `private`/`family`. Textul este obligatoriu pentru note; un moment poate avea numai titlu și dată. Data implicită din formular este ziua curentă în România, iar data salvată nu se deplasează după fusul orar al cititorului.
- Proprietarii/adulții pot crea; numai autorul cu rol activ de proprietar/adult poate modifica. Membrii cu acces limitat pot citi amintirile autorizate. Proprietarul familiei nu primește acces la amintirile private ale altui membru.
- `MomentAccess` cere familia explicită și apartenență activă, apoi autorul ori vizibilitate de familie. Toate rutele intră prin `TenantContext`, cu tranzacție și lock pe familie. Cele două tabele au RLS forțat și FK compuse pentru autor și persoane din aceeași familie.
- Persoanele sunt membri activi menționați prin `membership_ids`, maximum 100, validați în familia curentă. Nu sunt destinatari de partajare. Numele membrilor revocați sunt filtrate la citire; asocierile nu acordă acces și nu înlocuiesc viitorul registru de subiecți P1.
- Lista are 12 elemente/pagină, ordonate descrescător după dată și UUID. Căutarea se face în titlu și text; filtrele sunt tipul și accesul. Payloadul conține întregul element, autorul, persoanele active și `can_edit`, prin join/aggregate, fără query per card. Deschiderea detaliului și a editării reutilizează acest payload.
- `POST /api/v1/families/{family}/moments` creează elementul; `GET` pe aceeași rută listează cu `q`, `kind`, `visibility` și `page`. `GET /moments/{moment}` returnează elementul și ETag. `PATCH /moments/{moment}` primește conținutul formularului și cere `If-Match: "<version>"`; fără token răspunde 428, cu versiune veche 412. Schimbarea conținutului, accesului, persoanelor și auditului este atomică, iar salvarea crește versiunea.
- Formularul păstrează draftul la conflict și blochează salvarea până la reîncărcare explicită, care înlocuiește modificările nesalvate. Nu există retrimitere automată a mutațiilor. Cererile sunt anulate/ignorate la demontare; închiderea unui formular nu garantează anularea unui commit deja ajuns la server.
- Lista și detaliul se actualizează manual ori la 60 s cât pagina este vizibilă. Editarea nu este suprascrisă de polling. Schimbarea familiei sau actorului remontează modulul. Erorile de acces închid conținutul și cer reverificarea sesiunii.
- Meniul include Amintiri după Bucătărie și înainte de Calendar, pe desktop și mobil. Folosește Modal, API-ul și datele comune, Helvetica, suprafețe calde și etichete directe. Bara mobilă are șapte controale de minimum 44 px, cu derulare internă la lățimi insuficiente.

### Fișiere și instalare

- Backend: `Application/MomentAccess.php`, `Http/MomentController.php`, rutele din `backend/routes/web.php` și migrația `2026_10_22_170000_create_memory_moments.php`.
- Frontend: `Memories.tsx`, `MomentForm.tsx`, `MomentDetail.tsx`, `memoryTypes.ts`, `memory.css` și integrarea în `frontend/src/app/App.tsx`.
- În mediul țintă, aplică migrația cu rolul administrativ și apoi `ops/grant-runtime.sql` pe aceeași bază, conform [SETUP](../../ops/SETUP.md). Publică buildul frontend împreună cu backendul migrat. Nu s-au introdus seedere sau date demo noi.

### Verificări și limite

- `npm --prefix frontend run build`: TypeScript și Vite reușite. Prettier aplicat fișierelor frontend modificate; linkurile locale și `git diff --check` verificate.
- La implementarea inițială pe macOS, PHP/Composer/vendor nu erau disponibile și nu s-au executat verificări backend/runtime. Rezultatele locale Windows observate ulterior sunt consemnate la AM2.
- **Fără suite de teste**, conform priorității salvate: nu s-au adăugat sau rulat teste automate. Verificarea manuală nu închide acceptarea integrală a modulului sau a MVP-ului.
- Fotografiile, accesul selectiv și arhivarea/restaurarea sunt adăugate în AM2. Ștergerea definitivă, subiecții fără cont, evenimentele Memory în outbox și timeline-ul rămân pentru pașii următori.

## AM2 — fotografii și partajare

### Comportament și contract

- Un moment sau o notă poate avea o fotografie opțională. `PrivateMedia::receiveImage` aplică MIME real, scanare ClamAV, recodare fără metadatele originale și limitele existente: JPG/PNG/WebP, maximum 10 MB și 16 megapixeli, cotă comună de familie de 100 MB. Numai `ready` oferă bytes; UI arată carantina și respingerea. Autorul poate relua verificarea unei fotografii în carantină.
- Crearea și editarea primesc `photo_action=keep|replace|remove` și fișierul `photo` pentru înlocuire. Cererile JSON AM1 fără fotografie rămân compatibile. Formularul folosește multipart POST cu `_method=PATCH` la editare, pentru parsarea fișierelor în PHP. Media nouă se salvează în tranzacția momentului, cu cleanup la rollback prin pipeline-ul existent.
- `visibility=selected` cere `grant_membership_ids` cu alți membri activi ai aceleiași familii. `membership_ids` rămâne exclusiv pentru menționare, fără acordare implicită de acces. Granturile active apar în payloadul complet numai pentru autor; se încarcă prin agregare/JOIN, fără N+1 sau GET suplimentar la deschiderea ferestrei.
- `MomentAccess` controlează lista, detaliul și fotografia după apartenența activă și politica actuală. Proprietarul familiei nu primește acces implicit la amintiri private. Revocarea unui membru curăță granturile Memory prin evenimentul public `MembershipRevoked`; schimbările de acces cresc `authz_revision` în aceeași tranzacție.
- Lista activă exclude arhivatele; `GET /moments?archived=1` returnează numai arhivatele autorizate ale autorului. `POST /moments/{id}/archive` și `/restore` cer `If-Match`, cresc `version`, șterg granturile și setează accesul `private`. Arhivarea păstrează conținutul/fotografia pentru autor; editarea și retry-ul scanării cer restaurare. Restaurarea nu reactivează partajarea anterioară.
- `GET /moments/{id}/photo` reautorizează momentul înainte de bytes, cu răspuns privat fără cache. `POST /moments/{id}/photo/scan` cere autorul, rol activ de adult/proprietar, element activ și `If-Match`; nu schimbă versiunea conținutului.
- Conflictele păstrează draftul; reîncărcarea explicită înlocuiește alegerile nesalvate. Confirmarea arhivării explică retragerea accesului o singură dată. `PrivatePhoto` din `frontend/src/shared/` este comun cu Bucătărie, cu aceleași stări și URL-uri existente acolo. Lista Amintiri grupează căutarea/accesul/actualizarea și filtrele, fără subtitlul redundant al listei.

### Fișiere și instalare

- Backend: `MomentController`, `MomentAccess`, listenerul din `AppServiceProvider`, rutele moments și migrația `2026_10_22_180000_add_memory_sharing_and_photo.php`, după migrația AM1. Coloanele foto/arhivare și tabela `memory_moment_grants` folosesc FK compuse; granturile au RLS forțat.
- Frontend: fișierele existente Memory, `shared/PrivatePhoto.tsx`, adaptorul `KitchenPhoto.tsx` și identificarea actorului în `App.tsx`. Nu sunt dependențe noi.
- În Windows local, cele patru migrații restante din pull (K4a, K4b, AM1, AM2) au fost aplicate pe `memoria` cu rol administrativ, apoi `ops/grant-runtime.sql`. Nu s-au resetat bazele și nu s-au rulat seedere. Aplicația rulează cu rolul runtime; verificările folosesc conturile demo și două amintiri fictive create în browser.

### Verificări observate și limite

- TypeScript/Vite build, Prettier, Pint, lint PHP pentru fișierele schimbate, înregistrarea rutelor și `git diff --check`: reușite. Nu s-au adăugat/rulat suite de teste.
- Browser, desktop: creare cu fotografie scanată și afișată; acces selectiv pentru Mihai, citire fără controale de editare în contul destinatarului; retragere la „Doar eu”, după care lista destinatarului este goală și URL-ul direct al fotografiei răspunde `Not found`; păstrare, înlocuire, eliminare și readăugare fotografie; arhivare, filtrul Arhivate și restaurare privată; creare notă fără fotografie cu acces de familie.
- Browser, două ferestre: salvare din ecranul mobil, conflict în editorul desktop, draft păstrat și salvare blocată până la reîncărcare explicită; reîncărcarea aduce textul salvat pe mobil. Verificare vizuală a listei, detaliului și formularului la 390 px, cu derulare verticală în ferestre. Capturi locale ignorate: `.runtime/am2-desktop.png`, `.runtime/am2-mobile.png`.
- Nu s-au exercitat în AM2 rolul limitat, revocarea completă a apartenenței, cereri cross-tenant, limitele de cotă/dimensiuni, fișiere infectate, scanner indisponibil ori eșec forțat după scrierea media. Acestea nu sunt declarate acceptate doar prin reutilizarea codului existent.
- Eliminarea/înlocuirea desprinde fotografia de moment, fără ștergere fizică imediată. Media nereferențiată rămâne privată și intră în cotă până la cleanupul planificat în P2; niciun endpoint Memory nu o poate descărca prin ID-ul media. Nu sunt albume cu mai multe fotografii, upload în fundal sau timeline în acest pas.

## Următorul pas

AM3: timeline cu momente și preparări, prin contractele publice Activity/Outbox, paginare stabilă și autorizarea canonică la citire. Păstrează separat acceptarea restantă AM1/AM2; nu modifica versiunile istorice din Bucătărie și nu începe automat alt modul.
