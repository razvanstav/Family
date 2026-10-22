# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** Familie, **FP1-demo — date în profilul Anei**, salvate local. [Exemple și verificare](modules/families.md#fp1-demo--date-în-profil-23-octombrie-2026).
- **Rezultat:** în Familia Popescu → Ana Popescu sunt exact trei exemple noi: Control anual (21 octombrie), Micul Prinț (18 octombrie), Plimbare în parc (22 octombrie 2026). Apar în Prezentare și în categoriile lor. Toate au acces **Doar eu**, cu notițe demo. ID-urile sunt în fișa Familie; nu le duplica la reluare.
- **Permisiuni:** neschimbate; public = numai familia. Privatele altui membru nu devin vizibile și nu se încarcă în numele lui. Profilurile fără cont și asocierea documentelor părinte–copil rămân în afara FD2.
- **Verificat FP1-demo:** salvare prin API-ul existent și recitirea listei autorizate: trei ID-uri confirmate, acces privat și administrare de către Ana. Fără cod nou, seed/reset, build, teste sau probe vizuale. Aspectul final așteaptă feedbackul utilizatorului.
- **Pas anterior FD2:** Familie → Documente conține numai documentele partajate familiei; Documente din profil este dosar separat. Încărcările din Familie/Acasă deschid profilul propriu și rămân private până la partajare. Build și un test API (16 aserțiuni) trecute anterior, fără probe vizuale.
- **Limite/livrare:** build local, fără publicare în producție sau push. Verificarea automată anterioară a respins pushul fără acord explicit; nu îl reîncerca implicit. Nu există blocaj pentru dezvoltarea locală.
- **Context păstrat:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, Cont separat. FP1 și regulile confirmate de vârstă/confidențialitate sunt în fișa Familie; H2/KH1/FD1/MH1/CH1 rămân existente. Planificatorul este schiță. Evenimentele, amintirile și mesele demo existente nu au fost schimbate.

## Următoarea acțiune

Preia feedbackul pentru cele trei exemple din profilul Anei; nu începe automat alt modul/layout. La reluare citește acest rezumat, numai secțiunea FP1-demo din fișa Familie și codul necesar cererii. Nu reciti tot planul sau toate modulele. După fiecare pas actualizează aici rezultatul, verificările, limitele și următoarea acțiune.

## Mediu local

Windows, PHP 8.4 în `C:\Users\razva\php84\php.exe`, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste exclusiv pe `memoria_test`. Comenzile PHP pentru DB/media folosesc `-d extension=pdo_pgsql -d extension=gd`. Migrația FP1 și granturile sunt aplicate local; FD2 nu adaugă migrații. Buildul din `backend/public/app/` este ignorat. Pornire/opriere și replay în [SETUP](../ops/SETUP.md); nu reseta baza aplicației și nu dubla datele demo. Secretele/datele locale nu se afișează sau comit.
