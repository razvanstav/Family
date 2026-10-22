# Amintiri

Stare la 22 octombrie 2026: **AM1 implementat în surse**, cu build frontend reușit; backendul și migrația nu au fost executate în acest checkout. Utilizatorul a reprioritizat funcțiile pentru MVP, fără teste, înaintea instalării Linux. AM2 și AM3 rămân planificate.

## Scop

Momente și note private sau partajate, fotografii, asocieri cu persoane/subiecți și timeline autorizat. Momentele sunt surse canonice aici; activitățile altor module apar ca proiecții, fără copierea proprietății asupra datelor lor.

## Pași

- [ ] **AM1 — Momente și note.** Creare, listă, căutare, detaliu și editare cu controlul versiunii implementate în API și UI; acceptarea runtime rămâne deschisă. Menționarea membrilor activi este implementată; subiecții care nu sunt membri așteaptă registrul P1, fără identități fictive sau acces implicit.
- [ ] **AM2 — Fotografii și partajare.** Folosește pipeline-ul Media, cu fișiere curate și accesul momentului. Verifică schimbarea vizibilității, revocarea, arhivarea și rollbackul după încărcare.
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
- **Fără teste**, conform cererii utilizatorului: nici suitele existente, nici teste noi. Nu există rezultate runtime declarate pentru AM1.
- PHP, Composer și `backend/vendor` lipsesc în checkoutul macOS. Artisan, lintul PHP, Pint, migrațiile, granturile și fluxurile HTTP/DB nu au fost executate. Fișierele PHP au fost create după convențiile existente, fără generatorul Artisan indisponibil. Nu s-a făcut verificare vizuală în aplicația pornită.
- Fotografii, acces selectiv, arhivare/restaurare, ștergere, subiecți fără cont, evenimente Memory în outbox și timeline rămân în afara acestui pas. AM1 nu închide acceptarea integrală a modulului sau a MVP-ului.

## Următorul pas

AM2: fotografie prin contractul `PrivateMedia`, cu autorizarea momentului, acces selectiv și arhivare/restaurare. Refolosește pipeline-ul existent pentru scanare/recodare/cotă și păstrează bytes privați. AM3 va integra sursele în timeline prin contractele publice Activity/Outbox; nu modifica versiunile istorice din Bucătărie.
