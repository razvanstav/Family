# Sarcini

## H5 — Sarcini pe Acasă, 24 octombrie 2026

**Backend implementat și verificat local**, la cererea explicită de a aduce lista de sarcini sub Evenimente și Remindere. `GET /families/{family}/home` returnează `tasks`: cel mult cinci sarcini nefinalizate autorizate, cu termenele în ordine crescătoare (întârziate → azi → viitoare), apoi fără termen. La același termen, ordinea este `created_at DESC, id DESC`. Sunt incluse sarcinile comune și cele personale ale utilizatorului; accesul personal nu este extins administratorului familiei. Șterse/finalizate sunt excluse.

Integrarea folosește exclusiv `Tasks/Contracts/TaskSummaries`, legat în provider la `TaskAccess`. Serviciul deține query-ul cu JOIN pentru autor/responsabil, payloadul canonic și drepturile; `TaskController` reutilizează aceeași implementare. Home nu citește tabela Tasks și nu are o politică de acces duplicată. Payloadul este identic cu detaliul Sarcini, inclusiv versiune, responsabil, termen, recurență și `can_edit/can_complete`. Finalizarea reutilizează endpointul existent cu If-Match, inclusiv succesorul recurenței; fără rută, migrație, worker sau polling nou.

**Backend verificat:** HomeTasksTest **6 teste/67 aserțiuni**; împreună cu TaskCalendarTest, TaskRecurrenceTest și ModuleBoundaries, **27 teste/356 aserțiuni** trecute serial numai pe memoria_test. Fixture-uri UUID și cleanup limitat la familiile proprii, fără TRUNCATE/reset sau SMTP real. Acoperă ordinea/limita, includerea fără termen, ACL/izolarea/revocarea, payload canonic, responsabil limitat și versiunile 428/412, finalizare/recurență și query count constant la 1 versus 21 de sarcini; lista Home folosește un singur query, limit 5, fără N+1. Pint și diff check trecute. Integrarea finală a trecut: 98 backend/1189 aserțiuni, 45 frontend, build BswbLztS/BxAVmUyW. UI desktop/390/320, butoane și bifare cu înlocuirea rândului/focus și succesor săptămânal verificate; salvările numai în familia de test H5, curățată ulterior. Fără overflow sau erori de consolă observate; publicarea rămâne în lucru în pasul comun Acasă.

