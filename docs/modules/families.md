# Familie

Stare: **F1–F3 verificate la 21 octombrie 2026; M01 închis local.** FD1 reorganizează Documente în interiorul Familiei la cererea utilizatorului din 22 octombrie 2026. F4 administrare extinsă rămâne separat.

## FD1 — Documente în Familie, 22 octombrie 2026

**Implementat și verificat local, cerere explicită de produs.** Documente este sub Familia mea; intrările principale Documente au fost eliminate de pe desktop și mobil. În Familia mea există Membri/Documente. Apăsarea numelui unui membru deschide dosarul său, separat de administrarea rolului/revocării.

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
