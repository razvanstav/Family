# Căutare și drepturi asupra datelor

Stare: **CD1 — căutare comună în Amintiri, Rețete și Documente**, implementată și verificată local la 23 octombrie 2026. Căutările locale rămân existente. Activarea modulelor, exportul și ștergerea controlată sunt planificate; această felie nu închide M10.

## CD1 — Căutare comună, 23 octombrie 2026

- A treia funcție aleasă explicit după VC1 și BC1. Acces din antet, pe desktop/mobil, fără schimbarea meniului sau a paginii implicite. Minimum două caractere, maximum 100, filtrare Toate/Amintiri/Rețete/Documente, rezultate paginate de 12, stări încărcare/gol/eroare și reîncercare. Focus în câmp la deschidere; Escape închide direct și întoarce focusul la buton.
- `GET /api/v1/families/{family}/search?q=...&type=moment|recipe|document&page=...` combină interogări autorizate prin `SearchProvider`, `FamilySearch` și `SearchTerm`. `DocumentSearch` reutilizează `DocumentAccess`; `MomentSearch` reutilizează `MomentAccess`; `RecipeSearch` reutilizează politica `RecipeAccess` și citește numai versiunea curentă. Fără index universal/copii de date private ori query per rezultat.
- Se caută în titluri de documente, titlu/text de amintire, titlu/ingrediente/instrucțiuni de rețetă. Amintirile/rețetele arhivate sunt excluse explicit. Compararea ignoră majusculele și diacriticele românești; `%`, `_` și backslash sunt text literal. Potrivirile exacte și începuturile de titlu preced restul; ordinea titlu/tip/ID stabilizează paginarea. `has_more` este calculat numai după autorizare; nu se expun numere sau fragmente ale surselor ascunse.
- Rezultatele deschid detaliul canonic: Amintiri, Bucătărie sau dosarul comun/profilul persoanei pentru documente. Documentele personale folosesc subiectul activ, apoi proprietarul activ ca rezervă; dacă niciun profil nu este activ, detaliul autorizat se poate deschide din cadrul Documente, fără mutare ori partajare. Lista completă deja încărcată este reutilizată pentru rețete/amintiri; detaliile absente și granturile documentelor justifică GET separat. Modulele UI nu își importă ecranele reciproc; App coordonează țintele.
- Căutarea se anulează la închidere/schimbare de familie și se revalidează la revenire în tab și la 30 s cât este vizibilă. Rezultatele nu dispar în timpul actualizării aceleiași căutări. Antetul comun se comprimă la 320 px, păstrând căutarea/notificările la 44 px și selectorul accesibil al familiei.
- **Verificat:** `FamilySearchTest`, 9 teste / 113 aserțiuni, serial numai pe `memoria_test`: trei surse și detaliile lor, privat față de administrator, granturi/revocare/readmitere, adult limitat versus minor pentru Casă, arhive/versiuni vechi, diacritice/ingrediente/caractere SQL literale, paginare fără dubluri, query count constant, familii și validare. Prima așteptare pentru „calatorie” a fost restrânsă la Amintiri: documentul demo Jurnal de călătorie era găsit corect în căutarea comună. Rularea completă corectată a trecut.
- TypeScript/Vite build, Prettier și Pint trecute. Browser Ana/Popescu, numai pe 8001/`memoria_test`: trei rezultate populate, filtru Rețete, document → Ana/Documente/Personale, rețetă → Bucătărie, amintire → Amintiri; focus/Escape, prag minim, familie Ionescu fără rezultate și păstrarea Calendarului la schimbarea familiei. Desktop 1280×720, 390×844 și 320×800, fără overflow sau erori/warninguri în consolă. Capturi `.runtime/cd1-search-{desktop,mobile,320}.jpg`.
- Limite: fără OCR, conținutul fișierelor, alte module sau dispozitive fizice. Nu s-au reluat descărcările de bytes, uploadul ori întregile suite ale surselor; politicile lor sunt reutilizate, iar accesul este verificat în testele noi. Fără migrație pentru CD1 sau schimbarea datelor aplicației. Acceptarea vizuală așteaptă feedback.

## Pași

- [x] **CD1 — Căutare autorizată.** Livrată pentru cele trei surse alese explicit: Amintiri, Rețete, Documente. Providerii și probele sunt descrise mai sus; alte surse se adaugă numai într-un pas cerut separat.
- [ ] **CD2 — Activarea modulelor.** Folosește registrul explicit din P1 pentru UI și scrieri. Dezactivarea păstrează datele, oprește alertele noi și este reverificată de joburi; exportul și cleanupul pot folosi providerii fără a reactiva ecranele. Probează adăugarea unui provider fără modificarea internals ale celorlalte module.
- [ ] **CD3 — Export.** Definește solicitantul, scopul și resursele permise, apoi jobul, stocarea privată, expirarea și descărcarea. O revocare invalidează exportul pregătit; dreptul de descărcare este verificat din nou. Membrul exclus are un flux separat și verificat de cerere, fără redeschiderea accesului normal la familie.
- [ ] **CD4 — Ștergere și retenție.** Stabilește politicile înaintea execuției. Separă arhivarea, ștergerea resursei și ștergerea contului; curăță proiecții, remindere, exporturi și bytes neutilizați. Păstrează tombstones minimale pentru a nu reintroduce date șterse după restaurare; testează o restaurare cu replay.

## Acceptare

- Accesul privat nu se scurge prin căutare, timeline, export sau notificări, inclusiv după revocare cu sesiune deschisă.
- Dezactivarea modulului nu șterge date și nu lasă joburile vechi să trimită alerte.
- Exportul nu este public, are expirare și nu se poate descărca după pierderea dreptului relevant.
- Ștergerea contului nu șterge arbitrar datele altui membru și nu transferă date private proprietarului familiei.
- Restaurarea nu reactivează datele acoperite de ștergeri finalizate. Termenele efective de retenție sunt consemnate și aplicate, nu deduse din exemple.

## Dependențe și surse

M10 folosește contractele tuturor modulelor și infrastructura Media/Eventing. Câmpurile căutabile și regulile concrete de acces rămân la modulul proprietar.

Consultă numai operațiile `Search_`, `Settings_`, `Privacy_` și scenariile aferente din pachetul v1.2. Valorile de retenție din blueprint sunt propuneri de decis, nu politici actuale adoptate.

Nu sunt incluse OCR, căutare în conținutul documentelor scanate sau embeddings. Un dashboard Home poate consuma ulterior aceleași contracte, cu funcții și date reale stabilite separat.
