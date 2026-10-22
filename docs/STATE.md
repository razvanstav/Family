# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Calendar, **CH1 — layout mobil implementat, cu verificare locală limitată**, cerut după MH1, conform noii imagini Calendar/Agendă. Așteaptă feedbackul utilizatorului înainte de alt layout.
- **Rezultat:** Lună/Săptămână/Agendă, antet compact cu căutare/adăugare, grilă cu selecție circulară, selector de șapte zile, filtre pe acces și opțiuni Active/Arhivate. Cardurile au accente după accesul real; agenda are ore pe axă verticală. Săptămânile peste lună încarcă în paralel cele două luni prin API-ul existent. Editorul și notificările păstrează contractele C1/C3. CalendarMonth are o extensie vizuală opțională, varianta Acasă rămâne existentă.
- **Navigație:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, identică desktop/mobil. Acasă implicită, Cont separat. Documente rămâne în Familia mea; bara mobilă are șase controale în total. CH1 nu schimbă navigația aplicației.
- **Verificări CH1:** TypeScript/Vite build, Prettier și diff check trecute. Browser 390 px: cele trei vizualizări, selecția zilei, săptămâna 26 octombrie–1 noiembrie, opțiunile Active/Arhivate, deschiderea căutării cu focus și formular cu data selectată. Fără overflow observat. Randare punctuală în Node a listelor populate și verificare date peste an/DST, cu date numai în memorie. Fără fișiere de teste noi sau suite extinse. Pagina este deschisă pe Lună/22 octombrie pentru revizuire.
- **Limite:** calendarul actual Popescu este gol; cardurile populate și punctele nu au probă vizuală în browser. Nu s-au scris date și nu s-au reluat salvarea/arhivarea, permisiunile, conflictele, notificările, erorile forțate sau dispozitivele fizice. Backend neschimbat; C2 și reconcilierea C4 rămân deschise.
- **Probe:** `.runtime/ch1-calendar-mobile.png` și `.runtime/ch1-agenda-mobile.png`, ignorate de Git; referința furnizată este salvată în `artifacts/design-reference-calendar.png`. Contracte/verificări în [Calendar CH1](modules/calendar.md#ch1--calendar-mobil-22-octombrie-2026).
- **Pași anteriori:** Acasă H2 are feedback pozitiv; [Bucătărie KH1](modules/kitchen.md#kh1--layout-bucătărie-22-octombrie-2026), [Familie FD1](modules/families.md#fd1--documente-în-familie-22-octombrie-2026) și [Amintiri MH1](modules/memory.md#mh1--layout-mobil-amintiri-22-octombrie-2026) sunt implementate local. Planificatorul este numai schițat. D4a și AM1–AM3 își păstrează limitele; nu le reîncepe implicit.
- **Livrare:** commit local pe `codex/documente-design`, fără push. Verificarea automată anterioară a respins publicarea GitHub fără acord explicit; nu reîncerca implicit. Nu blochează dezvoltarea locală.

## Prima acțiune la reluare

Preia feedbackul utilizatorului pentru [CH1 — Calendar](modules/calendar.md#ch1--calendar-mobil-22-octombrie-2026). Nu începe alt layout, planificator sau suite de teste fără o cerere nouă. Pentru ajustări, citește numai fișa CH1 și codul relevant. Cardurile populate mai au nevoie de o probă vizuală când există evenimente în calendar; nu adăuga evenimente fictive în datele aplicației implicit.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul `backend/public/app/` este ignorat. Fără dependențe noi.
- PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornire `.\ops\start-local.ps1`, oprire `.\ops\stop-local.ps1`; workeri ascunși outbox/remindere la 15 s. Reverifică procesele la reluare.
- Baza aplicației `memoria` nu se resetează pentru verificări; `memoria_test` nu a fost rulată/migrată în pașii AM2/AM3/D4a/KH1/FD1/MH1/CH1. Secretele și datele demo rămân ignorate, fără afișare sau commit.
- Popescu are două amintiri și o masă/vot fictive, trei referințe AM3; CH1 nu le modifică. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Rebuild/replay în [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe `memoria`; alt mediu cere migrațiile înainte de noul build. CH1 nu are migrație sau schimbare de backend.
