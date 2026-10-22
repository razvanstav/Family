# Familie

Stare: **FD2 implementat la 23 octombrie 2026**, build și verificare punctuală API trecute; fără probe vizuale, la cererea utilizatorului. FP1, F1–F3 și FD1 rămân existente. F4, profilurile fără cont și legarea confirmată la cont rămân separate.

## FD2 — Dosarul comun și documentele din profil, 23 octombrie 2026

**Implementat.** Cerere explicită: dosarul Familie → Documente conține numai documentele cu acces `family`, iar dosarul persoanei este fila Documente din profil, alături de celelalte categorii. Înlocuiește selectorul FD1 și butonul Documente din cardul de identitate FP1.

- Același modul Documents este reutilizat cu un context fix: `visibility=family` pentru familie, `owner_id` pentru membru. Dosarul persoanei include documentele sale deja autorizate de API, inclusiv partajările selective; privatele altui membru nu devin vizibile. Fără mutări de fișiere, schimbări de proprietate sau granturi noi. Proprietatea actuală identifică persoana; asocierea unui document deținut de părinte cu profilul copilului rămâne D4/F4 separat.
- Documente se deschide în profil, fără ieșire în dosarul comun. Pe mobil cele cinci categorii se așază pe două rânduri, cu controale de minimum 44 px. Cât fila Documente este activă nu se solicită însemnări din API-ul profilului.
- Adaugă document din dosarul comun și din Acasă deschide încărcarea în profilul propriu. Documentele noi rămân private; apar în dosarul comun numai după partajare explicită cu familia. În profilul altei persoane nu se oferă încărcare în numele ei; rolul limitat păstrează restricția existentă.
- Legăturile Acasă deschid documentele comune în Familie → Documente, iar pe cele personale/selective în profilul proprietarului. Rezumatul Home adaugă numai `owner_id` din query-ul autorizat existent, fără query suplimentar sau schimbarea ACL. Categoriile Acasă deschid dosarul comun filtrat.
- Intrări: `App.tsx`, `Family.tsx`, `MemberProfile.tsx`, `profileData.ts`, `Documents.tsx`, CSS-urile lor, `Home.tsx`, `shared/api.ts` și `DocumentAccess::recent`. Fără migrații sau modificări ale API-ului de upload/partajare.
- **Verificat:** TypeScript/Vite build, Prettier, Pint, legături documentație și `git diff --check`; testul existent Home pentru rezumatele documentelor, actualizat cu proprietarul: **1 test / 16 aserțiuni**, trecut exclusiv pe `memoria_test`. Prima încercare în sandbox nu a putut citi autoloaderul; reluarea aceleiași comenzi cu acces permis a trecut. Nu s-au rulat suite, browser, capturi sau probe vizuale, conform cererii. Fluxurile interactive și aspectul final așteaptă feedback; buildul reușit nu confirmă acceptarea vizuală ori publicarea în producție.

Istoricul FP1/FD1 de mai jos descrie verificările anterioare. Următorul pas este feedbackul pentru separarea FD2, fără extinderea altor module.

## FP1 — Profilul membrului, 22 octombrie 2026

**Implementat și verificat local**, cerut explicit după Calendar. Numai Familia mea și profilul deschis la apăsarea fotografiei sau numelui unui membru, inclusiv propriul profil. Design inspirat de profilul Animale din referința MVP: fotografie circulară, identitate, patru file la vedere și rânduri compacte. Documente este o acțiune directă în cardul persoanei. Fără statistici fictive.

