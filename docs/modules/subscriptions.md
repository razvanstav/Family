# Abonamente

## SB3 — Detaliu abonament, 25 octombrie 2026

**În lucru.** Utilizatorul cere un design mai îngrijit pentru fereastra SkyShowtime din captura furnizată. Se modifică un singur layout: detaliul abonamentului, cu iconiță oficială locală, metadate grupate compact și acțiuni aliniate, păstrând funcțiile, accesul și dezvăluirea explicită a secretelor. Formularele și lista nu sunt redesenate. Se arată rezultatul local pentru feedback; această cerere nouă nu include publicarea noului design.

## SB2 — Pagină separată și navigare fluidă, 25 octombrie 2026

**Publicat:** commit `08c60f8` pe `origin/codex/documente-design`; [deploy 40141536615](https://github.com/razvanstav/Family/actions/runs/40141536615) reușit. HTTPS `/up`, `/app/`, JS/CSS și iconițele Netflix/Spotify răspund 200. Bundle-ul public `BrH9asnZ/DJO_Wuan` coincide cu buildul verificat și include navigarea, filele, iconițele și meniurile noi. Fără migrații noi sau probe pe date reale; granturile runtime/workerii nu sunt declarați verificați independent.

**Implementat, verificat și publicat, după feedbackul utilizatorului.** Abonamente are intrare proprie după Cheltuieli în meniul desktop și în Mai multe pe mobil; bara mobilă păstrează cele cinci poziții. Calendar deschide direct pagina Abonamente. Compoziția intermediară `ExpensesWorkspace` și sloturile de file rămase fără apelanți sunt eliminate; Cheltuieli își păstrează filtrele proprii.

Familie / Partajate cu mine folosesc direct `shared/SectionTabs`, imediat după `PageBackdrop`, la aceeași poziție și cu aceleași dimensiuni ca Bucătărie/Cheltuieli: maximum 480 px pe desktop, întreaga lățime pe mobil, înălțime 54 px. Actualizarea este în acțiunile antetului. Bara proprie cu padding separat și toate stilurile ei au fost eliminate. La actualizarea aceluiași filtru, lista rămâne vizibilă și suprafața expune `aria-busy`; iconița de actualizare indică cererea în curs. Schimbarea filtrului ascunde rândurile celuilalt filtru. Încărcarea inițială are o singură apariție discretă a rezultatelor, fără animații pe fiecare card. Erorile de autorizare golesc lista și închid detaliul, iar mecanismul A1 de suspendare/reautorizare rămâne activ.

Cei 12 furnizori predefiniți au iconițe oficiale locale de 28 px, alese după `service_key`; numele rămâne în textul alăturat. Serviciile necunoscute/personalizate păstrează iconița generică. Proveniența este în `frontend/src/assets/subscriptions/README.md`. Asseturile rămân fișiere distincte, nu imagini inline în JavaScript, și nu generează cereri către furnizori la consultare.

Dependența comună de navigare și verificările sunt în [Interfață SB2](interface.md#sb2--abonamente-și-tranziții-25-octombrie-2026). Previzualizarea folosește buildul real cu un server API fictiv, numai GET, separat de baze și de serverul aplicației. Nu confirmă backendul, migrațiile ori performanța rețelei de producție. Nu s-au creat abonamente sau date de acces reale.

## SB1 — Abonamente și acces partajat, 24 octombrie 2026

**Publicare:** commit `c5c81a2`, [deploy 40130862434](https://github.com/razvanstav/Family/actions/runs/40130862434) reușit. Migrația202023 apare efectiv `DONE`, cinci operațiuni GRANT executate; HTTPS `/up`, `/app/` și JS200, bundle `btuOof81/BB7XjicJ` confirmat. Diagnosticul suplimentar al granturilor runtime/workerilor rămâne limitat de accesul Docker refuzat; nu este declarat verificat independent. Nu au fost create conturi, parole de serviciu sau abonamente de probă pe server.

**Implementat, verificat și publicat.** Cerere explicită: servicii precum Netflix/streaming/Digi Sport, acces automat pentru familie și partajare către alte conturi ale platformei prin email. Utilizatorul a confirmat și păstrarea datelor de acces. Push și deploy la final, împreună cu eliminarea Cronologiei din Amintiri.

### Scop și acces

- Inițial, Abonamente apărea în Cheltuieli prin compoziția din App; poziționarea este înlocuită explicit de SB2. Modulul are lista Familiei și Partajate cu mine, detaliu și formular; nu importă modulele Cheltuieli sau Calendar în frontend.
- Serviciu predefinit sau nume liber, link, cost opțional în RON/EUR/USD, frecvență și următoarea plată opțională. Datele introduse apar și în Calendar prin contractul său public. Nu se creează automat cheltuieli achitate sau tranzacții bancare.
- Familia în care este creat vede abonamentul automat. Numai autorul cu drept de scriere modifică, invită, revocă sau arhivează. Conturile limitate consultă. O invitație externă se adresează unui cont existent, normalizat după email; destinatarul acceptă explicit înainte de a vedea detaliile și datele de acces. Membrii familiei destinatarului nu primesc acces.
- Loginul, parola și PIN-ul sunt opționale, criptate prin infrastructura Laravel cu cheia aplicației. Metadatele/lista/auditul nu conțin secrete. Dezvăluirea este o cerere separată, reautorizată și fără cache; clientul le șterge din starea vizibilă la ascundere/plecare/închiderea detaliului. Editarea păstrează secretul existent până la înlocuire sau eliminare explicită.
- Revocarea, arhivarea și pierderea apartenenței autorului se verifică în cererile noi. Revocarea nu poate șterge o parolă deja văzută; interfața explică schimbarea ei în serviciul original când este necesară. Abonamente nu autentifică utilizatorul pe serviciul extern.
- Conturile existente nu au emailul verificat. Invitația necesită și un cod aleatoriu, arătat o singură dată autorului pentru trimitere printr-un canal de încredere, valabil șapte zile și legat de abonament/destinatar. Baza păstrează numai hashul; lista destinatarului nu primește codul. Acceptarea cere codul, îl consumă și este respinsă după expirare/revocare sau pentru alt cont. Acest control este separat de înscriere, care rămâne neschimbată.
- RLS cu familie și actor autentificat, versiuni If-Match și tranzacții; fără copierea datelor altor module sau lărgirea permisiunilor existente. Identificarea prin email nu trimite SMTP în acest pas; invitația apare în Partajate cu mine.

### Intrări și contracte

Backend `Modules/Subscriptions`, tabele proprii și adaptor `Calendar/Contracts/DatedRecords`; rute sub `/families/{family}/subscriptions`. Frontend `modules/subscriptions`, pagină compusă direct în App și deschidere din Calendar (poziționare SB2). Catalogul conține numai nume și linkuri oficiale, fără tarife inventate; opțiunea liberă permite alte servicii.

`subscriptions` păstrează metadatele, ciphertextul și versiunea, iar `subscription_shares` destinatarul, starea, hashul și expirarea codului. Migrația aditivă `2026_10_24_202023` are RLS forțat, FK în aceeași familie, unicitate abonament/destinatar și gărzi DB: destinatarul poate răspunde invitației și incrementa numai versiunea părintelui, fără schimbarea conținutului. `TenantContext` setează actorul numai din sesiunea autentificată, în tranzacția existentă. Endpointurile folosesc If-Match, cu cod unic returnat numai creatorului la POST shares; citirile normale nu returnează cod, hash sau secrete. Ștergerea abonamentului păstrează rândul istoric și golește ciphertextul, cu consecința explicită în confirmare.

### Verificări și pas rămas

- **Backend:** 14 teste / 203 aserțiuni pentru SubscriptionTest și gărzile modulare; alte 13 teste / 197 aserțiuni CalendarDatedRecordsTest și HomeTasksTest. Total distinct **27 teste / 400 aserțiuni**, serial pe memoria_test, numai fixture-uri UUID și curățare țintită, fără TRUNCATE. Acoperă criptarea și păstrarea/eliminarea, absența secretelor din JSON/audit/outbox, ACL familie/rol limitat/destinatar, cod greșit/alt destinatar/expirat/consumat, normalizarea emailului, revocare/reinvitare, versiuni vechi, Calendar cu acces individual și RLS/trigger. Calendar folosește nouă adaptoare, cu același număr de query-uri indiferent de numărul rândurilor. Pint trecut.
- **Frontend:** 56 teste comune trecute, inclusiv contractul destinației Calendar și două teste pentru payloadul/curățarea secretelor; TypeScript/Vite și Prettier/diff trecute. Build final `btuOof81/BB7XjicJ`, după clarificarea consecinței ștergerii; avertismentul JS peste500kB rămâne.
- **Migrație:** 202023 și granturile celor două tabele aplicate în memoria_test și memoria. Amprentele surselor existente și cheltuielilor au rămas identice; fără conturi sau abonamente reale create în memoria.
- **Browser:** desktop, 390 și320px, două familii și trei conturi fictive în memoria_test. Creare Netflix cu cost, scadență și date de acces fictive; afișare/ascundere explicită; invitație și copiere cod; cod greșit păstrează eroarea în fereastră, codul corect oferă acces. Destinatarul nu are editare sau partajare, iar celălalt membru din familia sa vede ambele liste goale. Scadența din Calendar este Persoane alese și deschide abonamentul; după revocare lista destinatarului este goală. Fără overflow observat la390/320, consola probei fără erori/warninguri observate. Capturi `.runtime/sb1-list-mobile.png`, `.runtime/sb1-shared-detail-320.png`. Fixture-urile și server8001 curățate/oprit, viewport resetat.
- **Limite:** fără SMTP real, conturi/abonamente reale sau dispozitiv fizic. Tarifele și regulile serviciilor externe nu sunt importate ori verificate automat. Backupul bazei trebuie păstrat împreună cu cheia aplicației existente; fără ea ciphertextul nu se poate decripta. Acceptarea vizuală rămâne la utilizator.
