# Animale

Stare la 24 octombrie 2026: **implementat și verificat local**. Utilizatorul a cerut Animale după Sarcini recurente, fără întrebări sau feedback intermediar, apoi push. Modulul este în **Mai multe pe mobil și desktop**. Livrează un singur layout: fotografie și identitate, cu filele stabile **Prezentare / Îngrijire / Documente**. Acceptarea vizuală rămâne pentru utilizator.

Identitatea animalului și istoricul aparțin modulului Pets, separat de profilurile umane. Membrii activi pot consulta; proprietarul și adulții pot administra. Membrii cu acces limitat consultă profilul, îngrijirea și numai documentele autorizate lor.

## Pașii livrați

- **AN1 — Profil, implementat.** Creare, listă, detaliu, editare, fotografie privată și arhivare/restaurare. Numele și specia sunt obligatorii; rasa și data nașterii sunt opționale. Filtrul Active/Arhivate și selecția animalului păstrează pagina curentă la schimbarea familiei.
- **AN2 — Greutate și îngrijire, implementat.** Înregistrări Greutate, Vaccinare, Vizită și Îngrijire, cu titlu, dată, greutate în kg, cost opțional în lei și notițe. Prezentare afișează înregistrările recente; Îngrijire are filtrare după tip, Arhivate și paginare. Modificările și arhivarea folosesc versiuni; istoricul nu este șters.
- **AN3 — Document și scadență, implementat.** Se asociază documente existente prin contractul public Documents. Următoarea scadență este opțională și publicată în Calendar prin contractele sale. Calendarul deschide direct înregistrarea canonică din Animale.
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
