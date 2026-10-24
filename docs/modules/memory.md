# Amintiri

**DC1, 24 octombrie 2026:** data originală a amintirii/însemnării apare în Calendar prin `MemoryDatedRecords`, indiferent de notificări, cu ACL canonic; este distinctă de aniversarea opțională „În ziua asta” din NS1. Detaliul se deschide în sursă; [verificări Calendar DC1](calendar.md#dc1--toate-datele-introduse-de-utilizator-24-octombrie-2026).

**NS1, 24 octombrie 2026:** Contul meu → Setări → Amintiri activează independent În aplicație / Email pentru „În ziua asta”, la 09:00 România. Numai momente/însemnări autorizate din anii precedenți cu aceeași zi/lună; proiecție privată per destinatar și deschidere canonică din Calendar. Implicit oprit, fără replay al orelor trecute; contracte și verificări în [NS1](calendar.md#ns1--setări-personale-și-emailuri-automate-24-octombrie-2026).

## MP1 — Fotografii multiple, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită a utilizatorului: mai multe fotografii într-o amintire, vizualizare pe tot ecranul și răsfoire pe telefon. Un singur layout, în formularul și detaliul existente din Amintiri. Acceptarea vizuală așteaptă feedbackul utilizatorului.

### Comportament și contracte

- Formularul acceptă selecții multiple și adăugări succesive, cu previzualizări locale și eliminare individuală înainte de salvare. Maximum 20 de fotografii/amintire și 10 MB de fotografii noi cumulate la o salvare; JPG/PNG/WebP, 16 megapixeli/fotografie și cota comună de 100 MB rămân cele ale pipeline-ului existent. Previzualizările folosesc FileReader în memorie și politica CSP existentă, fără upload înainte de Salvare.
- Prima fotografie păstrată, urmată de cele nou adăugate în ordinea selecției, este coperta. Eliminarea copertei promovează următoarea fotografie; eliminarea tuturor păstrează amintirea fără fotografie. Cardul listei afișează numărul real când sunt mai multe fotografii. Acasă, profilurile și Cronologia păstrează contractele și ruta copertei existente; nu se redesenează aceste pagini.
- `memory_moment_photos` păstrează ID-ul fotografiei, familia, momentul, referința media și poziția 0–19, cu FK compuse, ordine unică și RLS forțat. Migrația `2026_10_24_132325_create_memory_moment_photos.php` transformă fiecare fotografie existentă în prima fotografie a galeriei, fără mutarea bytes sau modificarea câmpurilor amintirii. `memory_moments.photo_asset_id` rămâne referința copertei pentru consumatorii existenți.
- Payloadul complet din listă/detaliu/salvare include `photos: [{id, status}]`, ordonate prin agregare/JOIN, fără N+1, chei de stocare sau ID-uri media. Deschiderea din lista Amintiri continuă să folosească datele deja încărcate.
- Formularul trimite `photos_mode=edit`, `photo_ids[]` pentru fotografiile păstrate și `photos[]` pentru fișierele noi; lipsa ID-urilor în acest mod elimină toate fotografiile anterioare. Crearea/editarea și salvarea media sunt atomice, cu `If-Match` la editare și cleanup al fișierelor noi la rollback. ID-urile altui moment/familii și duplicatele sunt respinse. Cererile istorice `photo_action`/`photo` rămân compatibile: keep păstrează întreaga galerie, replace o înlocuiește cu o fotografie, remove o golește; modurile nu se combină.
- `GET /moments/{moment}/photos/{photo}` reautorizează momentul și asocierea fotografiei la fiecare cerere, cu bytes numai pentru ready și cache privat dezactivat. `POST` pe aceeași rută cu `/scan` verifică individual fotografia, numai pentru autorul cu drepturi de editare și versiunea curentă. Partajarea, revocarea și arhivarea se aplică tuturor fotografiilor; fotografiile detașate nu mai au rută autorizată. Bytes detașați rămân în cotă până la cleanupul P2, conform comportamentului AM2.
- Detaliul are fotografie mare, miniaturi cu selecție clară și deschidere pe tot ecranul aplicației. Viewerul folosește dialog modal, proporțiile originale ale imaginii, contor, săgeți de minimum 48 px, glisare orizontală prin Pointer Events, tastele stânga/dreapta și Home/End. Mișcările scurte/verticale nu schimbă fotografia; navigarea se oprește la capete. Escape și Închide revin numai în detaliu, la fotografia selectată; Tab/Shift+Tab păstrează focusul în viewer. Stările de verificare/respingere/indisponibilitate sunt păstrate prin PrivatePhoto.
- Intrări UI: `MomentGallery.tsx`, `MomentPhotoEditor.tsx`, `galleryNavigation.ts`, formularul, detaliul și cardul existente. `styles.css` adaugă numai convenția comună `fullscreen-dialog`, care elimină gutterul cât un astfel de dialog este deschis; nu există dependențe noi între module sau pachete noi.

### Verificări observate

- **Backend:** 11 teste MP1 + cele 3 gărzi modulare, **14 teste / 176 aserțiuni trecute**, serial numai pe `memoria_test`. Acoperă ordinea și coperta, payload complet, bytes privați, adăugare/eliminare, compatibilitate, acces selectiv/privat, arhivare/restaurare, apartenență revocată/rol limitat, ID-uri străine/duplicate, scanare individuală, versiune veche, rollback pentru al doilea fișier invalid, limite/cotă, query count constant și RLS. Fixture-urile noi folosesc ID-uri proprii și curățare țintită, fără TRUNCATE. Rularea inițială care includea vechiul MemoryAlbumTest a fost refuzată automat din cauza golirii bazei în setupul său; acel test și suita generală nu au fost rulate în MP1. După adaptare, testele MP1 au trecut; o eroare inițială a ordinii de cleanup a fixture-urilor a fost corectată.
- **Frontend:** 30 teste trecute, inclusiv 2 teste noi pentru gesturi și limitele navigării; după ultima corecție de focus au fost repetate cele 5 teste galerie/gărzi, toate trecute. Buildul final TypeScript/Vite este `index-B9Z0hmDA.js` / `index-CpRZF36h.css`; avertismentul existent pentru JS peste 500 kB rămâne (554,10 kB). Pint și Prettier trecute.
- **Migrație:** aplicată aditiv și cu granturi runtime pe `memoria_test` și `memoria`. Amprentele users/families/family_memberships/memory_moments/media_assets/family_tasks din aplicația reală au rămas identice; toate coperțile existente au asociere la poziția zero. Fără reset sau seeder general.
- **Browser:** server temporar 8001 cu baza `memoria_test` și cookie separat; cont/familie „Galerie MP1 · test” create prin UI. Album privat cu trei imagini decorative existente din proiect: selecție multiplă, previzualizări, salvare, miniaturi, deschidere directă din Acasă, eliminarea copertei/adăugarea unei imagini și păstrare după reload demonstrate. Desktop 1280×900 și 390×844 / 320×800: fără overflow observat; viewerul ocupă exact viewportul, controalele viewerului sunt 48 px și eliminările din editor 44 px. Glisare stânga/dreapta prin pointer, mișcare verticală ignorată, săgeți, Home, limite, Escape cu revenirea focusului și Tab/Shift+Tab verificate. Capturi `.runtime/mp1-{gallery,viewer}-{desktop,mobile}.png`, `mp1-viewer-320.png`, `mp1-editor-320.png`; consola probei fără erori/warninguri observate.
- **Limite:** fără dispozitiv fizic sau probă de pinch zoom pe telefon, montaj/video, reordonare prin drag, upload în fundal ori suite generale. Conflictele, scanarea și revocarea sunt probate prin teste backend, nu prin scenarii noi cu două browsere. Acceptarea vizuală rămâne la utilizator; fără commit/push/deploy în acest pas.
- **Încheiere:** fotografia unică existentă din aplicația reală 8000 a fost deschisă în noul detaliu cu 1/1 și coperta păstrată, fără salvări. Amintiri rămâne deschis pe Ana/Popescu, fără dialog; tabul/serverul temporar 8001 au fost închise și viewportul resetat. Prettier, legăturile/ancora MP1 și `git diff --check` verificate.

**P1a.3, 24 octombrie 2026:** Home și profilul Familiei consumă `Contracts/MomentSummaries`, implementat de `RecentMoments`. Filtrele canonice, ordinea, paginarea și payloadurile rămân aceleași; [regresia este în Platformă](platform.md#p1a3--contracte-backend-și-ownership-verificat-la-24-octombrie-2026).

**P1a.1, 24 octombrie 2026:** compoziția mesei din Cronologie este furnizată de App prin `renderMeal` și `mealTypeLabels`; nu există importuri din Bucătărie. Modalul, accesul, voturile și revenirea la Cronologie rămân în componentele existente. [Verificări și limite P1a](platform.md#p1a1--amintiri-ui-verificat-la-24-octombrie-2026).

Stare la 22 octombrie 2026: **AM1–AM3 implementate**, cu fluxurile principale demonstrate local pe Windows, desktop și mobil. Acceptarea completă rămâne deschisă pentru scenariile neexercitate de mai jos. Se păstrează prioritatea funcțiilor, fără suite de teste, înaintea instalării Linux.

## MH2 — Album pe ani și În ziua asta, 23 octombrie 2026

**Implementat și verificat local; acceptarea vizuală așteaptă feedback.** Cerere explicită: un singur ecran Amintiri, cu fotografii mari, grupare pe ani și „În ziua asta”. Antetul, filele comune și corpul Cronologie rămân existente.

- Momente și note grupează pagina curentă pe ani descrescător. Prima fotografie disponibilă din fiecare grup este coperta: fotografie mare și text alăturat desktop, fotografie deasupra textului mobil. Coperta apare prima în grup, iar restul păstrează ordinea după dată. Cardurile următoare folosesc două coloane desktop și una sub 700 px; notele nu primesc imagini fictive. Tipul, data completă, autorul, accesul, numele accesibil și stările foto se păstrează prin `MomentCard`/`PrivatePhoto`.
- Selectorul An filtrează în backend între 1900 și 2100, cu resetarea paginii. Anii disponibili provin din toate amintirile autorizate care corespund căutării/tipului/accesului/arhivei, independent de anul selectat și de pagina curentă. Paginarea de 12 și payloadul complet pentru detaliu/editare sunt păstrate; deschiderea cardului nu adaugă GET pentru detaliu.
- `GET /moments` adaugă `year`, `years`, `today` și `on_this_day`, fără rută sau migrație nouă. În vizualizarea nefiltrată, activă, pe prima pagină, aniversările sunt maximum șase amintiri din anii anteriori, cu aceeași lună și zi ca astăzi în România, găsite în întregul istoric autorizat. Se refolosește `MomentAccess`, inclusiv pentru ani; fotografiile rămân pe ruta privată existentă. Fără N+1: query-uri agregate/JOIN și payload existent. Filtrele și arhiva ascund secțiunea aniversărilor.
- Dacă nu există aniversări, secțiunea spune concret că nu sunt amintiri din anii trecuți pentru această zi. Nu se completează cu alte date și nu se modifică baza aplicației pentru prezentare. 29 februarie are numai potrivire exactă; nu se mută pe 28 februarie/1 martie.
- **Verificări automate:** build TypeScript/Vite, Prettier, Pint, lint PHP și `git diff --check` trecute. `MemoryAlbumTest`: 7 teste/60 aserțiuni trecute, serial pe `memoria_test`: ziua în România la limita UTC, istoric dincolo de prima pagină, paginare/an, ani fără acces privat sau cross-family, grant retras, arhivă, rol limitat/revocare, 29 februarie și ani invalizi. Numai fișierul nou de teste, fără reluarea suitei generale.
- **Browser:** Ana/Popescu, desktop: coperta foto, grupa 2026, selectorul 2026/resetare, detaliu complet și închidere cu Escape verificate. Previzualizare mobilă existentă de 390 px: fotografie pe lățime, text/acces/autor vizibile, fără overflow orizontal observat; căutare bunici → o notă, închidere → album, Cronologie → sursele existente, revenire → album. Filele și antetul rămân în cadrul comun. Capturi ignorate: `.runtime/mh2-desktop.png`, `.runtime/mh2-mobile.png`; ecranul desktop Amintiri rămâne deschis, tabul temporar de verificare închis.
- **Limite:** baza locală are numai amintiri din 2026 și nicio aniversare pentru 23 octombrie; gruparea multianuală și aniversările populate sunt probate prin API în baza de test, fără probă vizuală populată. Fără dispozitiv fizic, upload/salvări noi, scenarii foto carantină/respingere provocate, alte pagini redesenate, commit/push sau deploy. AM1–AM3 nu sunt declarate integral acceptate.

## MH1 — Layout mobil Amintiri, 22 octombrie 2026

**Implementat și verificat local**, ales explicit după FD1. Numai pagina Amintiri, în stilul Acasă/Bucătărie; așteaptă feedbackul utilizatorului înainte de alt layout.

- Antet cu căutare/adăugare, selecție Momente și note/Cronologie cu butoane compacte. Tipurile Toate/Momente/Note rămân la vedere; accesul, arhiva și actualizarea sunt grupate în panoul de filtre. Căutarea se deschide din antet și primește focus.
- Fotografia ocupă lățimea cardului, textul este grupat într-un singur bloc, notele rămân fără imagine fictivă. Pe mobil cardurile sunt într-o coloană; data, tipul, autorul și accesul se păstrează. Titlul complet are nume accesibil explicit și rămâne în detaliu. Stările fotografiilor folosesc PrivatePhoto existent.
- Ordinea după dată, filtrele, arhivarea, detaliul complet, paginarea, cronologia și rutele private rămân existente. Refreshul de fundal păstrează cardurile; schimbarea filtrelor înlocuiește lista. Fără GET suplimentar la deschiderea unui moment din listă.
- Modificări de cod limitate la `Memories.tsx` și `memory.css`. Stilurile comune page-heading/search/modal rămân în styles.css. TypeScript/Vite build, Prettier și `git diff --check` trecute. Fără teste noi sau suite automate.
- Browser, previzualizare mobilă de 390 px: card foto pe lățime, notă compactă și controale fără overflow observat; căutarea „bunici” întoarce nota, iar închiderea readuce lista. Panoul de filtre expune accesul, actualizarea și arhiva. Cardul foto deschide detaliul complet. Cronologia afișează cele două amintiri și masa existente, grupate pe date. Pagina rămâne deschisă pe Momente și note, cu Toate selectat. Captură locală ignorată: `.runtime/mh1-memories-mobile.png`.
- Nu s-au modificat date, permisiuni, rute sau backend. Nu s-au reluat scrierile/uploadul, conflictele, revocările ori dispozitivele fizice; limitele AM1–AM3 de mai jos rămân valabile. Refreshul periodic nu a fost verificat prin așteptare dedicată.

## Scop

Momente și note private sau partajate, fotografii, asocieri cu persoane/subiecți și timeline autorizat. Momentele sunt surse canonice aici; activitățile altor module apar ca proiecții, fără copierea proprietății asupra datelor lor.

## Pași

- [ ] **AM1 — Momente și note.** Implementat; creare de moment/notă, listă, detaliu, menționare și editare demonstrate local, inclusiv conflict și reîncărcare. Acceptarea integrală a rolurilor, filtrelor și paginării nu este închisă. Subiecții care nu sunt membri așteaptă registrul P1, fără identități fictive sau acces implicit.
- [ ] **AM2 — Fotografii și partajare.** Implementat; upload, înlocuire/eliminare, acces selectiv/retragere și arhivare/restaurare demonstrate local. Rollbackul după scrierea fișierului și scenariile de carantină/respingere nu au fost provocate în acest pas.
- [ ] **AM3 — Cronologie.** Implementat: amintiri și mese prin contractele Kitchen/Outbox, paginare și proiecție reconstruibilă. Integrarea, replay-ul repetat, arhivarea/restaurarea și paginarea după rebuild sunt demonstrate local; acceptarea rămâne deschisă pentru scenariile neexercitate de mai jos.

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
- Fotografiile, accesul selectiv și arhivarea/restaurarea sunt adăugate în AM2; evenimentele Memory și cronologia, în AM3. Ștergerea definitivă și subiecții fără cont rămân pentru pașii următori.

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

## AM3 — cronologie

### Comportament și contract

- În Amintiri, vizualizarea nouă „Cronologie” reunește momentele/notele și mesele, cu grupare după data lor calendaristică, filtre Toate/Amintiri/Mese și paginare. Prima vizualizare rămâne „Momente și note”; meniul aplicației și pagina implicită nu se schimbă. Nu sunt incluse carduri separate de rețete, chat sau date fictive de activitate.
- `memory_timeline_sources` conține numai familie, tip/ID de sursă, revizie, `occurred_on` și retragere, cu PK familie/tip/ID, RLS forțat și index pentru ordonare. Nu păstrează titluri, text, fotografii sau ACL-uri. Data vine din `memory_moments.occurred_on` ori, prin provider, din data mesei; nu se convertește după fusul cititorului.
- `MomentController` publică `memory.moment.changed`, schema 1, în tranzacția creării/editării/arhivării/restaurării, alături de sursă, media și audit. Retry-ul scanării nu modifică versiunea conținutului și nu emite alt eveniment; starea fotografiei se citește live.
- Handlerul `memory.timeline.v1` consumă `memory.moment.changed` și `kitchen.meal.changed`. Recitește sursa canonică sub contextul/lockul familiei, refuză schemele incompatibile și reconcilierea în urma versiunii evenimentului. Evenimentele vechi/repetate nu coboară revizia și nu reactivează arhive. Sursa absentă produce o referință retrasă.
- Memory nu interoghează tabelele Kitchen. `Kitchen/Contracts/MealReferences` expune numai metadate canonice prin `find`/`each` în loturi de 100; `Activity::mealCards` rezolvă în loturi cardurile prin politica existentă a mesei/rețetei. Astfel, o masă partajată poate rămâne vizibilă fără fotografia/rețeta la care cititorul nu mai are acces.
- `GET /api/v1/families/{family}/timeline?kind=all|memory|meals&limit=20&cursor=...` folosește `TenantContext` și răspuns privat fără cache. 1–50 carduri/pagină, maximum 200 de referințe scanate plus un rând pentru continuare. Rezumatul autorizat conține titlu, autor, acces, tip, dată, revizie, stare foto și, pentru amintiri, maximum 240 caractere din text. Detaliile complete se cer numai la deschiderea unei surse; nu există query per card.
- Ordine: dată descrescătoare, tip crescător, UUID descrescător. Cursorul criptat/autentificat este legat de familie și filtru și indică ultima referință scanată. O pagină poate fi goală și avea continuare. Cardul cu dată canonică diferită de index este omis până la reconciliere, pentru a nu apărea într-o grupă greșită.
- `memory:rebuild-timeline --family=<UUID>` reconstruiește atomic numai proiecția, inclusiv resursele anterioare instalării, prin sursele Memory și providerul Kitchen. Eșecul păstrează proiecția anterioară. `outbox:deliver --family=<UUID> --replay=memory.timeline.v1` reprocesează evenimentele păstrate, fără a crea surse sau evenimente istorice artificiale.
- UI actualizează cronologia la 15 s cât pagina este vizibilă, cu păstrarea rândurilor/focusului la refresh în fundal. Detaliul amintirii refolosește `MomentDetail`/`MomentForm`; masa folosește componenta publică `Kitchen/MealViewer`, cu detaliul/votul/rețeta existentă și reautorizare la 60 s. Refreshul nu înlocuiește draftul votului. Listele inactive nu trimit cereri de polling paralele.

### Intrări și instalare

- Backend: `TimelineProjection`, `TimelineReader`, `Http/TimelineController`, comanda `RebuildMemoryTimeline`, migrația `2026_10_22_190000_create_memory_timeline_sources.php`, evenimentele MomentController și înregistrarea din `AppServiceProvider`. Dependența Kitchen: `MealReferences`, `MealActivityReferences`, `Activity::mealCards` și metadatele cardurilor MealCatalog.
- Frontend: `memory/Timeline.tsx`, integrarea în `Memories.tsx`/`memory.css`, `kitchen/MealViewer.tsx` și reutilizarea/sincronizarea `MealDetail` din `Meals.tsx`. Nu sunt dependențe noi și nu se modifică `recipe_versions`.
- După migrație și `ops/grant-runtime.sql`, reconstruiește explicit fiecare familie existentă și repornește workerul outbox. Replay-ul singur nu acoperă sursele create înaintea publicării evenimentelor. Vezi comenzile din [SETUP](../../ops/SETUP.md).

### Verificări observate și limite

- Migrație/granturi aplicate pe `memoria`, fără reset/seed; noul worker a livrat cele două receipts ale mesei demo fără erori. Sunt folosite cele două amintiri demo din AM2 și o masă/vot fictive adăugate prin browser.
- Desktop: trei surse distincte, deschidere amintire/masă, vot salvat din cronologie și deschidere a versiunii rețetei folosite. Mihai vede nota și masa partajate, fără amintirea privată a Anei. Arhivarea scoate nota din cronologie; restaurarea este privată, iar partajarea ulterioară funcționează. Data unui moment a fost mutată la 21 octombrie, fără deplasarea datei la citire.
- Replay de două ori cu câte 4 livrări, zero erori: trei referințe, una retrasă la revizia 3. După restaurare, rebuild repetat și replay cu 7 livrări/zero erori: trei referințe active. Amprentele rândurilor canonice Memory/Meal sunt identice înainte și după rebuild/replay.
- API în browser, `limit=1`: trei pagini cu trei surse unice, ultima fără continuare. Cursorul primei pagini rămâne utilizabil după rebuild; folosirea lui în altă familie este respinsă prin validare.
- Vizual: listă/grupe pe zile pe desktop și la 390 px, filtrele Toate/Mese și deschiderea mesei pe mobil, fără overflow orizontal observat. Controalele și derularea ferestrei mesei rămân utilizabile. Regulile mobile sunt după stilurile de bază, fără suprapuneri contradictorii. Capturi locale ignorate: `.runtime/am3-desktop.png`, `.runtime/am3-mobile.png`.
- Build TypeScript/Vite, Prettier, Pint și lint PHP: reușite. S-au ajustat numai cele trei contoare de receipts din `KitchenOutboxTest` afectate de al doilea handler; nu sunt teste noi și nu s-au rulat suite automate, conform priorității salvate. Rezultatele istorice K4b nu reprezintă o regresie rerulată pentru AM3.
- Rămân neexercitate în AM3 ștergerea fizică a sursei, eșecul forțat de handler/rebuild și rollbackul lui, concurența a doi workeri, schemă nouă, limitele de 200 de surse ascunse, volum mare și revocarea completă a apartenenței. Nu sunt declarate acceptate doar prin reutilizarea infrastructurii.
- Apariția sau mutarea unei surse în cronologie este eventual consistentă (worker la 15 s plus polling UI). Politica se verifică live la fiecare citire; aplicația nu poate retrage copii salvate anterior. Publicarea/deploy-ul și acceptarea completă a MVP-ului rămân separate de această demonstrație locală.

## Consum Acasă — H2, 22 octombrie 2026

`Contracts/MomentSummaries::forHome` expune maximum trei rezumate active prin politica `MomentAccess`, cu titlu, tip, extras de 200 de caractere, dată, acces și starea fotografiei. Un singur query cu join media; fără granturi, people, versiuni sau chei media în rezumat. Home nu citește tabelele Memory. La deschiderea din Acasă, App transmite ID-ul către Amintiri, care citește detaliul canonic complet; fotografia folosește ruta privată existentă. Modelul AM1–AM3 și indexul cronologiei rămân neschimbate. Probe H2 și limite: [Interfață](interface.md#verificare-h2--22-octombrie-2026).

Din [FS1](families.md#fs1--povestea-profilului-și-arbore-genealogic-23-octombrie-2026), `Contracts/MomentSummaries::forMember` expune maximum 13 rezumate active autorizate, numai pentru momente cu menționare explicită a profilului. Acceptă poziția validată a cursorului comun cu însemnările; query unic cu JOIN autor/media și EXISTS pentru menționare, fără duplicarea momentelor sau N+1. Fotografia și deschiderea detaliului folosesc rutele canonice existente. Acest consum nu schimbă accesul, asocierea persoanelor ori cronologia AM3.

## Următorul pas

Așteaptă feedbackul utilizatorului pentru galeria MP1 înainte de alt layout. Verificările restante AM3 rămân deschise înaintea acceptării integrale: sursă eliminată, eșec de reconciliere/rebuild, pagini rare și acces retras înaintea livrării. Păstrează acceptarea restantă AM1/AM2; nu începe automat alt modul și nu modifica versiunile istorice din Bucătărie.
