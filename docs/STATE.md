# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Interfață, **H2 — Acasă după noua referință, în lucru**. Cererea nouă a utilizatorului este apropierea treptată a MVP-ului de imaginea furnizată, pagină cu pagină, folosind produsul existent. Începem cu Acasă; nu începem simultan redesenarea celorlalte pagini.
- **Rezultat urmărit:** acces rapid la module existente, amintiri cu fotografii reale, secțiuni compacte, activități și calendar funcționale; adaptare desktop/mobil, Helvetica și paleta caldă. Fără statistici, ratinguri, căutare globală ori module fictive.
- **Pas salvat înaintea schimbării priorității:** Documente D4a implementat; expirare opțională la upload și editare în detaliu, cu versiune. Migrația aplicată pe `memoria`, fără reset/seed. Build, Prettier, Pint, lint/rută și adăugare/ștergere dată pe document existent verificate desktop. Acceptarea rămasă este în [fișa Documente](modules/documents.md#d4a--expirarea-independentă-22-octombrie-2026); nu este închis D4 și nu există încă integrare Calendar.
- **Pas anterior:** AM1–AM3 implementate, cu fluxurile demo locale desktop/mobil. Rezultatele și limitele de acceptare sunt în [Amintiri](modules/memory.md). Nu se reconstruiește cronologia.
- **Verificări H2:** neîncepute. Fără teste noi sau suite automate, conform priorității anterioare; build, lint și probe manuale pentru fluxul modificat.
- **Livrare:** commituri locale pe `codex/documente-design`. Push-ul anterior a fost respins de verificarea automată de aprobare; lipsește acordul explicit de trimitere către GitHub. Cererea de continuare sau de redesign nu autorizează implicit push. Nu reîncerca.
- **Blocaje:** numai publicarea remote; dezvoltarea și demonstrația locală pot continua.

## Prima acțiune la reluare

Continuă H2 în [Interfață](modules/interface.md), după cererea vizuală nouă. Păstrează funcțiile reale existente și deschide resursele prin operațiile canonice autorizate. Înainte de încheiere salvează referința în repository și actualizează AGENTS/fișa/ROADMAP cu prioritatea.

Ordinea meniului rămâne Acasă → Familia mea → Documente → Bucătărie → Amintiri → Calendar, identică desktop/mobil; Acasă implicită. Bucătărie are și `artifacts/kitchen-reference.png`. Pregătirea Linux [O2a](../ops/SETUP.md#o2a--configurații-pentru-instalator) nu este noua prioritate.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul în `backend/public/app/` este ignorat. Fără schimbări de dependențe.
- Aplicație http://127.0.0.1:8000/app/, previzualizare http://127.0.0.1:8000/preview/mobile, PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornite prin `.\ops\start-local.ps1`; oprire `.\ops\stop-local.ps1`. Procese ascunse, workeri outbox/remindere la 15 s. Starea se reverifică la reluare.
- Baza aplicației este `memoria`; niciodată resetată pentru verificări. `memoria_test` nu a fost migrată/rulată în pașii AM2/AM3/D4a. Secretele sunt în fișiere ignorate și nu se afișează.
- Popescu are două amintiri și o masă/vot fictive, trei referințe AM3. Cronologia folosește `memory:rebuild-timeline --family=<UUID>` și `outbox:deliver --family=<UUID> --replay=memory.timeline.v1`. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Vezi [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe baza locală `memoria`. Pentru alt mediu trebuie migrate înainte de noul build. Nu s-au schimbat datele de acces, fișierele documentelor sau granturile.
- Datele demo, secretele și capturile din `.runtime/` nu intră în commit.
