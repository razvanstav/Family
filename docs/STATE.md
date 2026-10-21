# Punct de reluare

Actualizat: 21 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Calendar — [fișa modulului](modules/calendar.md).
- **Ordine cerută de utilizator:** C1 — calendar simplu **implementat și verificat**, apoi C3 — remindere in-app pentru evenimentele manuale, apoi dashboardul cu date reale. C3 și dashboardul sunt planificate. A2–A3 și emailul extern rămân amânate pentru testarea locală; C2/D4 se leagă separat.
- **Rezultat C1:** calendar lunar și agendă pe lună/zi, creare, detaliu, editare, arhivare și restaurare. Evenimente private sau vizibile familiei, modificabile de creator; dată fără oră ori moment în Europe/Bucharest. Versiunile concurente produc conflict și reîncărcare. Migrarea este aplicată în bazele locale, fără resetarea datelor existente. Corectate limitele lunii și zonele tactile/butonul de adăugare pe mobil.
- **Verificări C1:** 9 teste backend / 80 aserțiuni și 3 teste frontend noi trecute; după extinderea limitelor de lună, doar testul afectat a fost reluat și a trecut cu 12 aserțiuni. Build TypeScript/Vite, Pint și diff check trecute. Browser: creare, schimbarea datei/orei, arhivare/restaurare, conflict între ferestre, luni/zi, schimbarea familiei, desktop 1366 × 900 și mobil 360 × 800 fără overflow. Detalii și limite în [Calendar](modules/calendar.md#verificare-c1--21-octombrie-2026).
- **Verificări păstrate:** suitele F1–F3, D1–D3 și A1 nu au fost repetate. Sesiunea și contextul familiei sunt reutilizate. Evenimentul fictiv C1 și cele cinci intrări de audit ale lui au fost eliminate după probe; documentele, apartenențele și parolele existente nu au fost schimbate.
- **Blocaje curente:** niciunul pentru C1. Reminderele și dashboardul nu sunt încă implementate. C1 folosește un singur fus orar; orele inexistente sau repetate la DST sunt respinse cu explicație. ClamAV rămâne configurat, cu actualizarea manuală a semnăturilor.

## Prima acțiune la reluare

Începe C3 cu remindere in-app pentru evenimentele manuale C1: citește doar fișa Calendar, codul C1 și contractele strict necesare pentru livrare durabilă/notificări. Definește destinatarul autorizat, momentul notificării pentru evenimente cu/fără oră, anularea și replanificarea la editare/arhivare. Emailul extern și sursele derivate rămân separate. Verifică numai comportamentul nou și dependențele modificate; nu relua suitele încheiate. După C3 urmează dashboardul, într-un pas separat.

Preferința utilizatorului: rezumate scurte pe module, citire limitată la pas și dependențe concrete, fără audituri generale de securitate la lucrări UI. Regulile sunt în `AGENTS.md`.

Referința de structură este `artifacts/design-reference-layout.jpeg`, cu paleta caldă din `artifacts/design-reference-warm.png`. Dashboardul, calendarul, reminderele și notificările sunt mapate în [Interfață](modules/interface.md); fără mesagerie. Designul se aplică incremental, funcțiile viitoare nu sunt marcate implementate.

## Mediu local

- Aplicația rulează la http://127.0.0.1:8000/app/, sesiune demo Ana. PostgreSQL ascultă pe `127.0.0.1:55432`, ClamAV pe `127.0.0.1:3310`.
- Runtime ignorat de Git: `.tools/php84/php.exe`, `.tools/composer.phar`, `.tools/postgresql/pgsql/`, `.tools/clamav/clamav-1.5.4.win.x64/`. Dependențele și buildul sunt instalate. Bazele `memoria` și `memoria_test` sunt pregătite; PHPUnit folosește exclusiv `memoria_test`.
- Pornire din rădăcină: `.\ops\start-local.ps1 -PhpPath "$PWD\.tools\php84\php.exe"`. Comanda a fost verificată cu serviciile pornite. Oprire: `.\ops\stop-local.ps1`. Procesele de fundal folosesc ferestre ascunse.
- Actualizare manuală semnături: `& .\.tools\clamav\clamav-1.5.4.win.x64\freshclam.exe --quiet --config-file=.runtime/freshclam.conf`. Configurațiile, bazele și logurile sunt în `.runtime/`; detalii în [SETUP](../ops/SETUP.md#4-clamav-și-pornire). Nu afișa parolele ori conținutul `.env`.
- Captura C1: `.runtime/c1-calendar-desktop.jpg`, cu evenimentul fictiv înaintea curățării. Captura A1 și probele anterioare rămân în `.runtime/`; evidența lor este în fișele modulelor.
