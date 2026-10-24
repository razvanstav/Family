# Cheltuieli

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