- Prezentare, Sănătate (vizite medicale/medicamente), Școală și cultură (cărți citite/activități culturale), Activități și acces la dosarul Documente existent. Însemnările au titlu, dată, detalii și acces; creare, editare cu versiune, arhivare/restaurare privată și paginare. Nu sunt recomandări medicale, scheme automate de tratament sau remindere noi.
- Utilizatorul a confirmat că „public” înseamnă **numai familia**, iar însemnările private ale celor de 14–17 ani rămân **numai ale adolescentului**. Privat înseamnă numai autorul, inclusiv pentru un părinte care scrie despre copil; alt părinte, persoana descrisă sau administratorul nu primesc implicit acces la privatele autorului.
- Data nașterii și maximum doi părinți activi sunt configurate de proprietarul familiei, cu versiune; proprietarul nu devine părinte implicit. Sub 14 ani scriu numai părinții desemnați; de la împlinirea a 14 ani scrie și persoana, inclusiv cu rol limitat; de la 18 ani numai persoana. Vârsta se recalculează pe server în România. Un părinte desemnat trebuie să aibă rol adult/proprietar și să nu fie înregistrat ca minor. Schimbarea rolului sau revocarea retrage dreptul de administrare.
- Profilurile sunt legate de **membrii cu cont existenți**. Profilurile fără cont și revendicarea/legarea confirmată a unui cont rămân F4/P1 separat; nu se inventează identități sau parole pentru copii. Data necunoscută nu etichetează automat un membru drept copil.
- Fotografia se încarcă prin PrivateMedia existent (scanare/recodare, 10 MB/16 MP), cu rută autorizată pe familia și membrul activ. Avatarul este vizibil numai familiei, separat de accesul însemnărilor. Politica documentelor rămâne cea FD1, fără încărcare în numele altuia.
- Backend: MemberProfileAccess/MemberProfileController în Platform/Families, trei tabele cu FK compuse și RLS forțat; TenantContext serializează mutațiile. FamilyController aduce avatarul/vârsta prin JOIN, fără N+1. Frontend: MemberProfile.tsx coordonează pagina, ProfileForms.tsx deține formularele, profileData.ts grupează tipurile/catalogul, iar memberProfile.css stilurile. Family.tsx și extensia Member integrează intrarea în profil. Detaliul însemnării reutilizează payloadul listei.
- Actualizare manuală și la 60 s cât pagina este vizibilă și nu se editează. Refreshul de fundal păstrează cardurile; detaliul altui autor este reconciliat cu lista autorizată, iar draftul propriu nu este suprascris. Schimbarea tipului la salvare duce în categoria corespunzătoare; restaurarea rămâne privată. Stilul comun icon-button.primary este corectat în styles.css.
- Migrația `2026_10_22_201929_create_member_profiles.php` este aplicată aditiv pe `memoria` și `memoria_test`, cu granturile runtime; fără resetarea bazei aplicației. Baza de teste a primit și migrațiile restante necesare schemei curente. TypeScript/Vite build și Pint trecute. `MemberProfileTest`: **7 teste / 67 de aserțiuni**, trecute numai pe `memoria_test`: ziua dinaintea pragului de 14 ani, ziua împlinirii, privatele față de părinte/proprietar, încetarea drepturilor la 18 ani, rol limitat/revocat (inclusiv eliminarea părintelui neeligibil din selector), versiuni/arhivare privată, familie greșită/părinte invalid și fotografie autorizată. Fără audit sau suite generale. Skillul `testing-best-practices` menționat de backend/AGENTS.md nu este disponibil local; testele urmează convențiile FamilyMembershipTest existente.
- Browser 390 px: profilul Anei are încărcare foto și adăugare, al lui Mihai este numai de consultare pentru Ana, cu administrarea datei/părinților separată. Cele patru file sunt vizibile fără derulare orizontală. Verificate formularele de sănătate, carte citită și fotografie; accesul implicit este privat. Configurarea unei date sub 14 ani afișează părinții eligibili și blochează salvarea fără unul; exemplul din formular a fost anulat, fără schimbarea vârstei lui Mihai. Documente din profilul lui Mihai deschide dosarul autorizat gol, fără încărcare în numele lui. Capturi ignorate: `.runtime/fp1-profile-mobile.png` și `.runtime/fp1-parent-settings-mobile.png` (draft nesalvat). Profilul Anei rămâne deschis pe Prezentare.
- Limite: fără profiluri fără cont, galerie, remindere de tratament sau integrare Calendar. Browserul nu a salvat însemnări, fotografii ori date de naștere în baza aplicației; salvarea și fotografia au probe în baza separată de teste. Nu s-au provocat carantina/respingerea foto, erori de rețea, revocări live, paginare mare sau dispozitive fizice. Intervalul automat de 60 s nu a avut probă dedicată. Acceptarea generală F4/P1/P2 nu este închisă de FP1. Urmează feedbackul pentru acest profil, nu alt modul.

