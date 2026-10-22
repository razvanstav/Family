# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** [Auto V1a](modules/vehicles.md#v1a--ecranul-auto-23-octombrie-2026), implementat și verificat local; [UT2 — subcategorii profil](modules/interface.md#ut2--subcategorii-profil-23-octombrie-2026), corectat. Cerere nouă: repară subcategoriile din Membri, apoi Auto după telefonul Mașini din mock-up și pune tot pe Git.
- **Rezultat:** Auto are profil/fotografie, Prezentare/Revizii/Documente/Costuri, scadențe manuale, istoric, cost RON și kilometraj consemnat, asocierea documentelor existente, editare cu versiuni și arhivare/restaurare. Acces de familie, limited numai consultă; asocierea nu extinde accesul documentului. Fără date Auto inventate în aplicație.
- **Interfață:** subcategoriile profilului sunt un singur rând derulabil, fără suprapuneri, cu selecția adusă în zona vizibilă. Auto este singurul layout nou. Navigație: Acasă → Familia mea → Bucătărie → Amintiri → Calendar → Auto, identică desktop/mobil, Cont separat.
- **Migrație:** `2026_10_22_215356_create_vehicles_tables.php` aplicată aditiv pe `memoria_test` și `memoria`, cu granturi runtime. Fără reset/seed al aplicației sau modificarea documentelor existente. Alte medii trebuie să aplice migrația și granturile înainte de folosirea Auto.
- **Verificat:** TypeScript/Vite build, Prettier, Pint, rute și `git diff --check`. `VehicleTest`: **6 teste / 127 aserțiuni**, serial pe `memoria_test`; acces/izolare/revocare, versiuni, arhivare, date/costuri, paginare/filtre, fotografie și accesul documentului după asociere. Rezultatele și limitele sunt în fișa Auto.
- **Browser:** profil la 390 px, Auto la 320/390 și 1080/1440 px; fără overflow al paginii, controale/file de minimum 44 px, cadrul filelor păstrat. Cost salvat/arhivat și document asociat numai în serverul temporar 8001 pe `memoria_test`. Capturi locale ignorate: `.runtime/member-tabs-mobile.jpg`, `.runtime/auto-mobile.jpg`, `.runtime/auto-desktop.jpg`. Capturile Auto conțin date de test; acceptarea vizuală rămâne pentru feedback.
- **Limite:** V1a nu închide M09. Calendar/reminder/email pentru Auto, VIN, acces fin pe vehicul, alimentări în litri/consum și validarea/corectarea seriei de kilometraje rămân separate. Fără dispozitive fizice sau probe de indisponibilitate a scannerului. Fără publicare în producție.
- **Context păstrat:** F4a/FD3 (copii fără cont, dosare Casă/Amintiri și Personale/Sănătate), UT1 și KH2 rămân implementate. Nu dubla cele trei însemnări demo din profilul Anei. Planificatorul rămâne schiță.

## Următoarea acțiune

Preia feedbackul utilizatorului pentru Auto și subcategoriile profilului. Citește acest rezumat, fișa Auto V1a sau UT2 și codul relevant feedbackului. Nu începe alt layout și nu extinde automat Calendar/remindere/V2–V4. Commitul și pushul ramurii curente au fost autorizate explicit în cererea din 23 octombrie; deploy-ul rămâne separat.

## Mediu local

Windows, PHP 8.4 în `C:\Users\razva\php84\php.exe`, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste numai pe `memoria_test`, serial. CLI DB/media: `-d extension=pdo_pgsql -d extension=gd`. Buildul `backend/public/app/` este ignorat. Pornire/oprire: [SETUP](../ops/SETUP.md); nu reseta/seedui baza aplicației. Secretele/datele locale nu se afișează sau comit.
