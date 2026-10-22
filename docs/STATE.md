# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Interfață, **H2 — Acasă implementat, verificat local; așteaptă feedback vizual**. Ultima instrucțiune explicită: terminăm un singur layout, îl arătăm utilizatorului și așteptăm feedback înainte de următorul. Nu începe altă pagină la reluare fără acest feedback.
- **Rezultat:** acces rapid la Amintiri/Bucătărie/Documente/Calendar, amintire cu fotografie privată reală și note compacte, categorii/documente existente, evenimente/remindere/calendar. Desktop în două coloane, mobil 390 px; Helvetica, crem și iconuri colorate. Noua referință este `artifacts/design-reference-family-mvp.png`, salvată în AGENTS și [Interfață](modules/interface.md#referință-pe-pagini--22-octombrie-2026). Navigația și prima pagină rămân cele existente. Fără numere, ratinguri sau module fictive.
- **Pas salvat înaintea schimbării priorității:** Documente D4a implementat; expirare opțională la upload și editare în detaliu, cu versiune. Migrația aplicată pe `memoria`, fără reset/seed. Build, Prettier, Pint, lint/rută și adăugare/ștergere dată pe document existent verificate desktop. Acceptarea rămasă este în [fișa Documente](modules/documents.md#d4a--expirarea-independentă-22-octombrie-2026); nu este închis D4 și nu există încă integrare Calendar.
- **Pas anterior:** AM1–AM3 implementate, cu fluxurile demo locale desktop/mobil. Rezultatele și limitele de acceptare sunt în [Amintiri](modules/memory.md). Nu se reconstruiește cronologia.
- **Verificări H2:** build TypeScript/Vite, Prettier, Pint, lint PHP și diff check trecute. Browser desktop: fotografie încărcată și detaliul complet deschis din card. Mobil 390 px: compoziție, deschidere formular Calendar și schimbarea Popescu → Ionescu → Popescu cu stare goală și date separate. API cu sesiuni demo independente: Ana 2 amintiri/1 fotografie, Mihai 1 notă de familie/zero private, Ionescu zero amintiri Popescu; accesul Mihai la Ionescu dă 404. Fără teste noi/suite automate. Capturi ignorate `.runtime/h2-home-desktop.png` și `.runtime/h2-home-mobile.png`.
- **Limite H2:** nu s-au provocat separat erori de rețea, revocare/grant retras în timpul citirii cardului sau limita peste trei amintiri; nu s-au verificat dispozitive fizice. Acceptarea vizuală aparține utilizatorului. Probe și contracte în fișa Interfață; nu s-a reluat integral acceptarea AM2/AM3.
- **Livrare:** commituri locale pe `codex/documente-design`. Push-ul anterior a fost respins de verificarea automată de aprobare; lipsește acordul explicit de trimitere către GitHub. Cererea de continuare sau de redesign nu autorizează implicit push. Nu reîncerca.
- **Blocaje:** numai publicarea remote; dezvoltarea și demonstrația locală pot continua.

## Prima acțiune la reluare

Așteaptă și aplică feedbackul utilizatorului pe **Acasă**, în [Interfață H2](modules/interface.md#referință-pe-pagini--22-octombrie-2026). Pagina este lăsată deschisă la http://127.0.0.1:8000/app/. Nu începe automat Bucătărie, Amintiri sau celelalte layouturi. După confirmarea acestui layout se alege următorul, câte unul. D4a/C2 și instalarea Linux nu redevin automat prioritare.

Ordinea meniului rămâne Acasă → Familia mea → Documente → Bucătărie → Amintiri → Calendar, identică desktop/mobil; Acasă implicită. Bucătărie are și `artifacts/kitchen-reference.png`. Pregătirea Linux [O2a](../ops/SETUP.md#o2a--configurații-pentru-instalator) nu este noua prioritate.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul în `backend/public/app/` este ignorat. Fără schimbări de dependențe.
- Aplicație http://127.0.0.1:8000/app/, previzualizare http://127.0.0.1:8000/preview/mobile, PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornite prin `.\ops\start-local.ps1`; oprire `.\ops\stop-local.ps1`. Procese ascunse, workeri outbox/remindere la 15 s. Starea se reverifică la reluare.
- Baza aplicației este `memoria`; niciodată resetată pentru verificări. `memoria_test` nu a fost migrată/rulată în pașii AM2/AM3/D4a. Secretele sunt în fișiere ignorate și nu se afișează.
- Popescu are două amintiri și o masă/vot fictive, trei referințe AM3. Cronologia folosește `memory:rebuild-timeline --family=<UUID>` și `outbox:deliver --family=<UUID> --replay=memory.timeline.v1`. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Vezi [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe baza locală `memoria`. Pentru alt mediu trebuie migrate înainte de noul build. Nu s-au schimbat datele de acces, fișierele documentelor sau granturile.
- Datele demo, secretele și capturile din `.runtime/` nu intră în commit.
