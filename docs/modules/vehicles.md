# Vehicule

**DC1, 24 octombrie 2026:** toate datele înregistrărilor Auto active, inclusiv istoric/finalizate, apar în Calendar prin `VehicleDatedRecords`, fără dubluri cu programările existente. Arhivarea rămâne respectată; documentele asociate fără fișier sunt afișate neutru, fără acces nou. [Contract și verificări Calendar DC1](calendar.md#dc1--toate-datele-introduse-de-utilizator-24-octombrie-2026).

**P1a.3, 24 octombrie 2026:** Auto consumă numai contractele publice `DocumentLinks` și `SourceItems` pentru asocieri și Calendar. Accesul documentelor este reverificat prin modulul proprietar, iar setările rămân o proiecție autorizată în query-ul listei. [Regresie P1a](platform.md#p1a3--contracte-backend-și-ownership-verificat-la-24-octombrie-2026).

Stare: **VC1 — scadențe Auto în Calendar și notificări in-app**, implementat și verificat local. V1a rămâne existent, iar restul V1–V4 (inclusiv emailul) rămâne separat.

## VC1 — Scadențe Auto în Calendar, 23 octombrie 2026

- Cerere explicită: ITP/RCA/rovinietă introduse o singură dată, reminder opțional și actualizare fără dubluri. Se includ și reviziile service planificate existente. Alimentările/costurile efectuate nu produc evenimente.
- O proiecție Calendar per înregistrare, cu unicitate familie/tip sursă/ID, dată fără oră și acces familie. Auto deține data și setările; Calendar oferă detaliu și „Deschide în Auto”, fără editarea/arhivarea independentă a copiei.
- Reminder opțional în aceeași zi sau cu 1/7/30 de zile calendaristice înainte, la 09:00 în România; destinatari activi cu cont, aleși explicit. Se reutilizează intențiile/livrările Calendar; fără email ori push nativ. Momentele deja trecute nu se livrează retroactiv.
- Modificările înregistrării, redenumirea/arhivarea/restaurarea mașinii, efectuarea și arhivarea/restaurarea înregistrării sincronizează Calendarul și anulează ocurențele vechi în aceeași tranzacție. Reminderul unei înregistrări efectuate se dezactivează. Conflictul de versiune sau validarea respinsă păstrează sursa și proiecția anterioare.
- Contractul public `Calendar/Contracts/DeadlineSource` și `VehicleDeadlineSource` reverifică sursa canonică și versiunea înainte de Calendar, Acasă și livrare. Revocarea retrage destinatarul, fără să elimine scadența deținută de familie sau notificările către ceilalți membri. Asocierea documentelor și accesul lor nu se schimbă.
- Intrări: `VehicleCalendar`, `SourceCalendarItems`, `CalendarSources`, extensiile controllerelor și migrația `2026_10_23_172427_add_calendar_item_sources.php`. `vehicles:sync-calendar` reconstruiește proiecțiile lipsă, fără destinatari noi și fără replanificarea celor neschimbate. `ReminderFields` și CSS-ul său sunt comune în `frontend/src/shared/`; coordonarea navigării este în App.
- **Verificat:** 11 teste noi / 146 aserțiuni și 32 teste existente Calendar/Auto / 359 aserțiuni, serial numai pe `memoria_test`; TypeScript/Vite build trecut. Migrație aditivă aplicată pe ambele baze și retrasă/reaplicată numai în test. Prima rulare a prins salvarea parțială la validare și limita smallint pentru 30 de zile; corectate prin tranzacții explicite și integer. Pint trecut cu acces permis după refuzul de scriere în sandbox; nicio respingere de auto-review. Browser/diff check urmează. Skillul `testing-best-practices` cerut local nu este disponibil; testele urmează convențiile existente.
- **Încheiere VC1:** testele noi extinse la 154 aserțiuni (prohibirea sursei falsificate și rollback la editare invalidă); toate trecute. Browser desktop și 390×844, numai pe 8001/`memoria_test`: creare/reminder, Calendar → sursă, editare și arhivare probate; fără overflow sau erori/warninguri în consolă. Capturi și limite în STATE. Diff check trecut; workerul real repornit, serverul/tabul de test închise, fără schimbarea datelor aplicației.

## V1a — Ecranul Auto, 23 octombrie 2026

- Compoziția reproduce structura telefonului Mașini din `artifacts/design-reference-family-mvp.png`: fotografie și identitate, file Prezentare/Revizii/Documente/Costuri, următoarele evenimente, istoric și acțiunea de adăugare. Suprafețe neutre, iconuri colorate, Helvetica și controale de minimum 44 px. Fără numere, fotografii sau înregistrări inventate în baza aplicației; fotografia apare după încărcare.
- Auto este adăugat după Calendar în ambele navigații. Ordinea secțiunilor existente și pagina implicită rămân aceleași; Cont rămâne separat. Mai multe mașini se aleg prin selector; căutarea, arhiva și actualizarea sunt în instrumentele deschise din antet. Filele păstrează cadrul profilului.
- Profil: nume, marcă, model, număr de înmatriculare opțional, an opțional, fotografie privată scanată/recodată prin `PrivateMedia`. Adulții cu rol owner/adult pot modifica; rolul limited consultă. Toți membrii activi ai familiei pot vedea profilurile și înregistrările. Numărul nu este inclus în audit.
- Înregistrări ITP/RCA/rovinietă/service/alimentare/alt cost: planificat sau efectuat, dată, cost RON și kilometraj opționale numai după efectuare, notițe. Scadențele restante rămân vizibile cu stare distinctă; intervalele se calculează din data curentă a României. Costurile nu sunt estimate; istoricul include și înregistrări fără cost. Kilometrajul este o citire consemnată la înregistrare, fără calcul de consum sau validarea unei serii istorice V2.
- Liste paginate de 20, filtre, detaliu/editare din payloadul listei, versiuni If-Match, draft păstrat și salvare blocată la conflict până la reîncărcare explicită. Arhivare/restaurare pentru mașină și înregistrări; mașina arhivată nu mai primește modificări ale conținutului.
- Documente: asociază un document existent, deschide bytes prin ruta canonică și elimină numai asocierea. `Contracts/DocumentLinks::references` oferă metadatele autorizate în lot; niciun query per document. Asocierea nu schimbă accesul: titlul și bytes nu devin vizibile altui membru prin mașină. Uploadul și partajarea documentului rămân în dosarele existente.
- Backend: `Modules/Vehicles/Http/VehicleController.php`, rutele `/families/{family}/vehicles`, migrația `2026_10_22_215356_create_vehicles_tables.php`. Trei tabele cu FK compuse și RLS forțat, autorizare în TenantContext, audit fără identificatori. Migrație și granturi aplicate aditiv pe `memoria_test` și `memoria`, fără seed/reset al aplicației.
- Frontend: `modules/vehicles/{Vehicles,VehicleForms,vehicleData,vehicles.css}`; numai integrarea de navigație în App și varianta existentă de file comune. Fără dependențe noi.

**Verificat:** TypeScript/Vite build, Prettier, Pint, rutele și `git diff --check`. `VehicleTest`: **6 teste / 127 aserțiuni**, serial pe `memoria_test`: familii diferite, limited/revocare, versiuni, arhivare/restaurare, date/costuri invalide, paginare/filtre, fotografie și documente private după asociere și schimbarea accesului. Prima rulare a identificat două așteptări greșite ale testului (MIME PNG păstrat și throttling la fixture-ul cu 23 cereri); corectate, apoi toate testele au trecut. Costurile de alimentare și alte costuri sunt numai efectuate; nu pot produce scadențe ascunse de filtrul Revizii.

**Browser:** Auto verificat la 320/390 px și desktop 1080/1440 px. La 320 px filele au etichete întregi, înălțime 44 px și fără overflow al paginii; navigația are derulare proprie la lățimi insuficiente. La 390 px cadrul filelor a rămas la aceeași poziție/dimensiune între Revizii și Documente (top 343 px, înălțime 54 px, lățime 335 px în buildul probei). Salvare cost, arhivare și asociere document probate prin UI, exclusiv pe `memoria_test`, în serverul temporar 8001. Aplicația de pe 8000 a primit numai schema și buildul, fără mașini sau costuri demo. Capturi locale ignorate: `.runtime/auto-mobile.jpg`, `.runtime/auto-desktop.jpg`. Fără dispozitive fizice, probe de scanner indisponibil/carantină forțată sau email. Skillul `testing-best-practices` cerut de backend/AGENTS.md nu este instalat; testele urmează convențiile existente.

**Extindere după V1a:** Calendarul și notificările in-app sunt acum livrate prin VC1, descris mai sus. Emailul, identificatorul VIN și accesul fin pe vehicul, alimentările în litri/consum, seria/corectarea kilometrajelor și restul obligațiilor V3–V4 rămân separate. Acceptarea vizuală aparține utilizatorului; nu începe alt layout înainte de feedback.

## Scop

Profil și identificatori protejați, kilometraj, service, alimentări și obligații RCA/ITP/rovinietă introduse manual. Obligația deține scadența; documentul asociat dovedește obligația și are acces separat.

## Pași

- [ ] **V1 — Profil și identificatori.** Definește modelul, accesul și contractele. Livrează profilul cu editare concurentă protejată; câmpurile sensibile nu apar în loguri, audit sau căutare implicită.
- [ ] **V2 — Istoric.** Adaugă separat kilometraj, service și alimentări cu unități și validări. Corectarea unei citiri istorice este explicită și auditată; verifică efectul asupra intrărilor ulterioare, fără presupunerea că ordinea introducerii este ordinea evenimentelor.
- [ ] **V3 — Obligații și document.** Creează o obligație cu document asociat prin contract și o scadență canonică. Închiderea, modificarea și arhivarea actualizează Calendarul; verifică unicitatea la cereri concurente.
- [ ] **V4 — Flux complet.** Verifică obligație → document → un reminder → email, apoi editare/anulare și acces revocat. Probează document cu expirare independentă fără a genera tacit o dublură pentru aceeași obligație.

## Acceptare

- Datele din altă familie nu pot fi legate prin profil, citire, service, obligație sau document.
- Calendarul citește scadența obligației; nu oferă editarea unei copii concurente. Retry nu dublează obligația ori proiecția.
- Documentul privat nu devine accesibil prin simpla asociere cu un vehicul vizibil.
- Istoricul și corecțiile de kilometraj sunt consecvente; consumul nu se deduce din date insuficiente.

## Surse și căi viitoare

Consultă operațiile `Vehicles_` și `Vehicle_`, regulile pentru scadență unică și scenariile de concurență/kilometraj din pachetul v1.2.

Implementarea va sta în `backend/app/Modules/Vehicles/` și `frontend/src/modules/vehicles/`, cu acces numai la contractele publice ale dependențelor.

Verificarea automată în registre, telematica și integrările cu furnizori de asigurări sunt în afara MVP.
