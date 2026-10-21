# Bucătărie

Stare: **K1, K2 și K3a implementate și verificate local**, 21 octombrie 2026. Utilizatorul a prioritizat întâi rețetele, apoi mesele cu vot/comentariu și editarea cu istoric. Aceste felii preced restul M04 și folosesc contextul de familie, RLS, audit și media privată existente. Preparările libere/participanții și K4 rămân separate; M05 nu este închis integral.

## Scop

Rețete și versiuni, mese pe o dată, vot personal binar și comentariu opțional. La 21 octombrie, utilizatorul a ales „Mi-a plăcut” / „Nu mi-a plăcut”, în locul notelor 1–5 propuse în blueprint. Preparările libere, participanții și evenimentele pentru timeline rămân planificate; nu sunt funcții livrate de K3a.

## Pași

- [x] **K1 — Contract și rețete.** Modele, acces, migrație, contract public, listă/căutare/filtre/paginare, creare și detaliu, cu fotografie opțională, stări goale/eroare și izolare între familii. Verificările observate sunt mai jos.
- [x] **K2 — Versiuni.** Editare cu versiune nouă, istoric complet paginat, fotografii istorice și conflict între două editări verificate; mesele păstrează versiunea folosită.
- [ ] **K3 — Mese și feedback.** Identitatea actorului, istoricul și feedbackul rămân ale modulului Bucătărie.
  - [x] **K3a — Mese din rețete, vot și comentariu.** Dată și tip de masă, acces privat/familie, versiune fixă a rețetei, vot binar și comentariu opțional propriu pentru membrii activi. Modificare/retragere cu protecție la conflict. K2 completează protecția versiunii folosite cu editare și istoric.
  - [ ] **K3b — Preparări libere și participanți.** Scopul se stabilește când este prioritizat; nu adăuga implicit alte forme de rating peste alegerea binară a utilizatorului.
- [ ] **K4 — Acces, arhivare și evenimente.** Închide partajarea/revocarea, arhivarea și publicarea prin outbox. Verifică replay și evenimente duplicate; integrarea vizibilă în timeline se acceptă în AM3.

## Acceptare

- Editarea unei rețete nu schimbă istoricul unei preparări existente.
- Votul binar este unic pentru masă și apartenența actorului autorizat; un membru nu votează și nu scrie comentarii în numele altuia.
- O rețetă sau preparare privată nu apare altui membru prin listă, ID, căutare ori timeline.
- Evenimentul pentru timeline se salvează atomic cu sursa; retry nu dublează prepararea sau proiecția.
- Fluxul rețetă → versiune → masă → vot/comentariu trece prin API și UI cu verificările relevante. Editarea versiunilor și păstrarea mesei existente sunt verificate în K2.

## Surse și căi viitoare

Consultă numai operațiile `Kitchen_`, `Recipe_`, `CookingSession_` din `Memoria_Familiei_Review_v1.2/api.inventory.txt` și schemele/contractele aferente din pachet. Ele descriu planul, nu rute existente.

Implementarea este în `backend/app/Modules/Kitchen/` și `frontend/src/modules/kitchen/`. Contractul public este în `Contracts/`; nu importa internals din Amintiri.

## K1 — comportament și contract

