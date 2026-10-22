# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Familie, **FD1 — Documente în Familie implementat și verificat local**, la cererea explicită a utilizatorului. Așteaptă feedback înainte de alt layout.
- **Rezultat:** Documente este subsecțiune în Familia mea, alături de Membri. Numele membrului deschide dosarul său. Dosarul comun include accesul `family`; dosarul propriu include documentele deținute, inclusiv cele partajate. Dosarele altor persoane arată numai documentele deja autorizate de API. „Toate documentele” păstrează accesul unificat și partajările selective. Nu s-au mutat bytes sau schimbat proprietari/granturi.
- **Navigație:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, identică desktop/mobil. Acasă implicită, Cont separat. Documente nu mai are tab principal; bara mobilă are șase controale în total. Linkurile din Acasă deschid documentele în noul context. Schimbarea familiei păstrează subsecțiunea și remontează datele prin cheia cont/familie.
- **Verificări FD1:** build TypeScript/Vite, Prettier și diff check trecute; fără teste noi/suite automate, conform cererii utilizatorului. Browser desktop: dosar propriu Ana 4 documente, comun 2, dosar Mihai gol pentru Ana/fără încărcare în numele lui; Jurnal de călătorie deschis din Acasă. Schimbare Popescu → Ionescu → Popescu cu Documente păstrată și 2 → 1 → 2 documente comune. Mobil 390 px: subsecțiuni și navigație scurtată, fără overflow observat. Fără scrieri de documente, granturi sau membri.
- **Limite:** nu s-au reluat uploadul, descărcarea bytes, concurența, revocările ori dispozitivele fizice; contractele backend sunt neschimbate. Documentele noi rămân private și apar după salvare în dosarul propriu; partajarea familială rămâne explicită. Nu există profiluri fără cont sau proprietar generic de familie nou în schema documentelor.
- **Probe:** `.runtime/fd1-documents-desktop.png` și `.runtime/fd1-family-mobile.png`, ignorate de Git. Contracte și rezultate în [Familie FD1](modules/families.md#fd1--documente-în-familie-22-octombrie-2026) și [Documente](modules/documents.md).
- **Pași anteriori:** Acasă H2 are feedback pozitiv; Bucătărie KH1 este implementat/verificat local, cu planificator numai schițat în fișa [Bucătărie](modules/kitchen.md). FD1 nu schimbă layoutul acestor pagini. D4a are acceptare parțială, AM1–AM3 își păstrează probele/limitele în fișele proprii; nu le reîncepe implicit.
- **Livrare:** commit local pe `codex/documente-design`, fără push. Verificarea automată anterioară a respins publicarea GitHub fără acord explicit; nu reîncerca implicit. Nu blochează dezvoltarea locală.

## Prima acțiune la reluare

Aplică feedbackul utilizatorului pe **Familie FD1**. Pagina locală este http://127.0.0.1:8000/app/, cu Familia mea → Documente; previzualizare http://127.0.0.1:8000/preview/mobile. Nu începe automat alt layout, planificatorul, D4/C2 ori instalarea Linux. Menține cererea fără suite automate/audituri extinse.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul `backend/public/app/` este ignorat. Fără dependențe noi.
- PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornire `.\ops\start-local.ps1`, oprire `.\ops\stop-local.ps1`; workeri ascunși outbox/remindere la 15 s. Reverifică procesele la reluare.
- Baza aplicației `memoria` nu se resetează pentru verificări; `memoria_test` nu a fost rulată/migrată în pașii AM2/AM3/D4a/KH1/FD1. Secretele și datele demo rămân ignorate, fără afișare sau commit.
- Popescu are două amintiri și o masă/vot fictive, trei referințe AM3; FD1 nu le modifică. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Rebuild/replay în [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe `memoria`; alt mediu cere migrațiile înainte de noul build. FD1 nu are migrație sau schimbare de backend.
