# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Amintiri, **MH1 — layout mobil implementat și verificat local**, cerut explicit. Numai această pagină, în stilul Acasă/Bucătărie. Așteaptă feedbackul utilizatorului înainte de alt layout.
- **Rezultat:** fotografii pe lățimea cardului, note compacte, căutare din antet cu focus, filtrele Toate/Momente/Note la vedere și panou pentru acces/arhivă/actualizare. Ordinea după dată, autorul și accesul rămân vizibile. Detaliul folosește payloadul listei; refreshul de fundal păstrează cardurile. Cod modificat numai în `Memories.tsx` și `memory.css`.
- **Navigație:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, identică desktop/mobil. Acasă implicită, Cont separat. Documente rămâne în Familia mea; bara mobilă are șase controale în total. MH1 nu schimbă navigația aplicației.
- **Verificări MH1:** TypeScript/Vite build, Prettier și diff check trecute; fără teste noi/suite automate. Browser la 390 px: card foto și notă fără overflow observat, căutarea „bunici” și închiderea ei, panoul de filtre, deschiderea detaliului complet și cronologia cu cele două amintiri și masa existente. Pagina este deschisă pe Momente și note/Toate pentru revizuire.
- **Limite:** fără scrieri de date ori schimbări backend. Nu s-au reluat uploadul, concurența, revocările, filtrele de acces/arhivă cu mutații ori dispozitivele fizice. Refreshul periodic nu a avut așteptare dedicată. Acceptarea integrală AM1–AM3 rămâne deschisă conform fișei modulului.
- **Probe:** `.runtime/mh1-memories-mobile.png`, ignorată de Git; contracte, verificări și limite în [Amintiri MH1](modules/memory.md#mh1--layout-mobil-amintiri-22-octombrie-2026).
- **Pași anteriori:** Acasă H2 are feedback pozitiv; [Bucătărie KH1](modules/kitchen.md#kh1--layout-bucătărie-22-octombrie-2026) este implementat/verificat local, planificatorul numai schițat. [Familie FD1](modules/families.md#fd1--documente-în-familie-22-octombrie-2026) păstrează dosarul comun și documentele pe persoană. D4a are acceptare parțială; nu reîncepe implicit aceste module sau AM1–AM3.
- **Livrare:** commit local pe `codex/documente-design`, fără push. Verificarea automată anterioară a respins publicarea GitHub fără acord explicit; nu reîncerca implicit. Nu blochează dezvoltarea locală.

## Prima acțiune la reluare

Preia feedbackul utilizatorului pentru [MH1 — Amintiri](modules/memory.md#mh1--layout-mobil-amintiri-22-octombrie-2026). Nu începe alt layout, planificator sau suite de teste fără o cerere nouă. Pentru o ajustare, citește numai fișa MH1 și codul relevant.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul `backend/public/app/` este ignorat. Fără dependențe noi.
- PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornire `.\ops\start-local.ps1`, oprire `.\ops\stop-local.ps1`; workeri ascunși outbox/remindere la 15 s. Reverifică procesele la reluare.
- Baza aplicației `memoria` nu se resetează pentru verificări; `memoria_test` nu a fost rulată/migrată în pașii AM2/AM3/D4a/KH1/FD1/MH1. Secretele și datele demo rămân ignorate, fără afișare sau commit.
- Popescu are două amintiri și o masă/vot fictive, trei referințe AM3; MH1 nu le modifică. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Rebuild/replay în [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe `memoria`; alt mediu cere migrațiile înainte de noul build. MH1 nu are migrație sau schimbare de backend.
