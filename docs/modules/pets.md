# Animale

## AN5 — Adăugare directă de documente, 25 octombrie 2026

**Implementat; verificarea integrată și publicarea sunt în curs.** În fila Documente, „Adaugă document” deschide formularul canonic Documente, compus în App prin slotul tipizat `DocumentCreationRender`. Nu se mai încarcă lista documentelor pentru a alege un element existent. Salvarea creează documentul și îl asociază animalului prin endpointul existent, cu autorizarea Documente păstrată. Fișierul este opțional; numele, categoria, expirarea și accesul urmează regulile canonice. Reîncercarea asocierii reutilizează documentul deja creat, fără o a doua creare.

Titlul fiecărui document deschide detaliul canonic prin `DocumentDetailRender`, inclusiv când fișierul lipsește sau este în verificare/respins, astfel încât un fișier poate fi atașat ulterior. Starea fișierului și eliminarea asocierii rămân disponibile; eliminarea păstrează documentul original. Formularul vechi `PetDocumentForm`, importul și selectorii pentru linkurile înlocuite au fost eliminate. Pets nu importă modulul Documente și nu schimbă permisiunile documentului.

**Verificat până acum:** cele 3 teste de payload Pets și cele 3 gărzi frontend de module trecute. Verificarea TypeScript/build/browser și rezultatul deployului se completează după integrarea în App; nu sunt declarate observate în acest punct.