- Utilizatorul a cerut **Bucătărie**, cu **Rețete** în interior, și a confirmat ordinea Acasă → Familia mea → Documente → Bucătărie → Calendar pe desktop și mobil. Acasă rămâne pagina implicită. Referința specifică: [imaginea aleasă](../../artifacts/kitchen-reference.png), cu Helvetica și paleta comună. Se preiau fotografiile, cardurile și etichetele compacte. K3a adaugă secțiunea reală „Mese”; planificarea săptămânală, cumpărăturile, favoritele și recomandările nu sunt afișate ca funcții existente.
- `recipes` deține familia, autorul, accesul `private`/`family`, versiunea curentă, marcajul de exemplu și arhivarea rezervată K4. `recipe_versions` salvează prima versiune: titlu, categorie, ingrediente, preparare, durată, porții, dificultate și fotografia opțională. K1 nu expunea editare sau ștergere; editarea versionată și concurența sunt completate în K2, accesul modificabil/arhivarea rămân K4.
- Ambele tabele au RLS forțat. FK compuse leagă autorul de familia sa, versiunea de rețeta din aceeași familie și fotografia de media aceleiași familii. API-ul nu acceptă autor, familie, versiune, asset sau marcaj de exemplu impuse de client.
- `GET /api/v1/families/{family}/recipes`: `q` caută în titlu, `category` și `visibility` filtrează, `page` începe la 1. Răspuns `{items, has_more, page}`, maximum 12 rețete, ordine descrescătoare după creare și ID. Caracterele `%`, `_` și `\` sunt căutate literal. Numele autorului și starea fotografiei vin prin JOIN; numărul interogărilor nu crește cu lista. Elementele includ întregul detaliu și versiunea; deschiderea ferestrei nu face un GET redundant.
- `POST /recipes`: proprietarul sau un adult poate crea; rolul limitat poate doar citi rețetele autorizate. Limite: titlu 160 caractere, 1–60 ingrediente de maximum 200 caractere fiecare, preparare 16.000 caractere, 1–1.440 minute, 1–50 porții. Accesul inițial în formular este „Doar eu”; alegerea „Familia mea” include membrii activi. Proprietarul familiei nu poate vedea o rețetă privată a altui adult. Crearea rețetei, versiunii, media și auditului este atomică; rollbackul elimină fișierul nou.
- `GET /recipes/{recipe}` reverifică accesul și returnează detaliul cu ETag. `GET /recipes/{recipe}/photo` verifică accesul canonic la rețetă și apoi media; o adresă de imagine nu acordă acces suplimentar. Toate răspunsurile sunt private, fără cache; nu se expun chei de stocare, hashuri, ID-ul assetului sau bytes în payload.
- `PrivateMedia::receiveImage` reutilizează pipeline-ul comun și limitează MIME-ul real la JPG/PNG/WebP, fără PDF. Limitele existente rămân 10 MB/fișier, 16 megapixeli și 100 MB/familie. Fotografiile sunt scanate, reencodate fără metadate și servite inline doar în starea `ready`, cu tip imagine explicit și `nosniff`. Carantina/respins sunt vizibile în UI; bytes nu sunt disponibili. Autorul poate folosi `POST /recipes/{recipe}/photo/scan` pentru reîncercare.
- Contractul de citire este `App\Modules\Kitchen\Contracts\Recipes`, legat la `RecipeCatalog` în composition root. Controllerul, rutele și contractul rămân în modul; fotografiile nu creează documente. Frontendul folosește shared API, Modal și stilurile comune. Schimbarea familiei/contului remontează pagina; cererile întârziate sunt anulate/ignorate. Lista se actualizează manual și la 60 s cât pagina este vizibilă. Închiderea formularului anulează cererea clientului și ignoră răspunsul ulterior; nu garantează anularea unei scrieri deja acceptate de server.

### Verificări K1

- `KitchenRecipeTest.php`: **11 teste / 150 aserțiuni**, trecute pe `memoria_test`. Acoperă creare și prima versiune/audit, payload complet, privat față de proprietarul familiei, rol limitat, familie greșită, revocare în sesiune existentă, metadate/bytes/scan, ID invalid, valori și identificatori impuși de client, filtre combinate, caractere speciale, paginare fără suprapuneri și număr constant de query-uri, MIME fals/PDF/SVG/fișier prea mare, normalizare, carantină/retry/respingere, rollback cu eliminarea fișierului, RLS și FK între familii, exemple idempotente.
- După extinderea media s-au rulat cele 20 de teste DocumentSecurity: 18 au trecut, cele două live au fost inițial sărite; apoi **ambele probe ClamAV live au trecut separat** (2 teste / 7 aserțiuni). Primul eșec K1 a identificat PDF-ul redenumit; `receiveImage` verifică acum MIME-ul real. Un al doilea eșec era în dublura scannerului din test, care păstra rezultatul inițial între cereri; dublura folosește acum rezultatul curent. Rularea finală K1 este verde.
- Build TypeScript/Vite, cele 20 de teste frontend, Pint, legăturile locale din documentație și `git diff --check` au trecut. Nu s-au reluat alte suite backend fără modificări ale lor.
- Browser: lista cu trei exemple, detaliu cu ingrediente/preparare, creare reală prin formular a unei rețete private cu fotografie, imagine verificată și afișată, căutare plus filtru de acces, rezultate goale și familie Ionescu fără rețetele Popescu. Schimbarea familiei păstrează Bucătărie și resetează datele/formularul prin remontare.
- Desktop 1376 × 1244 și mobil 360 × 800: fotografii, două coloane mobile, șase controale de navigație în aceeași ordine, fără overflow. Controalele mobile măsurate au aproximativ 49 × 55 px. Formularul este o foaie cu scroll propriu, lățime 360 px, câmpul Titlu primește focus, focusul rămâne în dialog, Escape restabilește butonul Adaugă. Capturi locale ignorate: `.runtime/k1-kitchen-desktop.png`, `.runtime/k1-kitchen-mobile.png`.
- Proba UI `07a6da89-d46f-4784-b1c3-a47faa4c604e` a fost eliminată împreună cu versiunea, assetul, fișierul și auditul; verificare: 0 rețete / 0 versiuni / 0 audit pentru acel ID, trei exemple păstrate. Nicio resetare a bazei aplicației, parolelor, apartenențelor sau documentelor existente.
- Nu s-au provocat separat în browser erori de rețea și închiderea formularului în timpul uploadului; paginarea și rolurile sunt verificate prin API. Nu s-au testat dispozitive fizice, accesul selectiv, editări concurente, preparări, ratinguri sau producția. Acestea nu sunt declarate livrate.

## K3a — mese din rețete, vot și comentariu

### Comportament și model

- Din detaliul rețetei, „Adaugă ca masă” deschide formularul cu data, tipul mesei (Mic dejun, Prânz, Cină, Gustare) și accesul. „Mese” din Bucătărie arată ziua aleasă, cu ziua anterioară/următoare, Azi și paginare. Schimbarea familiei păstrează Bucătărie, dar remontează modulul și închide datele/formularele familiei anterioare.
- Un proprietar sau adult poate adăuga masa dintr-o rețetă pe care o poate citi. Rolul limitat poate citi mesele autorizate și vota pentru sine. Accesul inițial la masă este „Familia mea”, conform cererii; „Doar eu” este disponibil. Formularul explică vizibilitatea mesei, voturilor și comentariilor înainte de salvare. Familia poate vedea numele preparatului publicat ca masă; aceasta nu acordă acces la rețeta sau fotografia privată.
- `kitchen_meals` păstrează familia, autorul, data, tipul, titlul preparatului și FK compusă către rețeta/versiunea exactă. Un client cu versiunea veche a rețetei primește 409 la creare. Titlul este snapshot al mesei; conținutul versiunii și fotografia rămân în sursa canonică. Triggerul `recipe_versions_immutable` respinge UPDATE asupra versiunilor. K3a a introdus această protecție; K2 adaugă inserarea versiunilor noi și istoricul descris mai jos.
- `kitchen_meal_votes` are cheie unică masă + membership și FK compuse pentru familie. Un singur vot binar și un comentariu opțional de maximum 1.000 caractere, legate numai de actorul sesiunii. Salvările actualizează propriul vot, fără duplicate. Nu există rută de modificare a feedbackului altei persoane.
- Votul se deschide din ziua mesei, după data României; mesele viitoare afișează această condiție și nu acceptă vot sau comentariu înainte de dată. Membrii revocați nu mai pot citi/scrie, iar feedbackul lor nu mai intră în liste, comentarii sau totaluri. Revocarea nu este o ștergere fizică; retenția se tratează separat în CD4.
- „Retrage votul” elimină alegerea și textul comentariului. Rămâne numai rândul tehnic cu versiune crescută, pentru ca o fereastră veche să nu suprascrie un vot recreat. Nu se pretinde recuperarea textului retras.
- Toate tabelele noi au RLS forțat; `TenantContext` serializează accesul și revocarea prin familia blocată. Scrierile au și tranzacții proprii pentru rollback la erori de audit. Auditul conține numai actor/acțiune/ID, fără titlu, ingrediente sau comentariu.

### API și UI

- GET `/api/v1/families/{family}/meals?date=YYYY-MM-DD&page=1`: maximum 12 mese pe pagină, ordonate Mic dejun → Prânz → Cină → Gustare, apoi creare și ID. Fără dată, folosește ziua curentă din România. Intervalul admis este 1900–2100. Payloadul are metadata mesei, starea fotografiei autorizate, totalurile și propriul vot; fără ingrediente, instrucțiuni, ID-uri ale sursei sau chei de stocare.
- POST `/meals`: `recipe_id`, `recipe_version_id`, `meal_date`, `meal_type`, `visibility`. Autorul, familia, titlul și versiunea nu pot fi impuse de client. Serverul ia titlul din versiunea autorizată și confirmă referința înainte de orice scriere. Răspunsul 201 conține detaliul și primele comentarii, refolosite de UI fără GET suplimentar.
- GET `/meals/{meal}?page=1`: detaliu, totaluri, propriul vot și maximum 20 de feedbackuri pe pagină. GET suplimentar la deschiderea unui rând este necesar deoarece lista nu include comentariile celorlalți. Numele și totalurile vin prin JOIN/subquery grupat, fără query per masă sau per vot.
- GET `/meals/{meal}/photo`: verifică accesul actual la masă și la rețeta sursă, apoi servește fotografia **versiunii folosite**, prin media privată. O rețetă privată/inaccesibilă sau arhivată nu produce fotografie în payload și nici bytes pe această rută. La extinderea politicii Recipes în K4 trebuie extinse consecvent și JOIN-ul sursei din MealCatalog și testele lui.
- PUT `/meals/{meal}/vote`: `{liked:boolean, comment?:string|null}`. DELETE pe aceeași rută retrage numai votul actorului. `If-Match: "0"` este necesar pentru primul vot, apoi `If-Match: "<versiunea propriului vot>"`; lipsa antetului dă 428 și versiunea depășită 412. Totalurile și feedbackul revin în răspunsul mutației, fără un request ulterior redundant. Membrii pot salva sau retrage doar propriul feedback.
- UI păstrează textul nesalvat la conflict și blochează retrimiterea; „Reîncarcă masa” explică faptul că înlocuiește alegerea și comentariul nesalvate cu varianta din server. Actualizarea manuală a voturilor/paginarea comentariilor păstrează draftul curent; dacă propriul vot s-a schimbat între timp, If-Match protejează salvarea ulterioară. Nu există replay automat al unei scrieri.
- Clientul API, Modal, datele și inițialele sunt refolosite din shared. `KitchenPhoto.tsx` deservește rețetele și mesele; vechea funcție locală RecipePhoto a fost eliminată. Lista se actualizează manual și la 60 s cât este vizibilă; detaliul are „Actualizează voturile”. Cererile la demontare sunt anulate/ignorate. Închiderea formularului nu promite anularea unei scrieri deja acceptate de server.
- Intrări: `MealCatalog.php`, `MealController.php`, migrația `2026_10_21_181243_create_kitchen_meals.php`, `KitchenMealTest.php`, `Meals.tsx`, `mealTypes.ts`, `meals.css` și integrarea în `Kitchen.tsx`. Rutele sunt cele existente în `backend/routes/web.php`, nu inventarul viitor al blueprintului.

### Verificare K3a — 21 octombrie 2026

- **22 teste / 326 aserțiuni** trecute: KitchenMeal și regresia KitchenRecipe. Cele 11 teste noi acoperă snapshotul și versiunea, data/tipul/autorul, payload sigur, voturi personale diferite, schimbarea comentariului fără duplicate, identitate impusă, 428/412 inclusiv după retragere/recreare, acces privat versus familie și owner, fotografie sursă privată, altă familie, revocare și ascunderea feedbackului revocat, rol limitat, deschiderea votului la miezul nopții în România, validare, rollback la audit, păstrarea fotografiei vechi la o versiune ulterioară simulată, blocarea UPDATE al versiunii, paginare 12/20, query count constant, RLS și FK între familii.
- Build TypeScript/Vite, 20 teste frontend, Pint și diff check trecute. Nu s-au reluat DocumentSecurity/Calendar/Home: pipeline-ul media și acele contracte nu au fost modificate. Triggerul și tabelele au fost aplicate atât pe `memoria_test`, cât și local pe `memoria`, fără resetare.
- Browser: Ana a adăugat o masă din rețeta demo, în ziua aleasă, apoi Ana/Mihai au votat diferit și au salvat comentarii separate. O a doua fereastră cu vot vechi a primit conflict; draftul a rămas vizibil, iar reîncărcarea a adus comentariul nou și alegerea corectă, fără a suprascrie serverul. Fereastra suplimentară a fost închisă și sesiunea Ana restaurată.
- Ziua următoare → fără mese, Azi → masa de probă și schimbarea familiei → fără mesele/voturile Popescu au fost verificate și în browser. La 360 px, controalele de zi au minimum 44 px. Proba `c6d04223-49cb-4bbf-9ac6-2d5090c686aa` a fost apoi eliminată exclusiv împreună cu cele două voturi/comentarii demo și auditul ei: verificare DB 0 mese / 0 voturi / 0 audit pentru ID, trei rețete de exemplu păstrate. Rețetele, fotografiile, identitățile și alte date existente nu au fost modificate de curățare.
- Desktop 1376 × 1244 și mobil 360 × 800: controale de vot măsurate la aproximativ 145 × 44 px pe mobil, dialog de 360 px fără overflow, comentarii cu scroll și focus în dialog. Fotografia mesei este compactă pentru a lăsa loc feedbackului. Capturi ignorate `.runtime/k3a-meal-desktop.png`, `.runtime/k3a-meal-mobile.png`; conțin **proba temporară cu conturi demo**, nu mese/voturi ale utilizatorului.
- Paginarea, retragerea, rolurile, revocarea și data viitoare sunt verificate prin API; nu toate au fost repetate în browser. Erorile de rețea și închiderea în timpul unei mutații nu au fost provocate separat; dispozitivele fizice și producția nu au fost testate. Mesele libere, selecția participanților, editarea/arhivarea meselor, integrarea în Calendar/timeline și notificările nu sunt declarate implementate; K2 este documentat separat mai jos.

## K2 — editare și istoricul versiunilor

### Comportament și contract

- Autorul cu rol proprietar/adult poate edita conținutul rețetei. Un proprietar al familiei nu poate edita rețeta altui adult. Rolul limitat citește numai sursele autorizate; revocarea blochează și istoricul, fotografiile istorice și scrierile.
- POST `/api/v1/families/{family}/recipes/{recipe}/versions` primește conținutul complet și `photo_action: keep|replace|remove`. `replace` cere un fișier; celelalte acțiuni interzic fișierul. Nu acceptă schimbarea accesului, autorului, familiei, versiunii sau ID-ului media. Accesul modificabil rămâne K4.
- `If-Match: "<versiunea curentă>"` este obligatoriu: lipsă → 428, versiune depășită → 412, înainte de primirea/stocarea fotografiei. Contextul familiei serializează cererile și revocarea; tranzacția internă inserează noua versiune, actualizează pointerul rețetei și scrie auditul minimal `recipe.version_created`. La eroare se retrag și rândurile, și fișierul nou.
- Rândurile `recipe_versions` nu sunt actualizate. Triggerul de imutabilitate și FK-urile compuse existente rămân active; nu a fost necesară o migrație K2. Păstrarea fotografiei refolosește assetul, înlocuirea primește un asset nou, eliminarea pune `null` numai în versiunea nouă. Nicio variantă nu șterge fotografia istorică.
- GET `/recipes/{recipe}/versions?page=1` returnează maximum 12 versiuni complete, descrescător după număr, cu `has_more` și `page`. GET `/recipes/{recipe}/versions/{version}` și `/photo` verifică rețeta canonică și apartenența versiunii la aceeași rețetă/familie. Payloadul include `version`, `current_version`, `version_created_at` cu offset și întregul conținut; fără chei de stocare sau asset ID. JOIN-urile aduc autorul și starea fotografiei, fără N+1.
- „Istoric” în detaliu permite citirea fiecărei versiuni direct din lista încărcată, fără GET suplimentar. „Editează” folosește detaliul existent, cu formular comun creare/editare. Salvarea returnează versiunea nouă. Versiunile vechi nu oferă editare sau creare de masă; utilizatorul poate reveni la versiunea curentă. Nu există restaurare automată a unei versiuni vechi.
- Actualizările periodice ale listei nu înlocuiesc formularul deschis. La 412, draftul rămâne vizibil, salvarea se blochează, iar „Reîncarcă rețeta” explică înlocuirea modificărilor nesalvate. Reîncărcarea explicită actualizează și contextul detaliului, pentru ca închiderea editării să nu readucă vechea versiune.
- GET `/meals/{meal}/recipe` și „Rețeta folosită” arată în mod de citire versiunea exactă a mesei. Se verifică atât accesul actual la masă, cât și la rețetă. Lista/detaliul mesei primesc doar indicatorul `recipe_available`, fără ID-urile sursei. O masă partajată nu deschide o rețetă privată. Titlul, fotografia, ingredientele și prepararea veche, voturile și comentariile nu sunt rescrise la editare.
- Intrări: `RecipeCatalog`, `Contracts/Recipes`, `RecipeController`, integrarea `MealCatalog`/`MealController`, rutele Kitchen, `RecipeForm.tsx`, `RecipeDetail.tsx`, `RecipeMeta.tsx`, `KitchenPhoto.tsx`, tipurile și stilurile Kitchen. Fotografiile rețetelor folosesc acum URL-ul versiunii exacte; cele ale meselor păstrează ruta autorizată existentă.

### Verificări K2 — 21 octombrie 2026

- `KitchenRecipeVersionTest`: **9 teste noi / 124 aserțiuni**. Împreună cu KitchenRecipe și KitchenMeal: **31 teste / 450 aserțiuni**, trecute pe `memoria_test`. Probe: versiune nouă fără schimbarea rândului vechi, istoric complet/paginat și query count constant, 428/412 fără fișiere sau audit suplimentare, owner/adult/limited/revocare, privat versus proprietarul familiei, versiune din altă rețetă/familie, keep/replace/remove cu păstrarea bytes istorici, masa și votul vechi intacte, sursă privată neexpusă prin masă, identificatori/acces/format foto invalide și rollback la eroare de audit.
- Verificarea completă `ops/test-local.ps1`: **109 teste backend / 1.040 aserțiuni**, inclusiv cele două probe ClamAV live, fără teste sărite. Cele **20 de teste frontend**, buildul TypeScript/Vite și Pint au trecut. Buildul a fost reluat după alinierea reîncărcării explicite cu detaliul; backendul nu s-a schimbat după rularea completă.
- Browser, Ana/Popescu: creare rețetă temporară, masă pe versiunea 1, vot/comentariu, editare la versiunea 2 cu alt titlu/ingrediente/preparare, selectarea versiunii 1 din istoric și „Rețeta folosită” din masa veche. Masa a păstrat titlul, ingredientele, prepararea și feedbackul inițiale. Două ferestre cu versiunea 1 au demonstrat conflictul: primul save a reușit, al doilea a păstrat draftul și a cerut reîncărcare, care a adus conținutul nou.
- Desktop **1376 × 1244** și mobil **360 × 800**: fără overflow orizontal, dialog de 360 px pe mobil, câmpuri/controale de minimum 44 px, rânduri istoric de aproximativ 69–88 px. Focus în dialog și Escape verificate. Capturi ignorate cu **date demo temporare**: `.runtime/k2-history-desktop.png`, `.runtime/k2-history-mobile.png`, `.runtime/k2-used-recipe.png`.
- Curățare exactă: rețeta `328279bb-2ed7-4470-948e-82362fa2553c`, masa `99550474-7903-4d18-8b7a-177beed46712`, cele două versiuni, un vot/comentariu și cele patru evenimente audit ale probei au fost eliminate. Verificare DB: **0** rânduri pentru toate aceste resurse; trei exemple păstrate. Proba nu a încărcat fotografii în baza locală. Fotografiile istorice/înlocuirea/eliminarea au fost verificate prin API pe baza de test, nu repetate cu upload în browser.
- Fără test pe dispozitive fizice sau în producție; nu s-au provocat separat erori de rețea/închidere în timpul scrierii. Accesul selectiv, arhivarea, restaurarea, preparările libere și integrarea Calendar/timeline rămân nelivrate.

## Date și imagini demo

`KitchenDemoSeeder` adaugă idempotent trei exemple numai în familia demo Popescu existentă, în mediul `local`/`testing` cu demo activ. Nu resetează familia sau identitățile și nu rulează automat la fiecare pornire. Exemplele folosesc aceeași scanare și stocare privată; sunt marcate „Exemplu” în listă și „Rețetă de exemplu · imagine generată.” în detaliu. Nu au note, favorite ori istoric inventat.

Imagini generate cu toolul built-in `imagegen`, copiate în repository:

- [pasta.png](../../backend/database/seeders/assets/kitchen/pasta.png)
- [soup.png](../../backend/database/seeders/assets/kitchen/soup.png)
- [pie.png](../../backend/database/seeders/assets/kitchen/pie.png)

Prompt comun, cu subiectul corespunzător fiecărui fișier:

> Use case: photorealistic-natural. Asset type: food photograph for a clearly labelled sample recipe in a family kitchen web application. Subject: {subject}. Warm natural window light, home kitchen oak table, close three-quarter angle, appetizing realistic food texture, calm neutral background softly out of focus. Horizontal 4:3 composition with the food filling the frame, safe to crop the edges for small cards. No people, no text, no logos, no watermark.

Subiecte exacte:

- `pasta.png`: Creamy tagliatelle with sliced mushrooms, parsley and parmesan in a warm ivory ceramic bowl
- `soup.png`: Romanian vegetable soup with cabbage, carrots, tomatoes and fresh dill in a rustic ivory ceramic bowl
- `pie.png`: Homemade Romanian apple pie cut in rectangular pieces, delicate powdered sugar, flaky golden crust on an ivory ceramic plate

## Următorul pas

K2 este încheiat local. Pentru continuare se stabilește separat prioritatea dintre K4 (acces/arhivare) și K3b (preparări libere/participanți). Editarea/arhivarea meselor, planificarea și lista de cumpărături nu se adaugă implicit. Nu modifica în loc rândurile versiunilor și nu adăuga taburi inactive pentru a reproduce imaginea.

Planul nutrițional, cumpărăturile automate, inventarul ingredientelor și recomandările clinice sunt în afara MVP.
