# Cheltuieli

## CH4 — Filtru și totaluri pe membru, 25 octombrie 2026

**Publicat:** implementare `1cf46f7`, [deploy40191874224](https://github.com/razvanstav/Family/actions/runs/40191874224) reușit; versiunea finală cu logo BH1 este `b8385ba`/[deploy40197755657](https://github.com/razvanstav/Family/actions/runs/40197755657), HTTPS200 și bundle `BKGHsVqB/BXFcCO3E` confirmate. Verificările locale de mai jos rămân baza probelor funcționale; fără date financiare ori evenimente reale de test în producție.

**Implementat și verificat local; acceptarea vizuală așteaptă feedback.** „Plătitor” filtrează după membrul din câmpul existent „Cine a plătit”, cu Toți membrii implicit. Panoul „Pe membri” afișează sumele întregii luni, pentru accesul Familie / Personale și starea Plătite / De plătit selectate. Apăsarea unui rând filtrează lista după acel membru; o nouă apăsare elimină filtrul. Totalul principal, categoriile și lista urmează filtrul. Sumele per membru includ toate paginile, nu numai cele încărcate în browser; cheltuielile private ale altor autori rămân excluse.

`GET /families/{family}/expenses` acceptă opțional `payer_membership_id` UUID și întoarce selecția sau null, plus `member_totals: [{payer_membership_id, payer_name, total_bani, count}]`, agregate în SQL înaintea filtrului membru. `payers` rămâne lista membrilor activi cu cont pentru formular. Foștii membri cu cheltuieli autorizate apar în totaluri și pot fi filtrați; nu redevin opțiuni la adăugare. Selecția se păstrează între luni, inclusiv într-o lună goală; deschiderea din Calendar/Acasă elimină filtrul pentru a arăta cheltuiala cerută. Fără migrații, polling sau schimbări ale regulilor de acces.

**Verificări observate:** ExpenseTest 9 teste / 237 aserțiuni și ExpenseRecurrenceTest + ModuleBoundariesTest 18 / 186, serial pe `memoria_test`: total 27 / 423. Filtru/totaluri cu 53+1 înregistrări și paginare, acces privat, fost membru, paid/planned și validare UUID; Pint și diff check trecute. Frontend: verificarea TypeScript și cele 7 teste Cheltuieli/gărzi trecute, inclusiv opțiuni istorice unice și selecție păstrată în luna goală. Suita comună frontend: 77 teste trecute; TypeScript/Vite final `DmygpS8O/Bvr31trk`, cu avertismentul existent JS peste 500 kB. Review independent: acces/paginare/sume fără probleme; încadrarea numelor lungi în total corectată.

**Browser:** build real cu API fictiv izolat pe8006, numai GET/HEAD, fără DB, SMTP sau salvări. Desktop1280×900, mobil390×844/320×800 și landscape844×390 fără overflow observat. Total familie5.827 lei/55 rânduri și Pe membri5.300/450/77 lei; paginare50+5 fără modificarea totalului; filtru Mihai450 lei/1 rând, noiembrie0 cu selecție păstrată, De plătit120 lei, fost membru Bogdan77 lei. Personale/Mihai0 și Toți35 lei în fixture; trei totaluri de familie păstrate după filtrare. Formularul arată numai doi membri activi, fără fostul membru. Calendarul și meniul de an din formular se încadrează și nu derulează panoul părinte. Corecția tastaturii și limitele sunt în [Interfață UW2](interface.md#uw2--stabilizarea-formularelor-la-tastatura-mobilă-25-octombrie-2026). Fără erori/warninguri de consolă. Captură ignorată `.runtime/ch4-expenses-desktop.png`; viewport resetat, previzualizarea fictivă8006 păstrată pentru feedback. Fără commit/push/deploy.

## CH3 — Plăți planificate și recurente, 24 octombrie 2026

**Publicare:** `a7aeea8`, [deploy 40127782128](https://github.com/razvanstav/Family/actions/runs/40127782128) reușit; migrația 162605 apare efectiv `DONE` în logul de build/deploy și cinci operațiuni GRANT executate. HTTPS `/up`, `/app/` și JS răspund 200, bundle final `CDzmPc0C/C5pZLMOO` verificat. Diagnosticul separat al workerilor/granturilor efective rămâne indisponibil din cauza accesului Docker refuzat; CH3 nu introduce worker sau tabel nou și nu schimbă configurația emailului. Nu s-au rulat probe cu date financiare reale pe server.

**Implementat, verificat și publicat.** Cheltuielile existente rămân plătite. La adăugare se aleg Plătită / De plătit și repetarea Fără repetare / Zilnic / Săptămânal / Lunar / Anual. `spent_on` rămâne data aleasă: data cheltuielii plătite sau termenul plății planificate. Marcarea ca plătită nu schimbă această dată; se poate modifica în formular.

O cheltuială plătită cu repetare creează o singură plată viitoare neplătită, la salvare. Una planificată o creează numai când este marcată plătită. Data următoare este strict după maximul dintre data rândului și ziua curentă din România; ciclurile trecute se sar. Ziua inițială este păstrată pentru lunile scurte și 29 februarie. Modificarea datei sau frecvenței rândului planificat pornește o ancoră nouă; oprirea repetării oprește numai continuarea lui. Ștergerea afectează un singur rând. Istoricul plătit și succesorul deja creat nu se modifică reciproc. Frecvența unui părinte care are deja succesor, inclusiv șters logic, este blocată: repetarea se schimbă în următoarea plată. Nu se recreează un succesor șters.

Pagina păstrează luna și accesul Familie / Personale și adaugă filele **Plătite / De plătit** prin `SectionTabs`. Crearea din De plătit selectează acea stare; starea unei înregistrări existente este afișată fără selector. **Marchează plătită** este o acțiune separată pentru autorul cu drept de scriere. Formularul blochează numai frecvența părintelui cu succesor, cu explicația lângă control; data rămâne editabilă. Listele și totalurile sunt separate pe stare, cu Plătite implicit; plățile planificate nu umflă cheltuielile plătite.

Acasă primește `upcoming_payments` prin contractul public `Expenses/Contracts/UpcomingPayments`, implementat de `ExpenseAccess`: maximum cinci plăți planificate autorizate, de azi până peste șapte zile inclusiv, ordonate după termen și ID. Fereastra traversează lunile și folosește ziua României. Rândurile plătite, șterse, întârziate, de peste șapte zile și private ale altor membri sunt excluse. Payloadul complet include versiunea și dreptul de editare și poate fi deschis fără GET suplimentar. App compune integrarea UI, iar acțiunea de plată este reutilizată din modulul Cheltuieli. Calendarul păstrează datele ambelor stări și deschide accesul/starea corecte. Acest pas nu creează remindere, emailuri, procese recurente sau polling.

**Backend:** `ExpenseAccess` reutilizează interogarea, JOIN-urile, ACL și payloadul în listă, detaliu și Acasă. `ExpenseRecurrence` deține continuarea înregistrărilor; algoritmul pur al datelor este comun în `Platform/RecurrenceDates`, iar `TaskRecurrence::nextDate` păstrează API-ul anterior și deleagă către el. Nicio dependență privată între module. `PATCH /expenses/{expense}/payment`, cu `{payment_status: "paid"}` și `If-Match`, permite numai autorului nelimitat să marcheze plata; un apel repetat cu versiunea curentă este fără efect, iar versiunea veche primește 412. CRUD păstrează controlul accesului, izolarea familiei și auditul. Modificarea stării prin PATCH-ul general este respinsă.

Migrația aditivă `2026_10_24_162605_add_recurrence_to_expenses.php` adaugă starea implicit plătită, frecvența, ancora și părintele, verificări DB, unicitatea succesorului inclusiv după ștergere logică, FK compus în aceeași familie și index pentru fereastra Acasă. Fără tabele sau clienți externi noi.

**Verificări observate:** integrarea finală a trecut **66 teste backend / 692 aserțiuni**, serial pe `memoria_test`: ExpenseRecurrenceTest, ExpenseTest, TaskRecurrenceTest, TaskRecurrenceDateTest, HomeTasksTest, CalendarDatedRecordsTest și gărzile modulare. Acoperă totaluri separate, creare plătită/planificată, idempotentă și versiuni, ancore lunare/anuale, cicluri omise, oprire/modificare, părinte cu succesor șters, rol limitat/privat/revocare/plătitor inactiv, limita 2099, Calendar fără remindere, fereastra Acasă inclusiv peste lună la miezul nopții României, limita de cinci și număr constant de query-uri, FK de familie și unicitate DB. `ExpenseTest` folosește familii/utilizatori UUID proprii și curățare țintită, fără TRUNCATE sau seed global. Pint trecut. Frontend comun: **53 teste**, TypeScript/Vite `CDzmPc0C/C5pZLMOO`, Prettier și diff check trecute; avertismentul existent JS peste 500 kB rămâne.

**Migrație și browser:** migrația 162605 aplicată în `memoria_test` și `memoria`, cu amprentele datelor și câmpurilor anterioare păstrate. Browser desktop/390/320, numai în familia UUID CH3 din baza de test: creare plătită săptămânală și planificată lunară, card gol ascuns, scadență la +7 zile afișată, deschidere în formular și bifare directă. După bifări: total achitat 245 lei, următoarele două plăți 185 lei în noiembrie, excluse corect din fereastra Acasă; datele originale păstrate. Focusul trece la următoarea bifă, apoi la Acces rapid când cardul dispare. Fără overflow observat, bife 44 px, consola probei fără erori/warninguri. Fixture curățat, serverul temporar oprit, viewport resetat; capturi `.runtime/ch3-home-*` și `.runtime/ch3-expense-form-320.png`. Fără SMTP sau dispozitiv fizic; acceptarea vizuală rămâne la utilizator.

**DC1, 24 octombrie 2026:** data cheltuielii apare în Calendar prin `ExpenseDatedRecords`, păstrând accesul Familie/Personal. Deschiderea păstrează luna și accesul și refolosește detaliul deja listat când este disponibil; ștergerea se reflectă imediat. [Contract și verificări Calendar DC1](calendar.md#dc1--toate-datele-introduse-de-utilizator-24-octombrie-2026).

## S1 — Calendar comun cu Sarcini, 24 octombrie 2026

Implementat și verificat local: selectorul lunar și calendarul românesc din CH1-demo sunt mutate în `shared/MonthSelect.tsx`, `shared/DatePicker.tsx` și `shared/datePicker.css`, pentru al doilea consumator, Sarcini. Cheltuieli păstrează data obligatorie, aceeași limbă, interval și acțiuni. Sarcini folosește varianta opțională cu Fără termen. Fără importuri între module, schimbări API/date sau alt layout. Fișierele și selectorii vechi locali au fost eliminați. Cele 6 teste calendar/gărzi, build și browser 8000 trecute: 8 cheltuieli/762,80 lei păstrate, calendar cu Octombrie și zile românești, fără salvări de date noi. Poziționarea comună include gutterul stabil al dialogului, verificat la 390/320 px în Sarcini.

## CH2 — Navigație principală și etichete, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită a utilizatorului: Cheltuieli devine pagină principală desktop/mobil și dispare din Familia mea. Calendar este în Mai multe pe ambele suprafețe; mobilul păstrează cinci destinații directe. Pagina are titlul Cheltuieli și filtrele **Familie / Personale**, în loc de „Privatele mele”; totalul și starea goală personală folosesc aceeași terminologie. Accesul personal rămâne exclusiv al autorului, iar Familia este implicită.

Integrare numai UI în App/MobileNavigation și modulul Expenses, cu eliminarea propului navigation și a selectorului CSS Calendar rămas fără utilizare. Se refolosesc Modal și stilul comun more-menu. Datele demo și regulile API se păstrează; fără backend/migrații. TypeScript/Vite, cele 3 gărzi frontend, Prettier și diff check trecute. Fără teste noi pentru redenumire/aranjare și fără reluarea suitei backend.

Browser 8000: titlu Cheltuieli, breadcrumb/titlu browser proprii, fără file Familie pe pagină; Familia mea are numai Membri/Documente. Familie arată 8 rânduri/762,80 lei, Personale arată total personal zero fără scurgeri între filtre. Calendar este accesibil din More desktop și mobil, cu aria-current pe destinație și Mai multe; Escape închide foaia și restabilește focusul. Popescu → Ionescu păstrează pagina Cheltuieli și arată zero, apoi revenire la cele opt exemple. La 390×844 și 320×800, bara mobilă are Acasă/Familie/Bucătărie/Cheltuieli/Mai multe, fără overflow, controale de minimum 55 px în proba la 320. Meniul deschis se închide când trecerea mobil/desktop îi ascunde controlul. Fără erori/warninguri de consolă observate și fără salvări de date în probe.

Capturi ignorate `.runtime/ch2-expenses-{desktop,mobile}.png` și `.runtime/ch2-more-mobile.png`; viewport resetat, pagina Cheltuieli lăsată deschisă pe Familia Popescu. Navigația curentă este salvată în AGENTS și Interfață. Fără dispozitiv fizic, commit/push/deploy sau extinderea altor fluxuri.

## CH1 — Adăugare rapidă și evidență lunară, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită a utilizatorului. Familia mea primește fila Cheltuieli, după Membri și Documente. Navigația principală desktop/mobil rămâne existentă. Un singur layout nou, cu antetul/fotografia/cadrul comun al familiei; aspect acceptat de utilizator la cererea CH1-demo.

### Scop și reguli

- Sumă în lei, categorie, data (implicit azi în România), cine a plătit (implicit utilizatorul curent), notă opțională și acces.
- **Familie este implicit**, în formular, API și DB. Toți membrii activi ai familiei pot vedea înregistrarea comună. Privat este numai pentru autor. Autorul poate edita/șterge propriile cheltuieli; proprietarul familiei nu primește acces suplimentar. Rolul limitat consultă, fără scrieri.
- Plătitorul este un membru activ cu cont, independent de autor, și nu dobândește acces la o înregistrare privată. Un fost membru deja asociat poate rămâne în istoric; nu mai poate fi ales la adăugare. Profilurile fără cont nu devin destinatari de autentificare sau notificări.
- Evidență lunară cu filtre Familie / Privatele mele, total și sume pe categorii pe toate înregistrările autorizate din lună. Lista este grupată pe zile, paginată cu 50 rânduri; totalul include și paginile următoare. Sume în bani întregi, fără aritmetică în virgulă mobilă la salvare/agregare.
- Editare din payloadul complet al listei, fără GET suplimentar. `If-Match` protejează editarea/ștergerea; 412 oferă reîncărcare explicită. Ștergere logică prin `deleted_at`, scoasă din liste/totaluri; fără restaurare în UI în acest pas.
- Separat de costurile Auto; fără import automat, OCR, bonuri, obiective, abonamente, bugete, schimb valutar sau integrare bancară în CH1.

### Fișiere și contracte

- Frontend: `frontend/src/modules/expenses/{Expenses,ExpenseForm}.tsx`, `expenseData.ts`, `expenses.css`. App compune fila/pagina; modulul nu importă Familie sau alte module. Refolosește `PageBackdrop`, `Modal`, datele și API-ul comune.
- Backend: `Modules/Expenses/Http/ExpenseController.php`, `GET/POST /families/{family}/expenses`, `GET/PATCH/DELETE /expenses/{expense}`. Fără consumatori externi ai datelor în acest pas; fără contract public fără utilizator.
- Tabela `expenses`: FK compuse pentru familie/autor/plătitor, RLS forțat pe familie, valori/categorii/acces validate, versiune și audit numai acțiuni/ID-uri. Numele se aduc prin JOIN în query-ul listei; fără N+1. Garda backend include ownership Expenses.

### Verificări și pas rămas

**Automat:** 8 teste API CH1 + 3 teste backend de limite modulare, **11 teste / 193 aserțiuni trecute**, serial pe `memoria_test`. Acoperă sume cu virgulă/punct, luna implicită la miezul nopții în România, limite lunare, total complet la 53 rânduri/paginare 50+3, număr constant de query-uri, privat/autor/plătitor/proprietar, rol limitat, două familii, RLS și revocare/readmitere, versiuni și ștergere logică. O așteptare inițială pentru spații a fost aliniată middleware-ului existent TrimStrings; fără schimbarea validării produsului.

TypeScript/Vite și cele 3 gărzi frontend trecute; Pint pe fișierele atinse și `--dirty --format agent` trecute. Cele 5 rute sunt verificate prin `route:list`. Buildul final include breadcrumbul/titlul Cheltuieli, starea goală pe toată lățimea și focusul în Sumă la deschidere / revenirea la adăugare după salvare.

**Instalare:** migrația `2026_10_23_215420_create_expenses_table.php` și grantul runtime pentru noua tabelă sunt aplicate aditiv pe `memoria_test` și `memoria`. Fără reset/seed al bazei aplicației și fără cheltuieli demo în ea. Rollbackul nu a fost executat. Utilitarele locale ignorate din `.runtime/` refuză baze neașteptate; pregătirea browserului modifică numai `expenses` din `memoria_test`.

**Browser, salvări exclusiv pe 8001/`memoria_test`:** creare 87,40 lei; altă categorie/plătitor 35,50 lei și mutare în noiembrie prin tastatura câmpului de dată, cu trecere automată la luna salvată; privat 24,90 lei cu total separat; formular nou deschis din Privatele mele cu Familie implicit; adăugare 62,80 lei și editare la 63,80 lei, total comun 151,20 lei; mutarea cumpărăturilor în 23 octombrie produce două grupuri zilnice. Ștergere privată confirmată → total privat zero. Popescu → Ionescu păstrează fila Cheltuieli și arată zero, apoi revenire. Paginarea a fost parcursă și cu fixture-urile de 53 rânduri; exactitatea 50+3/fără duplicate este verificată automat.

**Layout:** desktop 1280×800, mobil 390×844 și 320×800, fără overflow orizontal al paginii/formularului. Controalele paginii la 390 px au minimum 44×44 px. Formularul are focus în Sumă și Familie implicit; la 320 px se derulează vertical. Escape închide formularul. La 320 px, Documente/Membri/Cheltuieli au antet de 120 px și file la y=217, fără deplasarea cadrului. Consola probei și aplicației locale nu are erori/warninguri observate.

Capturi ignorate: `.runtime/ch1-expenses-{desktop,mobile,320}.png`, `.runtime/ch1-expense-form-mobile.png`. Tabul și serverul temporar de test au fost închise; viewportul resetat. Aplicația reală pe 8000 rămâne deschisă în Familia mea → Cheltuieli, cu stare goală și datele anterioare păstrate. Fără commit/push/deploy.

**Limite și următoarea acțiune:** probe locale, fără dispozitiv fizic, simulări UI noi de rețea/concurență sau rollback de migrație. Concurența/accesul sunt acoperite API. Așteaptă feedback pentru acest singur layout; nu începe automat funcțiile ulterioare sau alte module.

**Încheiere documentație:** 58 de ținte locale de legături verificate în cele 5 MD-uri modificate, Prettier și `git diff --check` trecute; nu s-au reverificat ancorele istorice ale altor module în acest pas.

## CH1-demo — Exemple și calendar românesc, 24 octombrie 2026

**Implementare UI:** `ExpenseMonthSelect.tsx` este reutilizat în evidența lunară și calendarul formularului: lunile în română, anul 1900–2099 și săgețile existente. `ExpenseDatePicker.tsx` refolosește `shared/calendarDates.ts`, afișează data în română și oferă zile cu nume complete, navigare săgeți ±1/±7 zile, selecție fără submit și Escape cu revenirea focusului. Popoverul este ancorat în lățimea efectivă a documentului și se reașază la resize. Sub 370 px, selectorul lunar are un rând propriu; filtrele și refreshul au al doilea rând. Fără schimbări în `shared`, alte module sau dependențe noi.

**Verificări finale:** TypeScript/Vite, cele 6 teste existente din `calendar.test.mjs` + `moduleBoundaries.test.mjs`, Prettier și diff check trecute. Browser real 8000: persistență 8 rânduri/762,80 lei după reload, Noiembrie gol și revenire în Octombrie, anii 2025/2026, toate cele 12 luni în română, popup cu zile românești, ArrowLeft → ziua precedentă, Escape păstrează formularul și readuce focusul la Data, 29 februarie 2024 selectabil fără salvare. Formularele probelor noi au fost anulate; numărul cheltuielilor rămâne opt.

Desktop 1280×900 și mobil simulat 390×844/320×800 fără overflow ori margini tăiate. La 320 px, Luna are 101 px în bara paginii; cu formularul deschis, calendarul are 320 px și zile de aproximativ 45,42×44 px. Reancorarea la 390 px funcționează. Fără erori/warninguri de consolă observate; fără dispozitiv fizic. Capturi ignorate `.runtime/ch1-demo-expenses-{desktop,mobile}.png` și `.runtime/ch1-demo-calendar-ro-mobile.png`. Viewport resetat, pagina Popescu → Cheltuieli rămasă deschisă cu octombrie 2026, fără dialog. Nu s-au repetat testele backend și nu s-a atins baza de test pentru această corecție UI/date demo.

**Implementat și verificat local.** Utilizatorul a acceptat aspectul CH1 și a cerut cheltuieli demo în familia Popescu, pe zile/categorii diferite, plus corectarea calendarului în engleză. Același layout; fără extinderea altor module.

**Date salvate prin UI-ul aplicației reale, ca Ana/Popescu**, cu acces Familie și notă terminată în „· demo”. Opt cheltuieli, șapte zile și toate cele șapte categorii; total octombrie **762,80 lei**. Acestea sunt exemple păstrate la cererea utilizatorului, nu cheltuieli reale sau seed general. Datele anterioare și familia Ionescu sunt păstrate.

| Data              | Categorie  | Notă                            | Sumă       | Plătitor      |
| ----------------- | ---------- | ------------------------------- | ---------- | ------------- |
| 24 octombrie 2026 | Alimente   | Cumpărături de weekend · demo   | 186,40 lei | Ana Popescu   |
| 24 octombrie 2026 | Timp liber | Înghețată în parc · demo        | 28,00 lei  | Mihai Popescu |
| 23 octombrie 2026 | Transport  | Combustibil · demo              | 150,00 lei | Mihai Popescu |
| 22 octombrie 2026 | Casă       | Produse pentru curățenie · demo | 64,90 lei  | Ana Popescu   |
| 21 octombrie 2026 | Sănătate   | Farmacie · demo                 | 47,50 lei  | Ana Popescu   |
| 20 octombrie 2026 | Copii      | Rechizite pentru școală · demo  | 82,30 lei  | Mihai Popescu |
| 18 octombrie 2026 | Alimente   | Piață și brutărie · demo        | 93,70 lei  | Ana Popescu   |
| 16 octombrie 2026 | Altele     | Cadou pentru bunici · demo      | 110,00 lei | Ana Popescu   |

**Observat:** lista are opt rânduri și șapte grupuri de zi, iar categoriile însumează Alimente 280,10; Transport 150; Altele 110; Copii 82,30; Casă 64,90; Sănătate 47,50; Timp liber 28 lei. Selectorul nativ de lună și pickerul datei au fost înlocuite cu controale românești independente de limba browserului. După reload, cele opt cheltuieli și totalul de 762,80 lei sunt păstrate. Fără migrații/backend/commit/push/deploy.