**DC1, 24 octombrie 2026:** termenul rămâne vizibil în Calendar și pentru sarcina finalizată, prin citirea canonică `TaskDatedRecords`; alerta este în continuare numai pentru nefinalizate, conform Setări. Ștergerea și accesul se reverifică, fără copii întârziate; [verificări Calendar DC1](calendar.md#dc1--toate-datele-introduse-de-utilizator-24-octombrie-2026).

**NS1, 24 octombrie 2026:** responsabilul activ poate activa În aplicație / Email în Contul meu → Setări → Sarcini; reminder în ziua termenului la 09:00 România, numai pentru sarcini nefinalizate și autorizate. Se reutilizează proiecția Calendar existentă, fără dubluri; detalii și verificări în [NS1](calendar.md#ns1--setări-personale-și-emailuri-automate-24-octombrie-2026).

## S3 — Sarcini recurente, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită de a livra S3 și apoi Animale fără întrebări intermediare, cu push după încheierea ambelor. S3 păstrează pagina și accesul existente. Repetare opțională zilnic/săptămânal/lunar/anual, cu termen obligatoriu; finalizarea creează cel mult o următoare sarcină, la primul termen viitor după data curentă/termen. Ziua inițială se păstrează pentru lunile scurte și anii bisecți. Redeschiderea nu șterge următoarea sarcină și nu creează duplicate la o nouă finalizare, nici după ștergerea succesorului.

Autorul, responsabilul, titlul și accesul curent se păstrează pentru următoarea apariție; dacă autorul nu mai poate crea, responsabilul nu mai este activ sau limita 2099 este atinsă, finalizarea rămâne posibilă și se explică oprirea repetării. Membrii adulți folosesc politica existentă `role != limited`; responsabilii activi cu acces limitat pot finaliza. Schimbarea termenului/frecvenței resetează ancora numai pe sarcina editată. Următoarea sarcină și proiecția Calendar se salvează atomic cu finalizarea; nu există worker sau generare anticipată a mai multor copii.

Intrări: `TaskRecurrence`, `TaskController`, migrația aditivă `2026_10_23_235040_add_task_recurrence.php`; `recurrence` nullable (`daily/weekly/monthly/yearly`) în cerere/payload, ancoră și predecesor interne. FK pe aceeași familie și unicitate per predecesor. Răspunsul finalizării oferă `recurrence_notice` și `next_due_on`, fără metadatele unui succesor deja creat. Frontendul reutilizează `dateLabel`, datele listei și controalele existente.

**Automat:** **49 teste backend / 510 aserțiuni trecute**, serial numai pe `memoria_test`: 11 cazuri API recurență, 16 cazuri de dată, Sarcini, Calendar și limite modulare. Acoperă luni scurte/bisecți, cicluri ratate/fus orar, Personal inclusiv față de proprietar, versiuni, revocare/roluri, limită de an, constrângeri și rollback la eșecul proiecției. Primul lot a găsit whitelistul greșit `member` în locul rolului existent `adult`; corectat la politica existentă, testul Personal întărit, lotul complet reluat și trecut. **25 teste frontend** trecute, plus cele 5 teste țintite reluate după afișarea datei următoare; TypeScript/Vite final, Pint, Prettier și diff check trecute. Build `C8TJ2G1m.js` / `SCzBUyPu.css`; avertisment Vite existent peste 500 kB (505,72 kB).

**Browser:** salvări numai pe 8001/`memoria_test`: recurență fără termen blochează salvarea, 31 ianuarie → 28 februarie → 31 martie 2027, schimbarea curentei pe Personal și următor termen 30 aprilie cu filtrul Personale păstrat. Mesajul arată data concretă. Desktop 1280×900 și mobil 320×800 fără overflow; formularul lung se derulează în foaie. Pe 8000/Ana/Popescu, formularul existent verificat la 390×844/desktop fără salvare; controalele au minimum 44 px și datele rămân neschimbate. Escape/focus și consola fără erori/warninguri observate. Capturi ignorate `.runtime/s3-recurring-{320,form-mobile,form-desktop}.jpg`. Tab/server test închise și viewport resetat. Migrație aplicată aditiv în ambele baze, cu comparația câmpurilor existente identică; cele opt sarcini ale aplicației sunt păstrate. Fără dispozitiv fizic, simulare UI nouă de concurență/rețea sau rollback de migrație. Acceptarea vizuală rămâne deschisă, dar utilizatorul a autorizat explicit continuarea cu Animale; fără push/deploy înaintea încheierii ambelor.

## SC1 — Sarcini în Calendar, 24 octombrie 2026

**Implementat și verificat local**, la cererea utilizatorului, cu commit și push solicitate pe ramura curentă. Sarcinile nefinalizate cu termen apar în Calendar la data termenului și se deschid direct în Sarcini. Accesul Familie/Personal și drepturile autorului/responsabilului se păstrează; fără remindere sau recurențe în acest pas. Acceptarea vizuală așteaptă feedback.

Integrarea folosește `Calendar/Contracts/SourceItems` și `DeadlineSource`, cu compoziția în `AppServiceProvider` și callbackuri tipizate în App. `TaskCalendar` sincronizează în tranzacția mutației; `TaskDeadlineSource` reverifică sursa și versiunea la citire. Migrația `2026_10_23_232810_allow_private_task_calendar_sources.php` extinde numai constrângerea proiecțiilor pentru sarcinile personale; `tasks:sync-calendar` reconstruiește proiecțiile din sarcinile existente, fără modificarea surselor.

**Comportament:** eliminarea termenului, finalizarea sau ștergerea scoate sarcina din Calendar, inclusiv din Arhivate; redeschiderea cu termen o readuce cu aceeași identitate. Proiecția nu se editează separat. Acasă își păstrează agenda existentă. Autorul deschide formularul, ceilalți un detaliu de consultare; responsabilul poate finaliza din detaliu. Deschiderea reutilizează lista Sarcini sau citește elementul canonic dacă lipsește (inclusiv personale/finalizate/în afara primei pagini), apoi aliniază filtrele. Cererea se curăță la schimbarea familiei/contului.

**Automat:** șapte teste SC1 noi incluse în **75 teste backend / 980 aserțiuni trecute**, serial numai pe `memoria_test`: Sarcini/Calendar, remindere existente, Auto, aniversări, Acasă și limite modulare. Acoperă mutații, Personal inclusiv față de proprietar, responsabil limitat, revocare/readmitere, două familii, versiuni canonice, rollback la eșecul proiecției, reconciliere idempotentă și query count constant. **23 teste frontend**, build TypeScript/Vite, Pint și diff check trecute. Build final `CVqwjEIA.js` / `CI18ciZ3.css`; avertismentul Vite existent pentru chunk peste 500 kB rămâne (503,98 kB).

**Instalare:** migrația este aplicată aditiv în ambele baze; fără tabele, granturi sau rute noi. Reconcilierea repetată în `memoria` păstrează cele opt sarcini și cele opt cheltuieli; comparația integrală a surselor înainte/după este identică. Fără reset/seed al aplicației; rollbackul migrației nu a fost executat.

**Browser:** 8000/Ana/Popescu fără scrieri: patru sarcini cu termen apar în Calendar; cea fără termen și cele trei finalizate lipsesc. Cumpărăturile deschid direct formularul. Pe 8001/`memoria_test`, o sarcină de probă: creare pentru Mihai/25 octombrie, mutare pe 26, detaliu fără editare ca Mihai, finalizare → Calendar gol, redeschidere din Finalizate, apoi ca Ana Personal/1 noiembrie. Luna veche este goală; intrarea privată din luna nouă deschide Sarcini/Personale. Deschiderea din Lună/Săptămână/Agendă, Escape cu focus în listă și Ionescu cu pagina Sarcini păstrată/listă comună goală verificate.

**Layout și limite:** desktop 1280×900, mobil 390×844 și 320×800 fără overflow observat; controalele formularului măsurate la 390 au minimum 44 px. Fără erori/warninguri de consolă observate. Capturi ignorate `.runtime/sc1-calendar-{desktop,mobile}.jpg`, `sc1-task-mobile.jpg`, `sc1-assignee-mobile.jpg`, `sc1-personal-320.jpg` și `sc1-calendar-test-mobile.jpg`. Tabul/serverul 8001 sunt închise, viewportul resetat; Ana/Popescu → Calendar → 24 octombrie rămâne deschis fără dialog. Fără dispozitiv fizic, simulări UI noi de rețea/concurență sau deploy. Commitul implementării `7e79d71` și modificările locale anterioare sunt publicate pe `origin/codex/documente-design`, cu push confirmat la 24 octombrie 2026. Datele private și capturile rămân ignorate. Țintele locale și ancora SC1 au fost verificate; Prettier și diff check trecute.

## S2 — Filtru după responsabil, 24 octombrie 2026

**Implementat și verificat local**, la cererea utilizatorului, după acceptarea vizuală a S1 și a listei populate („Arată bine”). Acceptarea ajustării filtrului așteaptă feedback.

- Selectorul **Responsabil** oferă **Toți** implicit, **Ale mele** și ceilalți membri activi cu cont. Funcționează împreună cu De făcut/Finalizate și Familie/Personale, fără schimbarea accesului.
- `GET /families/{family}/tasks` acceptă opțional `assignee_membership_id` UUID; filtrarea are loc pe server, înainte de paginare. Fără parametru se păstrează lista tuturor sarcinilor autorizate din accesul/starea selectate.
- Schimbarea responsabilului schimbă scope-ul listei și resetează paginarea. Metadatele păstrează lista completă de responsabili activi; un responsabil deja selectat și ulterior revocat își păstrează eticheta cu „fost membru”.
- După salvare, filtrul activ urmează responsabilul sarcinii salvate dacă ar ascunde rezultatul. Nu se adaugă requesturi de detaliu la deschiderea editării.
- Fără date, migrații, rute sau dependențe noi. Cele opt sarcini demo Popescu rămân inventarul S1-demo de mai jos.

**Automat:** 12 teste TaskTest + 3 gărzi backend, **15 teste / 244 aserțiuni trecute**, serial pe `memoria_test`; Pint, lint PHP și diff check trecute. TypeScript/Vite, cele 3 gărzi frontend și Prettier trecute. Buildul final `DR4urNdp.js` / `Dhjd3yNd.css` include ajustarea CSS mobilă; avertismentul Vite existent pentru pragul de 500 kB rămâne, cu chunk de 501,27 kB. Gărzile frontend sunt trecute după modificările TypeScript; CSS-ul ulterior nu schimbă dependențele. Revizuirea a corectat păstrarea numelui responsabilului selectat când datele listei sunt indisponibile la eroare; eticheta „fost membru” apare numai pe baza metadatelor. Acest caz a fost revizuit în cod, fără simularea erorii în browser.

**Browser 8000, fără scrieri:** Toți arată cinci sarcini de făcut; Ale mele (Ana) arată două de făcut și două finalizate; Mihai arată trei de făcut și una finalizată. Responsabilul rămâne selectat la schimbarea stării/accesului. Personale + Mihai arată lista goală cu mesaj specific filtrului; revenirea la Familie păstrează Mihai și cele trei sarcini. Schimbarea familiei la Ionescu păstrează pagina Sarcini, resetează responsabilul la Toți și încarcă opțiunile Toți/Ale mele/Elena Ionescu; revenirea la Popescu arată cele cinci sarcini comune de făcut. Toate cele opt exemple sunt păstrate.

**Layout:** desktop 1280×900, mobil 390×844 și 320×800 fără overflow. Desktopul aliniază selectorul cu filtrele de acces; pe mobil sunt două rânduri compacte, selectorul are înălțime 48 px și toate butoanele/selectoarele verificate au minimum 44 px. Fără erori/warninguri de consolă observate. Capturi ignorate `.runtime/s2-task-assignee-{desktop,mobile,320}.png`; viewport resetat, Popescu → Sarcini → De făcut/Familie/Ale mele rămâne deschis cu două rânduri, fără dialog. Fără date/migrații noi ori commit/push/deploy. Urmează feedback pentru ajustarea filtrului, cu S1 deja acceptat.

Documentație: zece ținte locale de legături și titlul ancorei S2 verificate în cele două MD-uri modificate; Prettier și `git diff --check` trecute. Ancorele istorice nu au fost reverificate.

## S1-demo — Exemple în Popescu, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită a utilizatorului: opt sarcini demo salvate prin UI-ul aplicației reale 8000, ca Ana/Popescu, cu responsabili și termene diferite, dintre care trei finalizate. Toate au acces Familie și titlul terminat în „· demo”. Listele De făcut cu cinci rânduri și Finalizate cu trei rânduri, inclusiv persistența după reîncărcare, sunt verificate. Datele anterioare și celelalte familii sunt păstrate.

| Sarcină                                  | Responsabil   | Termen            | Stare      |
| ---------------------------------------- | ------------- | ----------------- | ---------- |
| Ridicat coletul de la easybox · demo     | Mihai Popescu | 23 octombrie 2026 | De făcut   |
| Cumpărături pentru weekend · demo        | Ana Popescu   | 24 octombrie 2026 | De făcut   |
| Pregătit prânzul de duminică · demo      | Mihai Popescu | 25 octombrie 2026 | De făcut   |
| Sortat hainele de toamnă · demo          | Ana Popescu   | 28 octombrie 2026 | De făcut   |
| Organizat fotografiile de familie · demo | Mihai Popescu | Fără termen       | De făcut   |
| Plătit factura la internet · demo        | Ana Popescu   | 22 octombrie 2026 | Finalizată |
| Udat plantele · demo                     | Mihai Popescu | 23 octombrie 2026 | Finalizată |
| Pregătit lista de cumpărături · demo     | Ana Popescu   | 24 octombrie 2026 | Finalizată |

Lista De făcut ordonează termenele 23/24/25/28 octombrie și apoi sarcina fără termen. Lista Finalizate afișează cele trei bife, responsabilii și datele. După reload și redeschiderea Sarcini, cele opt titluri, responsabilii, termenele și stările sunt păstrate. Desktop 1280×900 și mobil 390×844 fără overflow orizontal; pe mobil sunt cinci rânduri, cu clientWidth și scrollWidth de 375 px. Fără erori/warninguri de consolă observate.

Capturi ignorate `.runtime/s1-demo-tasks-desktop.png`, `s1-demo-tasks-mobile.png` și `s1-demo-completed-desktop.png`. Viewportul este resetat, iar 8000 rămâne deschis în Popescu → Sarcini → De făcut/Familie, fără dialog. Numai date demo și documentație; fără cod/layout, reset/seed general, migrații, repetarea suitelor/buildului, commit/push/deploy. S1 și lista populată au fost acceptate vizual de utilizator la 24 octombrie 2026 („Arată bine”).

Documentație: nouă ținte locale de legături și titlul ancorei S1-demo verificate în cele două MD-uri modificate; Prettier și `git diff --check` trecute. Ancorele istorice nu au fost reverificate.

## S1 — Responsabil, termen și finalizare, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită a utilizatorului: Mai multe → Sarcini, pe desktop și mobil. Un singur layout nou, cu antetul comun și fotografia decorativă a agendei. S1 și lista populată au fost acceptate vizual de utilizator la 24 octombrie 2026 („Arată bine”).

### Scop și acces

- Titlu, responsabil dintre membrii activi cu cont, termen calendaristic opțional și bifă de finalizare/redeschidere. Responsabilul implicit este utilizatorul curent.
- **Familie implicit** în formular, API și DB, inclusiv când formularul este deschis din filtrul Personale. Familia vede sarcina comună; autorul și responsabilul o pot finaliza/redeschide. Numai autorul o editează/șterge. Rolul limitat consultă și poate bifa numai sarcinile atribuite lui, fără creare/editare/ștergere; regula accesului Personal rămâne exclusivă autorului.
- **Personal** este vizibil numai autorului, cu responsabilul fixat la sine. Proprietarul familiei nu primește acces suplimentar. Profilurile fără cont nu devin destinatari de autentificare sau notificări.
- Filtre Familie / Personale și De făcut / Finalizate. Liste paginate cu 50 de rânduri, termenele apropiate primele și cele fără termen la urmă; finalizatele cele mai recente primele.
- Editarea pornește din payloadul listei. Versiunea protejează mutațiile cu If-Match; conflictul cere reîncărcare explicită. Ștergere logică, fără restaurare în S1.
- Fără recurențe, notificări sau proiecții în Calendar în acest pas.

### Fișiere și contracte

- Frontend separat în `frontend/src/modules/tasks/`; App compune pagina, MobileNavigation o include în Mai multe. Remontare la schimbarea familiei. Calendarul românesc este comun în shared și reutilizat de Cheltuieli.
- Backend `Modules/Tasks/Http/TaskController.php`, tabela proprie `family_tasks`. Infrastructura comună oferă familiile/utilizatorii și TenantContext. Fără importuri sau acces la tabelele altor module.
- `GET/POST /families/{family}/tasks`, `GET/PATCH/DELETE /tasks/{task}`, `PATCH /tasks/{task}/completion` cu boolean `completed`. Lista acceptă `status=open|completed`, `visibility=family|private`, `page`; returnează items, assignees și next_page. Datele sunt YYYY-MM-DD, fără oră.
- FK compuse familie/autor/responsabil, RLS forțat pe familie, audit numai acțiuni/ID-uri, fără N+1. Garda de limite include tabela Tasks.

### Verificări și pas rămas

**Automat:** 10 teste API TaskTest + 3 gărzi backend, **13 teste / 199 aserțiuni trecute**, serial pe `memoria_test`. Acoperă Familia implicită, responsabilul implicit, termen opțional/sortare, Personal inclusiv față de proprietarul familiei, autor/responsabil/limited, versiuni 428/412, editare/ștergere logică, revocare/readmitere, RLS/două familii, rude fără cont, validări, 53 rânduri/paginare și număr constant de query-uri. Pint, lint PHP și inventarul celor șase rute trecute. Cele 6 teste frontend relevante (calendar și limite modulare), TypeScript/Vite, Prettier și diff check trecute. Revizuirea independentă a corectat filtrul după salvare; fără defect concret rămas în revizuire. Buildul final semnalează numai pragul Vite de 500 kB depășit cu circa 0,10 kB; fără pachete noi.

**Instalare:** migrația `2026_10_23_224544_create_family_tasks_table.php` și granturile runtime sunt aplicate aditiv în `memoria_test` și `memoria`. Datele existente sunt păstrate. Fără reset/seed al bazei aplicației; rollbackul migrației nu a fost executat.

**Browser, salvări exclusiv pe 8001/`memoria_test`:** creare sarcină Familie cu Mihai responsabil și termen 26 octombrie, finalizare → Finalizate, redeschidere → De făcut, editare cu eliminarea termenului; Personal fixează responsabilul la sine, salvare și ștergere confirmată; creare din Personale pornește Familie și deschide filtrul comun după salvare. Termen 23 octombrie afișează Depășit. Popescu → Ionescu păstrează Sarcini și arată lista goală, apoi revenire. Editarea folosește datele listei; reîncărcarea este rezervată conflictului. Concurența și accesul sunt verificate API; nu s-au simulat erori de rețea în UI.

**Layout:** desktop 1280×900 și mobil 390×844/320×800, fără overflow orizontal. Controalele paginii și barei mobile au minimum 44 px în probe. Calendarul are lunile/zilele în română, focus și săgeți ±1/±7, Escape închide numai calendarul și readuce focusul la termen. Poziționarea folosește minimum dintre clientWidth și dreptunghiul HTML, inclusiv gutterul stabil când dialogul ascunde scrollbarul: la 390 popupul are 375 px, la 320 are 305 px cu zile de circa 43,3×44 px, fără margini tăiate. Meniul mobil afișează Sarcini selectat, Mai multe activ, iar Escape restabilește focusul. Fără erori/warninguri de consolă observate.

**Aplicația reală 8000:** Sarcini cu stare goală și formular Familie/Ana/fără termen verificate, fără salvări de sarcini demo. Cheltuieli păstrează cele 8 exemple/762,80 lei și calendarul românesc după extragerea în shared. Capturi ignorate `.runtime/s1-tasks-test-{desktop,mobile,320}.png`, `s1-task-calendar-320.png`, `s1-task-form-desktop.png`. Tabul/serverul temporar sunt închise la final, viewportul resetat; Sarcini rămâne deschis în Popescu, fără dialog.

**Acceptare:** S1 și lista populată acceptate vizual la 24 octombrie 2026 („Arată bine”). Fără dispozitiv fizic, recurențe/remindere, alte module, commit/push/deploy.

Încheiere documentație: 71 de ținte locale de legături verificate în cele 7 MD-uri atinse de S1, Prettier și `git diff --check` trecute. Ancorele istorice ale celorlalte module nu au fost reverificate.
