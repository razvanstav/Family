# Familie

Stare: funcțiile de bază există local. **F1 este punctul de reluare pentru 22 octombrie 2026.** F1–F3 sunt acceptarea M01; F4 se face după Autentificare, în M03.

## Ce există

Selector de familie, membri activi, căutare, invitații prin link, acceptare cu cont nou sau existent, schimbare adult/acces limitat și revocare. Cardurile compacte și administrarea rolurilor sunt în ultima schimbare funcțională `e9ef880`.

## Fișiere relevante

- UI: `frontend/src/modules/families/Family.tsx`, `AcceptInvitation.tsx`, `family.css`.
- API și context: `backend/app/Platform/Families/FamilyController.php`, `InvitationController.php`, `TenantContext.php`.
- Contractul actual: `backend/routes/web.php`, `frontend/src/shared/api.ts`.
- Teste: `backend/tests/Feature/FamilyMembershipTest.php`, `FamilyInvitationTest.php`; interacțiunea cu documentele este în `DocumentSecurityTest.php`.

## Pași

- [ ] **F1 — Verificarea paginii existente.** Verifică membrii, căutarea, meniul de administrare și stările fără rezultate pe mobil și desktop, cu proprietar și adult. Inventariază defectele concrete. Rezultat: listă scurtă de fluxuri verificate și prima corecție necesară; fără extinderea funcțiilor.
- [ ] **F2 — Roluri și revocare.** Verifică schimbarea rolului, protejarea proprietarului, conflictul de rol învechit și efectul în sesiunea deja deschisă. Rulează testele de membership; completează doar lacunele de comportament identificate. Un membru limitat nu încarcă documente noi, dar păstrează accesul permis la cele existente.
- [ ] **F3 — Invitații și acceptare.** Verifică creare, afișarea unică a linkului, anulare, expirare, cont greșit, cont nou și acceptare repetată. Rulează testele de invitații, verifică vizual formularul și închide defectele blocante din F1–F3.
- [ ] **F4 — Extinderea administrării familiei.** După A2–A3, stabilește separat regulile pentru creare/editare familie, profil fără cont și legarea lui confirmată la cont. Apoi tratează plecarea/reactivarea și transferul proprietății ca fluxuri distincte, cu audit și teste; nu transforma automat un membru revocat în membru activ.

## Acceptare M01

- Proprietarul gestionează membrii; ceilalți primesc numai acțiunile permise. Proprietarul nu poate fi retrogradat sau revocat prin acest flux.
- ID-uri din altă familie și membri revocați sunt respinși fără dezvăluirea datelor; sesiunile existente sunt reverificate.
- Invitația este limitată la familie/email/rol, expiră și poate fi folosită o singură dată. Tokenul nu apare în loguri sau audit.
- Mobilul nu are overflow; cardurile și controalele respectă AGENTS. Dialogurile au focus și confirmări clare pentru revocare.

## Limite și decizii

Invitațiile actuale nu trimit email și nu verifică adresa. Linkul localhost funcționează numai local. Livrarea și verificarea sunt în [Autentificare](identity.md), fără duplicarea transportului de email aici.

Datele private ale unui membru nu sunt transferate proprietarului familiei prin revocare sau schimbare de rol. Regulile F4 se stabilesc înainte de implementarea acelui pas.
