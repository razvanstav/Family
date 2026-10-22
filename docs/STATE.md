# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Calendar, **CH1 — layout mobil implementat și verificat local cu demo**, cerut după MH1. Ultima cerere a fost popularea calendarului pentru revizuirea lunii și agendei. Așteaptă feedback înainte de alt layout.
- **Rezultat:** Lună/Săptămână/Agendă, antet compact cu căutare/adăugare, grilă cu selecție circulară, selector de șapte zile, filtre pe acces și opțiuni Active/Arhivate. Cardurile au accente după accesul real; agenda are ore pe axă verticală. Săptămânile peste lună încarcă în paralel cele două luni prin API-ul existent. Editorul și notificările păstrează contractele C1/C3. CalendarMonth are o extensie vizuală opțională, varianta Acasă rămâne existentă.
- **Navigație:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, identică desktop/mobil. Acasă implicită, Cont separat. Documente rămâne în Familia mea; bara mobilă are șase controale în total. CH1 nu schimbă navigația aplicației.
- **Demo:** șase evenimente cu oră, create explicit la cererea utilizatorului în Familia Popescu prin formularul Anei. Patru pe 23 octombrie (08:30, 12:30, 17:30, 19:00), câte unul pe 24 (10:00) și 26 (18:00); patru cu acces familie, două private. Toate fără remindere și cu notița „Eveniment demo pentru prezentarea calendarului.”. Titlurile și detaliile sunt în fișa Calendar. Rămân în baza locală pentru prezentare; codul nu conține seedere demo.
- **Verificări CH1:** build/Prettier trecute la implementare; nu s-au repetat pentru date demo. Browser 390 px: luna cu șase rânduri și marcaje 4/1/1, agenda zilei de 23 cu patru carduri și ore în ordine, filtrele Familie (3) și Doar eu (1). Toate restaurat; fără overflow observat. Pagina este deschisă pe Agendă/23 octombrie pentru revizuire. Verificările anterioare ale vizualizărilor/datei peste lună și randarea punctuală rămân consemnate în fișa modulului. Fără teste noi sau suite.
- **Limite:** nu s-au reluat arhivarea, permisiunile între conturi, conflictele, notificările, erorile forțate sau dispozitivele fizice. Backend neschimbat; C2 și reconcilierea C4 rămân deschise.
- **Probe:** `.runtime/ch1-demo-month-mobile.png` și `.runtime/ch1-demo-agenda-mobile.png`, ignorate de Git; capturile inițiale fără evenimente rămân istorice. Referința este `artifacts/design-reference-calendar.png`. Contracte/verificări în [Calendar CH1](modules/calendar.md#ch1--calendar-mobil-22-octombrie-2026).
- **Pași anteriori:** Acasă H2 are feedback pozitiv; [Bucătărie KH1](modules/kitchen.md#kh1--layout-bucătărie-22-octombrie-2026), [Familie FD1](modules/families.md#fd1--documente-în-familie-22-octombrie-2026) și [Amintiri MH1](modules/memory.md#mh1--layout-mobil-amintiri-22-octombrie-2026) sunt implementate local. Planificatorul este numai schițat. D4a și AM1–AM3 își păstrează limitele; nu le reîncepe implicit.
- **Livrare:** commit local pe `codex/documente-design`, fără push. Verificarea automată anterioară a respins publicarea GitHub fără acord explicit; nu reîncerca implicit. Nu blochează dezvoltarea locală.

## Prima acțiune la reluare

Preia feedbackul utilizatorului pentru [CH1 — Calendar](modules/calendar.md#ch1--calendar-mobil-22-octombrie-2026), acum populat cu cele șase evenimente demo cerute. Nu le dubla la reluare. Nu începe alt layout, planificator sau suite de teste fără o cerere nouă. Pentru ajustări, citește numai fișa CH1 și codul relevant.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. Buildul `backend/public/app/` este ignorat. Fără dependențe noi.
- PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornire `.\ops\start-local.ps1`, oprire `.\ops\stop-local.ps1`; workeri ascunși outbox/remindere la 15 s. Reverifică procesele la reluare.
- Baza aplicației `memoria` nu se resetează pentru verificări; `memoria_test` nu a fost rulată/migrată în pașii AM2/AM3/D4a/KH1/FD1/MH1/CH1. Secretele și datele demo rămân ignorate, fără afișare sau commit.
- Popescu are două amintiri, o masă/vot fictive și trei referințe AM3; CH1 nu le modifică. Calendarul are acum șase evenimente demo persistente, cerute explicit. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Rebuild/replay în [SETUP](../ops/SETUP.md).
- Migrația D4a este aplicată numai pe `memoria`; alt mediu cere migrațiile înainte de noul build. CH1 nu are migrație sau schimbare de backend.
