# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** [Interfață UT3 — navigarea categoriilor ascunse](modules/interface.md#ut3--navigarea-categoriilor-ascunse-23-octombrie-2026), implementat, verificat local și acceptat vizual de utilizator la 23 octombrie 2026. Commitul și pushul modificărilor curente sunt autorizate explicit.
- **Rezultat:** bara comună derulabilă afișează săgeți de 44 × 44 px când etichetele nu încap; acestea aduc următoarea filă ascunsă în vedere și se dezactivează la capete. Derularea directă și selectarea filelor sunt păstrate; săgețile dispar când toate filele încap.
- **Interfață:** un singur rând și cadrul stabil UT2, cu controale reale de navigare. Schimbări numai în `shared/SectionTabs.tsx` și regulile comune din `styles.css`. Varianta este folosită de profil și Auto. Navigația principală și ordinea categoriilor sunt neschimbate.
- **Date/API:** fără migrație, date noi sau modificări de permisiuni. [Familie FP2](modules/families.md#fp2--eliminarea-arhivării-din-profil-23-octombrie-2026) rămâne aplicat: fără Arhivate/Arhivează în interfața profilului, contractul backend existent păstrat.
- **Verificat:** TypeScript/Vite build, Prettier și `git diff --check`. Prima probă mobilă a identificat blocarea unei apăsări din cauza pozițiilor fracționare; pragul a fost corectat și parcurgerea completă reluată cu succes. Buildul este servit local pe 8000.
- **Browser:** profil Mihai în previzualizare de 390 px: săgeți până la Documente și înapoi, limite dezactivate, Enter, derulare directă și selectarea Prezentare. Desktop 1280 px: toate cele cinci file vizibile fără săgeți, bara de 54 px; filele obișnuite Familie sunt păstrate. Captură locală ignorată: `.runtime/member-tabs-arrows-mobile.png`.
- **Limite:** fără dispozitiv fizic, probă dedicată a mișcării reduse sau redimensionare continuă. Fără suită backend pentru schimbarea de interfață. Fără publicare în producție.
- **Context păstrat:** [Auto V1a](modules/vehicles.md#v1a--ecranul-auto-23-octombrie-2026), F4a/FD3, FP2, UT1/UT2 și KH2 rămân implementate; probele lor anterioare sunt în fișele modulelor. Calendar/remindere Auto și V2–V4 nu sunt începute. Nu dubla cele trei însemnări demo din profilul Anei. Planificatorul rămâne schiță.

## Următoarea acțiune

UT3 este acceptat. Continuă după următoarea cerere a utilizatorului; feedbackul pentru Auto rămâne separat. Citește acest rezumat, numai fișa pasului vizat și codul relevant cererii. Nu începe alt layout și nu extinde automat Calendar/remindere/V2–V4. Deploy-ul rămâne separat.

## Mediu local

Windows, PHP 8.4 portabil în `.tools/php84/php.exe` pe checkoutul curent, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste numai pe `memoria_test`, serial. CLI DB/media: `-d extension=pdo_pgsql -d extension=gd`. Buildul `backend/public/app/` este ignorat. Pornire/oprire: [SETUP](../ops/SETUP.md); nu reseta/seedui baza aplicației. Secretele/datele locale nu se afișează sau comit.

Rulare locală la cererea utilizatorului, 23 octombrie: `./ops/start-local.ps1 -PhpPath "$PWD/.tools/php84/php.exe"`, aplicație la `http://127.0.0.1:8000/app/`. Build refăcut după pull; cele șapte migrații restante aplicate pe `memoria`, granturi runtime și indexul cronologiei actualizate, workerii reporniți. HTTP 200 pentru aplicație, `/up` și `/api/v1/session`, PostgreSQL acceptă conexiuni, ClamAV răspunde PONG. Detalii și limite: [verificare locală](modules/operations.md#rulare-locală-windows--23-octombrie-2026). `memoria_test` nu a fost actualizată sau verificată în această rulare; rezultatele de teste din fișele modulelor sunt cele consemnate anterior.
