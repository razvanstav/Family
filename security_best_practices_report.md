# Verificare de securitate Amora

25 octombrie 2026 · constatări SR1 și remediere SR2, inclusiv H6.

## Actualizare — remedieri SR2

**Ambele probleme sunt remediate, verificate și publicate.** Commit `6f51349`, [deploy 40210148504](https://github.com/razvanstav/Family/actions/runs/40210148504) completed/success cu SHA exact confirmat. HTTPS `/up`, `/app/`, JS `BFKMY26o` și CSS `CV-Buh5J` răspund 200; versiunea publică include și Activitatea de azi. Constatările de mai jos descriu comportamentul anterior corecțiilor. Confirmarea publicării nu include atacuri ori încercări de umplere a discului pe producție.

- Login: limita de șase cereri/minut pe IP este comună tuturor sesiunilor, plus zece cereri/minut pentru emailul normalizat vizat. Sesiunile deja autentificate primesc 409 înainte de verificarea parolei. Limitele expiră și păstrează `Retry-After` și explicații în română.
- Înscriere: limite comune pe IP de șase cereri/minut, zece/oră și 30/zi. Fluxul fără verificare obligatorie a emailului rămâne disponibil.
- Media: buget de 200 MiB pe IP într-un interval de 24 de ore de la prima scriere, comun tuturor conturilor și familiilor. Contorul cu cheie HMAC persistă în cache `file`, separat de tranzacțiile bazei; verificarea/scrierea/taxarea sunt serializate prin lock. O scriere eșuată nu consumă buget, iar anularea ulterioară a salvării ori respingerea nu îl resetează. Creșterea fișierului prin normalizare este taxată, inclusiv la retry.
- Spațiu liber: uploadurile sunt refuzate înainte de scriere dacă ar reduce rezerva sub 1 GiB sau spațiul nu poate fi determinat. Controalele existente de 10 MiB/fișier și 100 MiB/familie se păstrează.

Pragurile media sunt configurabile prin `MEDIA_UPLOAD_DAILY_IP_BYTES`, `MEDIA_MIN_FREE_BYTES` și `MEDIA_BUDGET_CACHE_STORE`, cu valori implicite sigure în cod și exemple în configurațiile distribuite. Nu au fost schimbate fișiere `.env` reale, date sau permisiuni.

**Verificare:** 56 teste backend / 635 aserțiuni trecute serial pe `memoria_test`, incluzând [LoginSecurityTest](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/tests/Feature/LoginSecurityTest.php), [RegistrationSecurityTest](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/tests/Feature/RegistrationSecurityTest.php), [UploadBudgetTest](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/tests/Unit/UploadBudgetTest.php), [MediaBudgetTest](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/tests/Feature/MediaBudgetTest.php), documentele/fotografiile, H6 și limitele/contractele modulare. 72 teste frontend și build TypeScript/Vite `BFKMY26o/CV-Buh5J` trecute. Pint/Prettier/diff și review fără blocaje trecute. Fixture-uri UUID proprii, fișiere fictive și curățare țintită; fără reset, SMTP sau atacuri pe producție. Protecțiile introduse nu sunt o probă a configurației Docker/WAF/cotei volumului de producție.

## Rezultat

Am identificat două probleme practice de severitate medie: multiplicarea limitei de login prin conturi proprii și consumul de stocare prin familii noi. Prima este reprodusă într-un test izolat; a doua rezultă din fluxul de înscriere și cotele implementate. Nu am identificat o cale concretă de acces neautorizat la datele altei familii, la fișiere private sau la parolele abonamentelor în aria verificată. Acesta este rezultatul verificării efectuate, nu o garanție că aplicația nu are alte vulnerabilități.

## Probleme de severitate medie

### 1. Limita încercărilor de login poate fi multiplicată prin sesiuni proprii

**ID:** AUTH-THROTTLE-001 · **Prioritate:** prima corecție · **Încredere:** confirmată prin test.

**Impact:** un atacator poate încerca mai multe parole pentru contul unei victime, de pe același IP, folosind sesiuni pentru conturi create de el. Crește riscul pentru parole uzuale sau refolosite; nu demonstrează spargerea unei parole aleatoare puternice.

**Dovezi:**

- [Ruta login](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/routes/web.php:37) are `throttle:6,1` și acceptă inclusiv o sesiune deja autentificată.
- [SessionController::login](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/app/Platform/Identity/SessionController.php:29) apelează `Auth::attempt` fără să respingă utilizatorul autentificat. Eșecul păstrează sesiunea existentă.
- În Laravel instalat, [ThrottleRequests::resolveRequestSignature](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php:224) folosește ID-ul utilizatorului autentificat, iar pentru guest folosește IP-ul. Schimbarea cookie-ului unui cont propriu schimbă astfel contorul.

**Confirmare:** testul temporar ignorat [SecurityLoginThrottleTest.php](/Users/razvanstavarache/Documents/ChatGPT/Amora/.runtime/SecurityLoginThrottleTest.php) a trecut cu 67 de aserțiuni. Din același IP, două identități autentificate și o sesiune guest au obținut fiecare câte șase răspunsuri `422` pentru parola greșită, înainte de propriul `429`: **18 verificări de parolă în loc de șase**. Eșecurile au păstrat identitatea autentificată. Au fost folosite numai trei conturi temporare în `memoria_test`, șterse țintit, și cache în memorie; fără încercări pe producție.

**Remediere:** limiter dedicat loginului, cu un contor pe IP care nu depinde de sesiune și un contor pentru emailul normalizat vizat. Respinge și loginul dintr-o sesiune deja autentificată. Limitele trebuie să păstreze o cale normală de reîncercare și să evite blocarea permanentă a victimei. Laravel permite combinarea mai multor limite cu chei distincte în [documentația oficială pentru rate limiting](https://laravel.com/framework/docs/13.x/routing#rate-limiting).

**Limită a concluziei:** un WAF extern poate reduce frecvența cererilor în producție; o astfel de regulă nu a fost confirmată. Mecanismul din aplicație este reprodus.

### 2. Bugetul de stocare poate fi extins prin înscrieri repetate

**ID:** RESOURCE-QUOTA-001 · **Prioritate:** după login · **Încredere:** confirmat în cod; efectul asupra capacității serverului nu a fost testat.

**Impact:** un atacator poate crea conturi/familii suplimentare și încărca fișiere valide în fiecare, consumând discul și resursele de scanare. Este un risc de disponibilitate, fără acces la datele altor utilizatori.

**Dovezi:**

- [RegistrationController](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/app/Platform/Identity/RegistrationController.php:43) creează o familie nouă și proprietarul ei la înscriere fără cod de invitație. Ruta publică este limitată pe minut, dar nu are un buget cumulat de familii/stocare pentru sursa înscrierilor.
- [Configurația media](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/config/memoria.php:5) permite 10 MiB per fișier și 100 MiB per familie.
- [LocalPrivateMedia::receiveFile](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/app/Platform/Media/LocalPrivateMedia.php:37) verifică numai suma din familia curentă, apoi scrie fișierul pe disc. Fișierele rămân în carantină când scanarea este indisponibilă, conform [procesării scanării](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/app/Platform/Media/LocalPrivateMedia.php:73).

O sută de familii oferă astfel aproximativ **9,8 GiB** de buget media. Limitele pe minut reduc viteza, dar nu consumul total. Nu am găsit un buget global pentru înscrieri/uploaduri sau un prag minim de spațiu liber în codul relevant inspectat.

**Remediere:** bugete cumulative pentru înscrieri și uploaduri, un buget inițial controlat pentru familiile noi și refuzul încărcărilor înainte de atingerea unui prag critic de spațiu liber. Verifică și cota volumului media pe server, astfel încât umplerea lui să nu oprească baza de date sau aplicația. Alegerea produsului de a nu impune verificarea emailului nu este raportată separat ca vulnerabilitate și nu trebuie schimbată implicit pentru această corecție.

**Limită a concluziei:** nu am umplut discul și nu am creat conturi pe producție. Cota efectivă a volumului și eventualele reguli externe anti-abuz nu sunt vizibile în checkout; acestea pot limita impactul.

## Controale verificate fără probleme concrete găsite

- Apartenența activă și contextul familiei sunt verificate server-side în [TenantContext](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/app/Platform/Families/TenantContext.php:20). Administratorul aplicației necesită simultan flagul din DB și adresa permisă în [AdministratorAccess](/Users/razvanstavarache/Documents/ChatGPT/Amora/backend/app/Platform/Administration/AdministratorAccess.php:10).
- Descărcarea documentelor reautorizează resursa. Fișierele sunt private, cu nume UUID, tip MIME verificat, scanare care blochează conținutul neverificat și imagini normalizate; nu am găsit un traseu de execuție de fișiere sau traversare de directoare.
- Abonamentele folosesc accesul canonic, criptare la stocare, acceptarea de către destinatar cu un cod separat și revocare. Lista și activitatea H6 nu selectează parolele.
- Frontendul nu folosește injectare HTML necontrolată, `eval`, tokenuri în Web Storage sau un service worker care să păstreze răspunsuri private. API-ul folosește aceeași origine și CSRF.
- GET-urile publice pentru `/app/` și `/api/v1/session` au confirmat CSP cu `script-src 'self'`, protecție anti-iframe, `nosniff`, `no-referrer`, cookie de sesiune `Secure; HttpOnly; SameSite=Lax` și API `no-store`. Valorile cookie-urilor nu au fost păstrate în raport.
- La verificare, `npm audit` a raportat zero vulnerabilități, iar `composer audit --locked` zero advisories și zero pachete abandonate. Aceste comenzi verifică vulnerabilitățile cunoscute din bazele lor de date, nu toate defectele posibile.

## Acoperire și limite

Verificarea a folosit [skillul security-best-practices](/Users/razvanstavarache/.codex/skills/security-best-practices/SKILL.md), referințele lui pentru React și JavaScript și inspecția codului PHP/Laravel. Skillul nu include un ghid dedicat PHP/Laravel; concluziile backend sunt susținute de implementarea instalată, documentația oficială și testul local.

Au fost analizate autentificarea, invitațiile, rolurile, izolarea familiilor, administrarea, media, abonamentele, frontendul, dependențele și configurația de deploy disponibilă. Configurația Docker efectivă, privilegiile serverului, backupurile și WAF-ul nu au fost inspectate independent. Nu s-au făcut scanări agresive sau atacuri pe producție. Reviewul SR1 a fost read-only; corecțiile ulterioare SR2 sunt descrise separat în actualizarea de la începutul raportului.
