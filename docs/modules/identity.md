# Autentificare

Stare: **A1 implementat și verificat local la 21 octombrie 2026.** Login/logout, sesiune și CSRF funcționale; contul nou poate fi creat prin invitație. A2–A3 rămân planificate în M03.

## Fișiere relevante

- `backend/app/Platform/Identity/SessionController.php`, `PrivateResponse.php` și `backend/app/Models/User.php`.
- `frontend/src/app/App.tsx`, `sessionLifecycle.ts`, `frontend/src/shared/api.ts`, `backend/routes/web.php`.
- Cont din invitație: `backend/app/Platform/Families/InvitationController.php` și `frontend/src/modules/families/AcceptInvitation.tsx`.
- Testele existente pentru sesiune, CSRF și cont invitat sunt în `DocumentSecurityTest.php` și `FamilyInvitationTest.php`; regresiile A1 sunt în `frontend/tests/session.test.mjs`.

## Pași

- [x] **A1 — Sesiunea existentă.** Verifică autentificare corectă/greșită, logout, expirare, CSRF, revenire în tab și pierderea accesului la familia activă. UI golește datele familiei precedente și tratează sesiunea expirată fără a păstra conținutul privat pe ecran.
- [ ] **A2 — Recuperarea parolei.** Implementează solicitare, token cu expirare și folosire unică, parolă nouă și comportamentul sesiunilor vechi. Răspunsul solicitării nu confirmă existența unei adrese; verifică rate limiting și retry. Folosește inbox local înaintea transportului real.
- [ ] **A3 — Email verificat și invitație livrată.** Configurează expeditorul și linkurile HTTPS, apoi verificarea adresei și retrimiterea controlată. Tratează separat acceptarea invitației și dovada controlului emailului; nu marca adresa verificată doar pentru că tokenul de invitație a fost acceptat. Arată starea trimiterii și eșecul fără a dubla invitația.

## Acceptare

- Web folosește cookie HttpOnly și CSRF; nu introduce tokenuri de sesiune în localStorage.
- Sesiunea este regenerată la login, iar logoutul elimină contextul și datele private din memoria UI.
- Tokenurile de recuperare/verificare sunt expirabile, protejate și absente din audit și loguri; linkul deschis nu conferă singur acces la datele familiei.
- Erorile, încărcarea și retrimiterea sunt clare și accesibile. Butoanele demo și fixture-urile rămân exclusiv locale.

## Verificare A1 — 21 octombrie 2026

- Reprodus înainte de corecție: logoutul cu sesiunea expirată lăsa fereastra contului și datele private pe ecran; eroarea era în spatele dialogului. Răspunsurile 401/419 sunt acum tratate central, înaintea citirii corpului erorii: tokenul CSRF din memorie se golește și App retrage modulele private până la verificarea sesiunii. Cererea care a eșuat nu este retrimisă automat.
- App suspendă modulele când documentul devine ascuns și verifică sesiunea înainte să le monteze la revenire. Dacă verificarea eșuează, rămâne ecranul de eroare cu „Reîncearcă”, fără module private. Răspunsurile de sesiune depășite sunt ignorate. Lista și detaliul se remontează după identitatea utilizatorului și familiei. Documente/Familie nu mai declanșează separat încărcări concurente la revenire.
- Login/logout și acceptarea unei invitații anunță celelalte ferestre prin `BroadcastChannel`, folosind numai mesajul `changed`, fără identitate, tokenuri sau date private. Ferestrele recitesc sesiunea de la server. Browserele fără această facilitate păstrează verificarea la revenire și la 401/419. Nu s-a adăugat stocare de tokenuri în localStorage.
- Browser local: parolă greșită → eroare clară; login Ana → patru documente. Expirarea a fost provocată prin îmbătrânirea fișierului sesiunii demo peste cele 60 de minute configurate: logoutul produce acum ecranul de autentificare, fără carduri sau fereastra contului. Testul nu a modificat durata configurată a sesiunilor.
- Două ferestre ale aceluiași browser: logoutul din una închide documentul privat al lui Mihai din cealaltă. Autentificarea Anei recitește cele patru documente, fără documentul sau dialogul lui Mihai. Verificat și logoutul cu serverul HTTP oprit temporar: eroare vizibilă în dialog, buton disponibil pentru retry; după repornire logoutul reușește. Titlul tabului arată „Autentificare” în ecranul de login.
- **7 teste frontend A1 trecute** cu `node --test frontend/tests/session.test.mjs`: invalidare 401/419 înaintea corpului răspunsului, token reînnoit și fără repetarea mutației; evitarea recursiei când `/session` eșuează; păstrarea sesiunii la 403/404/412/422; dezabonare; suspendare/revalidare la schimbarea vizibilității; restaurare din istoricul browserului. Revenirea și `pageshow.persisted` sunt verificate prin evenimente simulate în testele automate: browserul integrat a raportat ambele taburi ca `visible`, deci nu este pretinsă o verificare manuală reală a ascunderii tabului sau a cache-ului de istoric.
- **9 verificări HTTP locale trecute**, cu cookie jar separat: sesiune guest fără utilizator, cookie HttpOnly/SameSite=Lax, no-store, login fără CSRF refuzat cu 419, login valid, rotația CSRF, cookie-ul anterior loginului fără acces autentificat, logout fără utilizator/familii și invalidarea cookie-ului anterior logoutului. Script ignorat de Git: `.runtime/a1-http-check.ps1`; fișierele temporare cu cookie-uri au fost eliminate. Backendul nu a necesitat modificări.
- Build TypeScript/Vite și `git diff --check` trecute. Captură: `.runtime/a1-session-ended.jpg`. Revocarea familiei, schimbarea familiei și accesul la documente se preiau din F2 și D1–D2; suitele respective și D3 nu au fost reluate. Nu s-au modificat documente, apartenențe sau parole; aplicația rămâne pornită cu Ana.

## Dependențe și următorul pas

A1 este local. A2–A3 au nevoie de decizia privind emailul, expeditorul și adresa publică înaintea livrării reale. Nu se aleg servicii plătite sau conturi în această etapă de planificare.

După A3 urmează F4 din [Familie](families.md). Autentificarea nativă și stocarea tokenurilor pe dispozitive rămân în [Mobil](mobile.md).
