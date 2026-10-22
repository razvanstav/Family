# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** Interfață, **UT1 — cadru stabil la schimbarea filelor**, implementat. [Contract și verificări](modules/interface.md#ut1--cadru-stabil-la-schimbarea-filelor-23-octombrie-2026).
- **Rezultat:** antet cu înălțime stabilă și bară comună SectionTabs în Bucătărie, Familie, profil, Amintiri și Calendar. Animația de pagină se execută numai la navigația principală/context, nu la schimbarea filelor. Familia mea păstrează titlul și pe Documente. Bucătărie păstrează filtrele rețetelor și data/pagina meselor; Mese nu solicită date cât este ascunsă. Nu s-au redesenat conținuturile modulelor.
- **Permisiuni:** neschimbate; public = numai familia. Privatele altui membru nu devin vizibile și nu se încarcă în numele lui. Profilurile fără cont și asocierea documentelor părinte–copil rămân în afara FD2.
- **Verificat UT1:** TypeScript/Vite build, Prettier, `git diff --check` și căutare de referințe pentru selectorii eliminați. Fără teste noi, suite sau probe vizuale, conform preferinței utilizatorului. Aspectul și interacțiunile așteaptă feedback; nu sunt declarate verificate în browser.
- **Pas anterior FD2:** Familie → Documente conține numai documentele partajate familiei; Documente din profil este dosar separat. Încărcările din Familie/Acasă deschid profilul propriu și rămân private până la partajare. Build și un test API (16 aserțiuni) trecute anterior, fără probe vizuale.
- **Limite/livrare:** build local, fără publicare în producție sau push. Verificarea automată anterioară a respins pushul fără acord explicit; nu îl reîncerca implicit. Nu există blocaj pentru dezvoltarea locală.
- **Context păstrat:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, Cont separat. FP1 și regulile confirmate de vârstă/confidențialitate sunt în fișa Familie; H2/KH1/FD1/MH1/CH1 rămân existente. Planificatorul este schiță. Datele demo nu au fost schimbate: Ana are Control anual, Micul Prinț și Plimbare în parc, toate private; ID-urile sunt în secțiunea FP1-demo din fișa Familie. Nu le duplica.

## Următoarea acțiune

Preia feedbackul pentru Rețete/Mese și cadrul comun al filelor; nu începe automat alt modul/layout. La reluare citește acest rezumat, numai secțiunea UT1 din fișa Interfață și codul necesar cererii. Nu reciti tot planul sau toate modulele. După fiecare pas actualizează aici rezultatul, verificările, limitele și următoarea acțiune.

## Mediu local

Windows, PHP 8.4 în `C:\Users\razva\php84\php.exe`, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste exclusiv pe `memoria_test`. Comenzile PHP pentru DB/media folosesc `-d extension=pdo_pgsql -d extension=gd`. Migrația FP1 și granturile sunt aplicate local; FD2 nu adaugă migrații. Buildul din `backend/public/app/` este ignorat. Pornire/opriere și replay în [SETUP](../ops/SETUP.md); nu reseta baza aplicației și nu dubla datele demo. Secretele/datele locale nu se afișează sau comit.