## FD1 — Documente în Familie, 22 octombrie 2026

**Implementat și verificat local, cerere explicită de produs.** Documente este sub Familia mea; intrările principale Documente au fost eliminate de pe desktop și mobil. În Familia mea există Membri/Documente. Apăsarea numelui unui membru deschide dosarul său, separat de administrarea rolului/revocării.

Acesta este comportamentul inițial FD1. Din FP1, numele/fotografia deschid profilul, iar acțiunea Documente din profil deschide același dosar, cu aceleași permisiuni.

- Dosarul comun include documentele cu acces `family`. „Documentele mele” include documentele deținute de utilizator, inclusiv cele partajate. Dosarul altei persoane afișează numai documentele deja autorizate de API; proprietarul familiei nu primește acces la privatele altui adult. „Toate documentele” păstrează accesul unificat, inclusiv la partajări selective.
- Dosarele sunt vizualizări ale documentelor existente; nu mută bytes, nu dublează documente și nu schimbă proprietari/granturi. Încărcarea rămâne privată și readuce dosarul propriu după salvare. Nu se oferă încărcare în numele altui membru.
- App coordonează subsecțiunea și navigația spre Documents prin callback/`DocumentRequest.owner`; modulele nu își importă ecranele reciproc. Schimbarea familiei păstrează subsecțiunea Documente, remontează datele și golește ținta veche. Linkurile din Acasă rămân funcționale în noul context.
- Intrări: `App.tsx`, `Family.tsx`, `Documents.tsx`, `shared/api.ts` și CSS-urile aferente. Fără modificări backend/schema/permisiuni; fără suite de teste, numai build și probe vizuale proporționale.

### Verificare FD1

- Build TypeScript/Vite, Prettier și `git diff --check` trecute; fără teste noi/suite automate. Căutate referințele vechi ale paginii principale Documente și selectorii de titlu eliminați, fără cod rămas.
- Browser desktop: cinci secțiuni principale; numele Anei → Documentele mele (4 documente), dosarul comun → 2 documente; Mihai → dosar gol pentru Ana, fără expunerea documentului său privat și fără acțiune de încărcare în numele lui. Linkul Jurnal de călătorie din Acasă deschide detaliul privat în contextul Familia mea/Documente.
- Popescu → Ionescu → Popescu păstrează subsecțiunea Documente și reîncarcă dosarul comun: 2 → 1 → 2 documente. Fără documente din familia anterioară în noua listă. Nu s-au modificat documente sau granturi pentru probe.
- Previziualizare mobilă 390 px: Membri/Documente și bara cu cinci secțiuni plus Cont, fără tab principal Documente. Capturi ignorate: `.runtime/fd1-documents-desktop.png`, `.runtime/fd1-family-mobile.png`. Nu s-au reluat uploadul, concurența, revocările sau descărcarea bytes, ale căror contracte backend nu s-au schimbat; nu s-au verificat dispozitive fizice.

## Ce există

Selector de familie, membri activi, căutare, invitații prin link, acceptare cu cont nou sau existent, schimbare adult/acces limitat și revocare. Cardurile compacte și administrarea rolurilor sunt în ultima schimbare funcțională `e9ef880`.

## Fișiere relevante

