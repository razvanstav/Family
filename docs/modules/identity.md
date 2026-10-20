# Autentificare

Stare: login/logout, sesiune și CSRF implementate; contul nou poate fi creat prin invitație. Recuperarea parolei, livrarea emailului și verificarea adresei sunt planificate pentru M03.

## Fișiere relevante

- `backend/app/Platform/Identity/SessionController.php`, `PrivateResponse.php` și `backend/app/Models/User.php`.
- `frontend/src/app/App.tsx`, `frontend/src/shared/api.ts`, `backend/routes/web.php`.
- Cont din invitație: `backend/app/Platform/Families/InvitationController.php` și `frontend/src/modules/families/AcceptInvitation.tsx`.
- Testele existente pentru sesiune, CSRF și cont invitat sunt în `DocumentSecurityTest.php` și `FamilyInvitationTest.php`.

## Pași

- [ ] **A1 — Sesiunea existentă.** Verifică autentificare corectă/greșită, logout, expirare, CSRF, revenire în tab și pierderea accesului la familia activă. UI golește datele familiei precedente și tratează sesiunea expirată fără a păstra conținutul privat pe ecran.
- [ ] **A2 — Recuperarea parolei.** Implementează solicitare, token cu expirare și folosire unică, parolă nouă și comportamentul sesiunilor vechi. Răspunsul solicitării nu confirmă existența unei adrese; verifică rate limiting și retry. Folosește inbox local înaintea transportului real.
- [ ] **A3 — Email verificat și invitație livrată.** Configurează expeditorul și linkurile HTTPS, apoi verificarea adresei și retrimiterea controlată. Tratează separat acceptarea invitației și dovada controlului emailului; nu marca adresa verificată doar pentru că tokenul de invitație a fost acceptat. Arată starea trimiterii și eșecul fără a dubla invitația.

## Acceptare

- Web folosește cookie HttpOnly și CSRF; nu introduce tokenuri de sesiune în localStorage.
- Sesiunea este regenerată la login, iar logoutul elimină contextul și datele private din memoria UI.
- Tokenurile de recuperare/verificare sunt expirabile, protejate și absente din audit și loguri; linkul deschis nu conferă singur acces la datele familiei.
- Erorile, încărcarea și retrimiterea sunt clare și accesibile. Butoanele demo și fixture-urile rămân exclusiv locale.

## Dependențe și decizii

A1 este local. A2–A3 au nevoie de decizia privind emailul, expeditorul și adresa publică înaintea livrării reale. Nu se aleg servicii plătite sau conturi în această etapă de planificare.

După A3 urmează F4 din [Familie](families.md). Autentificarea nativă și stocarea tokenurilor pe dispozitive rămân în [Mobil](mobile.md).
