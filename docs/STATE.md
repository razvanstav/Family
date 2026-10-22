# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** Familie, **FD2 — dosarul comun și documentele din profil**, implementat. [Contract și verificări](modules/families.md#fd2--dosarul-comun-și-documentele-din-profil-23-octombrie-2026).
- **Rezultat:** Familie → Documente afișează numai documentele partajate întregii familii. Profil → Documente este o filă separată lângă celelalte categorii, cu documentele autorizate ale persoanei. Selectorul comun/personal/toate și vechiul buton din card sunt eliminate. Adăugarea din Familie/Acasă deschide profilul propriu; uploadul rămâne privat până la partajare explicită. Legăturile Acasă păstrează contextul documentului.
- **Permisiuni:** neschimbate; public = numai familia. Privatele altui membru nu devin vizibile și nu se încarcă în numele lui. Profilurile fără cont și asocierea documentelor părinte–copil rămân în afara FD2.
- **Verificat FD2:** TypeScript/Vite build, Prettier, Pint, legături documentație și `git diff --check`; un singur test Home pentru rezumatele autorizate: **1 test / 16 aserțiuni**, trecut pe `memoria_test`. Fără suite generale, browser, capturi sau probe vizuale, conform cererii. Interacțiunile/aspectul final nu sunt declarate verificate în acest pas.
- **Limite/livrare:** build local, fără publicare în producție sau push. Verificarea automată anterioară a respins pushul fără acord explicit; nu îl reîncerca implicit. Nu există blocaj pentru dezvoltarea locală.
- **Context păstrat:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, Cont separat. FP1 și regulile confirmate de vârstă/confidențialitate sunt în fișa Familie; H2/KH1/FD1/MH1/CH1 rămân existente. Planificatorul este schiță. Evenimentele, amintirile și mesele demo existente nu au fost schimbate.

## Următoarea acțiune

Preia feedbackul pentru FD2; nu începe automat alt modul/layout. La reluare citește acest rezumat, numai secțiunea FD2 din fișa Familie și codul necesar cererii. Nu reciti tot planul sau toate modulele. După fiecare pas actualizează aici rezultatul, verificările, limitele și următoarea acțiune.

## Mediu local

Windows, PHP 8.4 în `C:\Users\razva\php84\php.exe`, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste exclusiv pe `memoria_test`. Comenzile PHP pentru DB/media folosesc `-d extension=pdo_pgsql -d extension=gd`. Migrația FP1 și granturile sunt aplicate local; FD2 nu adaugă migrații. Buildul din `backend/public/app/` este ignorat. Pornire/opriere și replay în [SETUP](../ops/SETUP.md); nu reseta baza aplicației și nu dubla datele demo. Secretele/datele locale nu se afișează sau comit.