- UI: `frontend/src/modules/families/Family.tsx`, `AcceptInvitation.tsx`, `family.css`.
- API și context: `backend/app/Platform/Families/FamilyController.php`, `InvitationController.php`, `TenantContext.php`.
- Contractul actual: `backend/routes/web.php`, `frontend/src/shared/api.ts`.
- Teste: `backend/tests/Feature/FamilyMembershipTest.php`, `FamilyInvitationTest.php`; interacțiunea cu documentele este în `DocumentSecurityTest.php`.

## Pași

- [x] **F1 — Verificarea paginii existente.** Verifică membrii, căutarea, meniul de administrare și stările fără rezultate pe mobil și desktop, cu proprietar și adult. Inventariază defectele concrete. Rezultat: listă scurtă de fluxuri verificate și prima corecție necesară; fără extinderea funcțiilor.
- [x] **F2 — Roluri și revocare.** Verifică schimbarea rolului, protejarea proprietarului, conflictul de rol învechit și efectul în sesiunea deja deschisă. Rulează testele de membership; completează doar lacunele de comportament identificate. Un membru limitat nu încarcă documente noi, dar păstrează accesul permis la cele existente.
- [x] **F3 — Invitații și acceptare.** Verifică creare, afișarea unică a linkului, anulare, expirare, cont greșit, cont nou și acceptare repetată. Rulează testele de invitații, verifică vizual formularul și închide defectele blocante din F1–F3.
- [ ] **F4 — Extinderea administrării familiei.** După A2–A3, stabilește separat regulile pentru creare/editare familie, profil fără cont și legarea lui confirmată la cont. Apoi tratează plecarea/reactivarea și transferul proprietății ca fluxuri distincte, cu audit și teste; nu transforma automat un membru revocat în membru activ.

## Acceptare M01

- Proprietarul gestionează membrii; ceilalți primesc numai acțiunile permise. Proprietarul nu poate fi retrogradat sau revocat prin acest flux.
- ID-uri din altă familie și membri revocați sunt respinși fără dezvăluirea datelor; sesiunile existente sunt reverificate.
- Invitația este limitată la familie/email/rol, expiră și poate fi folosită o singură dată. Tokenul nu apare în loguri sau audit.
- Mobilul nu are overflow; cardurile și controalele respectă AGENTS. Dialogurile au focus și confirmări clare pentru revocare.

## Verificare F1 — 21 octombrie 2026

- Browser local: Ana ca proprietar în Familia Popescu și ca adult în Familia Ionescu. Lista arată membrii și rolurile familiei selectate; adultul nu are butoane de adăugare sau administrare, iar proprietarul nu se poate administra prin propriul card.
- Căutare după nume, fără diferențiere între majuscule și minuscule, cu spații la capete; filtrare, „Niciun rezultat”, ștergere și resetare funcționale. Reîncărcarea listei prezintă starea de încărcare.
- Administrare: rolul actual este selectat, salvarea fără modificare este dezactivată, închiderea prin buton și Escape readuce focusul la butonul de administrare. Revocarea deschide confirmarea cu consecințe; anularea păstrează membrul și restabilește focusul. Nu s-au schimbat roluri și nu s-au revocat membri în baza locală.
- Desktop 1366 px și mobil 390/360 px: fără overflow orizontal în paginile verificate. Carduri de 88 px pe desktop și 80 px pe mobil; administrare 44 × 44 px, iar comenzile mobile de căutare și actualizare au minimum 44 px. Dialogul de administrare încape la 360 × 800 px.
- Build TypeScript/Vite reușit. Testele existente `FamilyMembershipTest` și cele două teste `DocumentSecurityTest` pentru revocare/sesiune și restricțiile adultului: **12 teste, 73 de aserțiuni, toate trecute**, pe `memoria_test`.
- Defecte observate în scopul F1: niciunul care să necesite o corecție de produs. Prima acțiune rămâne verificarea manuală F2; invitațiile și acceptarea M01 nu sunt încă verificate integral. Stările de eroare de rețea și lista fără niciun membru nu au fost simulate.

## Verificare F2 — 21 octombrie 2026

