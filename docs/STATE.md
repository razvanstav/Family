# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Bucătărie, **KH1 — layout implementat și verificat local, așteaptă feedback vizual**. Utilizatorul a acceptat Acasă și a cerut Bucătărie după referința mobilă. Lucrăm câte un layout și îl arătăm înainte de următorul.
- **Prioritate:** fără teste noi, suite automate sau verificări extinse. Pentru acest pas s-au făcut numai build, formatare/diff și o verificare scurtă în browser. Planificatorul se gândește separat, fără implementare implicită.
- **Rezultat KH1:** antet cu căutare/adăugare, Rețete/Mese, fotografii în rând derulabil pe mobil; „Vezi toate” duce la lista cu filtre, acces, arhivă și paginare. Mesele zilei sunt maximum trei rânduri compacte, cu fotografii și voturi reale, deschise prin MealViewer. Rețetele se deschid din payloadul complet existent. Backendul, schema și datele nu s-au schimbat.
- **Verificări KH1:** build TypeScript/Vite, Prettier și diff check trecute. Browser: grilă desktop, mobil 390 px cu fotografii, rețetă/ingrediente/preparare, masă/vot/comentariu existente, căutare „Paste” cu un rezultat și controale acces/arhivă disponibile. Nu s-au salvat rețete, mese sau voturi. Nu s-au reluat permisiuni, concurență, volum ori dispozitive fizice. Proba vizuală este `.runtime/kh1-kitchen-mobile.png`, ignorată de Git.
- **Planificator:** numai schiță în [Bucătărie](modules/kitchen.md#planificator-de-mese--schiță-neimplementat): săptămână și zi, rețetă/porții, intenție separată de masa gătită, confirmare explicită a preparării; cumpărăturile cer cantități/unități structurate. Nu există taburi inactive ori listă de cumpărături fictivă.
- **Pas anterior:** [Acasă H2](modules/interface.md#referință-pe-pagini--22-octombrie-2026) implementat/verificat local, cu feedback pozitiv al utilizatorului. Referință `artifacts/design-reference-family-mvp.png`; Helvetica și paleta caldă. Nu se redesenează din nou acum.
- **Restanțe funcționale:** Documente D4a salvat în `b9699b2`, cu acceptare parțială în [fișa Documente](modules/documents.md#d4a--expirarea-independentă-22-octombrie-2026). AM1–AM3 au fluxurile demo și limitele în [Amintiri](modules/memory.md). D4/C2, K3b și instalarea Linux nu redevin automat prioritare.
- **Livrare:** commit local pe `codex/documente-design`, fără push. Verificarea automată anterioară a respins publicarea către GitHub fără acord explicit. Nu reîncerca implicit; acest lucru nu blochează dezvoltarea sau demonstrația locală.

## Prima acțiune la reluare

Așteaptă și aplică feedbackul pe **Bucătărie KH1** din [fișa modulului](modules/kitchen.md#kh1--layout-bucătărie-22-octombrie-2026). Layoutul mobil este lăsat deschis la http://127.0.0.1:8000/preview/mobile. Nu începe altă pagină sau implementarea planificatorului fără noua alegere a utilizatorului.

Ordinea meniului rămâne Acasă → Familia mea → Documente → Bucătărie → Amintiri → Calendar, identică desktop/mobil; Acasă implicită. Bucătărie păstrează și `artifacts/kitchen-reference.png` ca referință secundară. Pregătirea Linux [O2a](../ops/SETUP.md#o2a--configurații-pentru-instalator) rămâne separată.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul în `backend/public/app/` este ignorat. Fără schimbări de dependențe.
- Aplicație http://127.0.0.1:8000/app/, previzualizare http://127.0.0.1:8000/preview/mobile, PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornire `.\ops\start-local.ps1`, oprire `.\ops\stop-local.ps1`. Workeri ascunși outbox/remindere la 15 s. Starea se reverifică la reluare.
- Baza aplicației este `memoria`; nu se resetează pentru verificări. `memoria_test` nu a fost migrată/rulată în pașii AM2/AM3/D4a/KH1. Secretele rămân în fișierele ignorate și nu se afișează.
- Popescu are două amintiri și o masă/vot fictive, trei referințe AM3; KH1 nu le-a modificat. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Comenzi rebuild/replay în [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe `memoria`; alt mediu cere migrații înainte de noul build. Datele demo, secretele și capturile din `.runtime/` nu intră în commit.