**DC1, 24 octombrie 2026:** nașterea animalului, data îngrijirii și scadența următoare, inclusiv încheiată, apar în Calendar prin `PetDatedRecords`, fără dubluri cu proiecțiile active. Arhivarea rămâne respectată; accesul documentelor asociate nu se lărgește, iar cele fără fișier au stare neutră. [Contract și verificări Calendar DC1](calendar.md#dc1--toate-datele-introduse-de-utilizator-24-octombrie-2026).

Stare la 24 octombrie 2026: **implementat și verificat local**. Utilizatorul a cerut Animale după Sarcini recurente, fără întrebări sau feedback intermediar, apoi push. Modulul este în **Mai multe pe mobil și desktop**. Livrează un singur layout: fotografie și identitate, cu filele stabile **Prezentare / Îngrijire / Documente**. Acceptarea vizuală rămâne pentru utilizator.

Identitatea animalului și istoricul aparțin modulului Pets, separat de profilurile umane. Membrii activi pot consulta; proprietarul și adulții pot administra. Membrii cu acces limitat consultă profilul, îngrijirea și numai documentele autorizate lor.

## Pașii livrați

- **AN1 — Profil, implementat.** Creare, listă, detaliu, editare, fotografie privată și arhivare/restaurare. Numele și specia sunt obligatorii; rasa și data nașterii sunt opționale. Filtrul Active/Arhivate și selecția animalului păstrează pagina curentă la schimbarea familiei.
- **AN2 — Greutate și îngrijire, implementat.** Înregistrări Greutate, Vaccinare, Vizită și Îngrijire, cu titlu, dată, greutate în kg, cost opțional în lei și notițe. Prezentare afișează înregistrările recente; Îngrijire are filtrare după tip, Arhivate și paginare. Modificările și arhivarea folosesc versiuni; istoricul nu este șters.
- **AN3 — Document și scadență, implementat.** Documentele se adaugă direct din Animale prin formularul canonic Documente și contractul public Documents (AN5); asocierile existente sunt păstrate. Următoarea scadență este opțională și publicată în Calendar prin contractele sale. Calendarul deschide direct înregistrarea canonică din Animale.
- **AN4 — Flux in-app, implementat și verificat.** Reminder în aplicație în ziua scadenței sau cu o zi înainte, la 09:00, Ora României, pentru destinatari autorizați. Editarea, încheierea, redeschiderea și arhivarea actualizează proiecția și livrările. Emailul nu este inclus în livrarea curentă.

## Reguli de date și acces

- Data înregistrării descrie îngrijirea efectuată și nu poate fi în viitor. **Următoarea scadență este distinctă**: `next_due_on` și `next_due_completed_at`. Încheierea/redeschiderea scadenței nu creează o vizită ori vaccinare fictivă și nu schimbă data înregistrării. Schimbarea scadenței o redeschide; editarea altor câmpuri îi păstrează starea.
- Greutatea este obligatorie numai la cântărire: 0,001–9.999,999 kg, maximum trei zecimale. Costul acceptă 0–9.999.999,99 lei, maximum două zecimale. Formularul normalizează virgula fără a transforma sumele în numere binare. Data nașterii și data înregistrării pornesc de la 1900; scadența trebuie să fie cel puțin data înregistrării și cel târziu 31 decembrie 2099.
- Asocierea nu modifică permisiunile documentului. Titlul și linkul apar numai dacă politica Documente autorizează membrul curent; accesul la conținut este verificat din nou la deschidere. Eliminarea asocierii păstrează documentul original.
- Fotografiile folosesc infrastructura privată existentă: JPG/PNG/WebP, maximum 10 MB și 16 megapixeli. Stările în verificare/respins sunt vizibile, iar conținutul este autorizat pe familia curentă.
- Arhivarea animalului sau înregistrării oprește scadențele și reminderele, păstrând istoricul și legăturile. Restaurarea nu redeschide o scadență deja încheiată. Preferințele reminderului se păstrează la încheiere; eliminarea scadenței le curăță. Livrarea reverifică sursa și destinatarii, inclusiv după revocare.
- Nu se creează conturi sau identități umane pentru animale. Diagnosticul, dozajele, integrările cu clinici și dosarele medicale umane sunt în afara acestui pas.

## Contracte și fișiere

Backendul deține tabelele `pets`, `pet_entries` și `pet_documents`, cu FK compuse și RLS pe familie. Migrația este `2026_10_24_000128_create_pet_tables.php`. `PetController` gestionează profilul, fotografia, istoricul și asocierile; `PetCalendar` sincronizează atomic cu sursa. `PetDeadlineSource` furnizează referințele canonice versionate pentru tipul `pet_entry`.

Integrarea folosește exclusiv `Documents/Contracts/DocumentLinks`, `Calendar/Contracts/SourceItems`, `Calendar/Contracts/DeadlineSource` și infrastructura comună de familii/media. Compoziția este în `AppServiceProvider`; Calendar și Pets nu importă implementările celuilalt modul.

Rutele sunt sub `/families/{family}/pets`:

- `GET/POST /`, `GET/PATCH /{pet}`, `POST /{pet}/archive` cu `archived` boolean.
- `GET/POST /{pet}/photo`, `POST /{pet}/photo/retry`; înlocuirea/eliminarea folosesc `FormData` și versiunea profilului.
- `GET/POST /{pet}/entries`, `GET/PATCH /{pet}/entries/{entry}`, `POST /{pet}/entries/{entry}/archive`, `PATCH /{pet}/entries/{entry}/completion` cu `completed` boolean pentru scadență.
- `GET/POST /{pet}/documents`, `DELETE /{pet}/documents/{document}`; numai documente deja autorizate.

Lista îngrijirii acceptă `kind`, `archived` și `page`, cu 20 de rânduri și răspuns `items/page/has_more`. Payloadul conține datele complete pentru formular, inclusiv versiunea, scadența, starea ei și preferințele reminderului. Listele folosesc JOIN-uri și număr constant de query-uri; versiunile sunt obligatorii prin `If-Match` la mutațiile profilului și înregistrărilor.

Frontend: `frontend/src/modules/pets/Pets.tsx`, `PetForms.tsx`, `petData.ts`, `petFormData.ts` și `pets.css`. App compune navigația și callbackul Calendar → Animale prin `PetRequest`, cu remontare la schimbarea utilizatorului/familiei. `PageBackdrop` folosește antetul decorativ comun `pets`; `PrivatePhoto`, `SectionTabs`, `DatePicker`, `ReminderFields` și formatarea monetară sunt comune. Deschiderea formularului reutilizează elementul listei; citirea de detaliu este rezervată țintei Calendar absente din pagină și reîncărcării explicite după conflict.

## Verificări și încheiere

**Automat:** 87 teste backend / 934 aserțiuni trecute, serial numai pe memoria_test: PetTest (13 teste), CalendarEventTest, CalendarReminderTest, VehicleCalendarTest, TaskCalendarTest, TaskRecurrenceTest, TaskRecurrenceDateTest și ModuleBoundariesTest. Sunt acoperite drepturile owner/adult/limited, familia străină și RLS, revocarea, versiunile, validările, fotografiile și bytes, documentele private, lipsa N+1, paginarea, proiecția și rollbackul, livrarea/idempotenta/replanificarea și arhivarea cu reminder activ. Restaurarea păstrează scadența explicit încheiată.

Toate mutațiile Pets au tranzacții locale explicite, cu integrarea Calendar/media, auditul și răspunsul în interior. Prima rulare a prins salvarea parțială după o validare de reminder respinsă; corecția este probată la creare și editare, inclusiv păstrarea versiunii, datei și proiecției vechi la 422. Nu ne bazăm pe tranzacția exterioară TenantContext, deoarece Laravel poate randa validarea înaintea ei. Revizuirea independentă a verificat toate cele 11 mutații și integrarea finală.

28 teste frontend și build TypeScript/Vite trecute. Cele trei teste noi din frontend/tests/pets.test.mjs acoperă precizia/virgula zecimală, eliminarea scadenței cu curățarea reminderului și schimbarea tipului fără greutate rămasă, păstrând preferințele scadenței existente. Gărzile de module, Prettier, Pint și git diff --check au trecut. Linkurile documentelor au minimum 44 px. Buildul are avertismentul existent de chunk peste 500 kB (533,01 kB JS); nu este un eșec de build.

**Browser:** Ana/Popescu, serverul 8001 pe memoria_test: profil cu dată de naștere, fotografie scanată și afișată, greutate 26,375 kg, costuri cu virgulă, vaccinare cu reminder pentru doi membri, document privat asociat, Calendar → deschidere directă, scadență mutată 28 → 29 octombrie, încheiere/redeschidere cu data istorică păstrată. Desktop 1280×900 și mobil 390×844/320×800 fără overflow observat; formularul lung rămâne derulabil, câmpurile/butoanele măsurate au minimum 44 px. Antetul este 130/120 px; poziția absolută a filelor la 390 px rămâne 766,44 px între Documente și Prezentare. Escape/focus în formular și selecția Mai multe verificate. Consola nu are erori/warninguri observate. Capturi ignorate: .runtime/pets-populated-desktop.jpg, pets-populated-mobile.jpg, pets-populated-320.jpg și pets-care-form-320.jpg.

**Instalare și limite:** migrație aditivă și granturi în memoria_test și memoria; instantaneele sarcinilor și cheltuielilor existente au rămas identice. În aplicația reală am verificat numai starea goală și formularul, navigația și schimbarea Popescu/Ionescu; nu s-au salvat animale fictive. Tabul și serverul 8001 au fost închise, viewportul resetat, Ana/Popescu → Animale fără dialog a rămas deschis. Arhivarea/revocarea/livrarea efectivă sunt probate prin API și ceas controlat; nu sunt reverificate în browser la ora reală sau pe dispozitiv fizic. Conținutul documentelor este probat prin API; în browser s-au verificat asocierea și linkul canonic. Fără email, push nativ sau deploy. Evidența Git este în STATE.

Antetul decorativ a fost generat cu instrumentul integrat ImageGen; promptul și proveniența sunt în frontend/src/assets/headers/header-pets.provenance.json. Fotografia folosită în proba UI este același asset sintetic și nu reprezintă un animal real al familiei.

## Integrare cu lucrul de pe celălalt PC

La push, ramura remote avea deja Amora/Administrare și optimizarea deployului (a624c50). Integrarea prin merge păstrează acele modificări și Pets/S3, cu review independent pe App/MobileNavigation și backend. După rezolvarea conflictelor: 102 teste backend / 1173 aserțiuni, serial memoria_test, 28 frontend și build CJLagKSv.js/BjpoqKFQ.css trecute. Buildul comun are avertismentul existent JS peste 500 kB (548,11 kB). Browser 8000: Amora, formularul Repetare și Animale din Mai multe pe desktop/mobil 390 verificate, fără overflow sau erori de consolă observate. Capturi .runtime/pets-amora-merged-desktop.jpg și pets-amora-merged-mobile.jpg. Probele populate de mai sus preced această integrare și au brandingul anterior.

Schema locală Windows include acum și cele trei migrații aditive Administrare; snapshoturile datelor existente sunt păstrate. Nu s-au importat baze, media ori credențiale de pe macOS și nu s-a acordat acces admin unui cont local. Mergeul se publică fără force push; evidența publicării este în STATE.