- Promovare și retrogradare în UI; un formular deschis în altă fereastră respinge rolul învechit cu 409 și cere reîncărcarea membrilor. Modificarea și revocarea proprietarului sunt respinse cu 422.
- Corecție observată la legătura Familie–Documente: uploadul deschis înainte de retrogradare era refuzat de server, dar mesajul gol ascundea eroarea. UI închide acum formularul, explică pierderea permisiunii și reîncarcă sesiunea. Membrul limitat păstrează documentele permise, inclusiv documentul său privat. Helperul API asigură un mesaj vizibil și pentru răspunsurile fără explicație.
- Revocare verificată într-o familie temporară: document deschis înainte de revocare, apoi metadata și descărcare refuzate cu 404; familia dispare din sesiunea existentă. Familia temporară și fișierul ei au fost eliminate. Mihai a fost readus la rolul adult în familia demo.
- Testele backend relevante: **12 teste / 73 de aserțiuni**, trecute; backendul nu a necesitat modificări. **7 teste frontend**, trecute, pentru mesajele API goale, conflict, validare și răspuns fără JSON. Build TypeScript/Vite reușit.
- Contractul folosit: modificarea rolului/revocarea este validată de API; UI reîncarcă sesiunea când accesul se schimbă. F2 nu închide verificările generale D1–D3 sau invitațiile F3. Nu repeta această suită la schimbări exclusiv vizuale fără o dependență relevantă.

## Verificare F3 — 21 octombrie 2026

- Browser local: Ana creează o invitație pentru Elena cu acces limitat; copierea funcționează, linkul este în fragmentul URL și nu reapare la redeschiderea formularului. Invitația apare în listă cu rolul și data expirării. Duplicatul, inclusiv emailul cu majuscule, primește explicația existentă.
- Cont greșit: Ana vede adresa invitată și „Schimbă contul”. După deconectare și autentificare cu Elena, acceptarea selectează Familia Popescu și elimină invitația din URL; Elena apare cu rol limitat. Linkurile anulate și deja acceptate afișează starea indisponibilă.
- Formularul pentru cont nou este verificat vizual, fără creare manuală de cont. Crearea contului, validarea parolei fără consumarea invitației, expirarea la șapte zile și acceptarea repetată sunt verificate prin testele backend. Nu s-a așteptat expirarea naturală în browser.
- Mobil 360 × 800: formularul de acceptare nu are overflow, butoanele au peste 44 px; fereastra de creare încape în ecran și păstrează etichetele și explicația rolului. Captură: `.runtime/f3-invitation-mobile.jpg` (ignorată de Git).
- Comandă din `backend`: `../.tools/php84/php.exe -d extension=pdo_pgsql -d extension=gd vendor/bin/phpunit tests/Feature/FamilyInvitationTest.php --testdox`. Rezultat: **13 teste / 103 aserțiuni, trecute**, pe `memoria_test`. F1/F2 nu au fost rerulate. Nicio modificare de produs necesară; buildul existent nu a fost refăcut pentru documentație.
- Cele trei invitații și apartenența temporară a Elenei în Familia Popescu au fost eliminate după verificare; conturile demo și apartenențele inițiale sunt păstrate. Nu există defect blocant cunoscut în F1–F3.
- Contract: proprietarul creează/anulează; linkul este returnat numai la creare, iar serverul păstrează hashul. Inspectarea și acceptarea trimit tokenul în corpul cererii; auditul primește ID-ul invitației. Acceptarea leagă emailul, familia și rolul invitației, creează sau autentifică utilizatorul și întoarce sesiunea. Nu include trimitere email sau verificarea adresei.

## Limite și decizii

Invitațiile actuale nu trimit email și nu verifică adresa. Linkul localhost funcționează numai local. Livrarea și verificarea sunt în [Autentificare](identity.md), fără duplicarea transportului de email aici.

Datele private ale unui membru nu sunt transferate proprietarului familiei prin revocare sau schimbare de rol. Regulile F4 se stabilesc înainte de implementarea acelui pas.
