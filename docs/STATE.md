# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** Bucătărie, **KH2 — carduri Mese în stilul Rețete**, implementat. [Contract și verificări](modules/kitchen.md#kh2--carduri-mese-23-octombrie-2026).
- **Rezultat:** Rețete și Mese reutilizează KitchenCard și CSS-ul existent pentru fotografie mare, titlu, margini, padding și etichete. Mese are grilă desktop și rând derulabil pe mobil ca prezentarea Rețete. Păstrează voturile reale, tipul mesei, accesul și detaliile existente. Rezumatul Mesele de azi rămâne compact; alte secțiuni nu au fost modificate.
- **Permisiuni:** neschimbate; public = numai familia. Privatele altui membru nu devin vizibile și nu se încarcă în numele lui. Profilurile fără cont și asocierea documentelor părinte–copil rămân în afara FD2.
- **Verificat KH2:** TypeScript/Vite build, Prettier, `git diff --check` și căutare de referințe pentru selectorii de rând eliminați. Fără teste noi, suite sau probe vizuale, conform preferinței utilizatorului. Aspectul și interacțiunile așteaptă feedback; nu sunt declarate verificate în browser.
- **Pas anterior UT1:** cadrul comun al filelor și animația numai la navigația principală sunt păstrate. Bucătărie păstrează filtrele rețetelor și data/pagina meselor; fila Mese ascunsă nu solicită date. FD2 și separarea documentelor rămân existente.
- **Limite/livrare:** build local, fără publicare în producție sau push. Verificarea automată anterioară a respins pushul fără acord explicit; nu îl reîncerca implicit. Nu există blocaj pentru dezvoltarea locală.
- **Context păstrat:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, Cont separat. FP1 și regulile confirmate de vârstă/confidențialitate sunt în fișa Familie; H2/KH1/FD1/MH1/CH1 rămân existente. Planificatorul este schiță. Datele demo nu au fost schimbate: Ana are Control anual, Micul Prinț și Plimbare în parc, toate private; ID-urile sunt în secțiunea FP1-demo din fișa Familie. Nu le duplica.

## Următoarea acțiune

Preia feedbackul pentru cardurile Mese comparate cu Rețete; nu începe automat alt modul/layout. La reluare citește acest rezumat, numai secțiunea KH2 din fișa Bucătărie și codul necesar cererii. Nu reciti tot planul sau toate modulele. După fiecare pas actualizează aici rezultatul, verificările, limitele și următoarea acțiune.

## Mediu local

Windows, PHP 8.4 în `C:\Users\razva\php84\php.exe`, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste exclusiv pe `memoria_test`. Comenzile PHP pentru DB/media folosesc `-d extension=pdo_pgsql -d extension=gd`. Migrația FP1 și granturile sunt aplicate local; FD2 nu adaugă migrații. Buildul din `backend/public/app/` este ignorat. Pornire/opriere și replay în [SETUP](../ops/SETUP.md); nu reseta baza aplicației și nu dubla datele demo. Secretele/datele locale nu se afișează sau comit.
