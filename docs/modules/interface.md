# Interfață comună

Stare: direcția vizuală și componentele comune există. H1 Acasă este implementat și verificat local la 21 octombrie 2026, în limitele consemnate mai jos. Fișa se aplică și fluxului UI modificat în fiecare etapă; nu introduce o redesenare generală.

## Fișiere relevante

- Tokenuri, tipografie și stiluri comune: `frontend/src/styles.css`.
- Shell, context de familie și autentificare: `frontend/src/app/App.tsx`.
- Dialog accesibil: `frontend/src/shared/Modal.tsx`.
- Client API: `frontend/src/shared/api.ts`; se aliniază rutelor efectiv implementate.
- Referință principală de structură: [mock-up ales](../../artifacts/design-reference-layout.jpeg). Paletă și atmosferă: `artifacts/design-reference-warm.png`; referință secundară: `artifacts/design-reference.png`.
- Bucătărie are și [referința specifică aleasă](../../artifacts/kitchen-reference.png): rețete în interiorul Bucătăriei, fotografii, carduri și etichete compacte; funcțiile se adaugă numai când sunt reale. K3a adaugă secțiunea „Mese”, zile, vot binar și comentariu personal, la cererea utilizatorului. Implementare și probe: [Bucătărie](kitchen.md).

## Referință pe ecrane — 21 octombrie 2026

Decizie de design salvată, nu funcționalitate deja implementată. Numerotare de la stânga la dreapta: 1–4 sus, 5–8 jos. Se aplică incremental când lucrăm la ecranul respectiv, păstrând Helvetica, crem, verde închis și iconurile semantice existente. Nu se copiază accentul albastru al aplicației imobiliare.

| Ecran din referință | Structură preluată | Aplicare în Memoria Familiei |
| --- | --- | --- |
| 1 — Acasă/listă | Antet compact, clopoțel, căutare, filtre, secțiuni cu fotografii și etichete discrete | Acasă și listele modulelor; documente recente, momente și acces la module numai cu date reale. Fără slogan sau secțiune de recomandări inventate. |
| 2 — Detaliu | Imagine principală, titlu, informații grupate în blocuri mici, persoană asociată, galerie și acțiune principală | Amintire, rețetă, animal sau vehicul. Fotografia apare dacă resursa are una; documentele nu primesc imagini decorative care mimează conținutul. Acțiunile și accesul rămân explicite. |
| 3 — Calendar/programare | Rezumat compact al obiectului, alegerea tipului, zile selectabile, intervale orare și acțiuni jos | Formular Calendar și configurarea reminderelor: sursă, dată/oră, destinatari și momentul notificării. Datele fără oră rămân distincte. Pe mobil: blocuri verticale și butoane tactile, fără calendar miniatural ilizibil. |
| 4 — Confirmare | Stare de succes, rezumat al resursei, informații scurte alăturate și următoarea acțiune | Eveniment salvat/reminder programat: data, ora, destinatarii și starea reală a livrării. Confirmare separată doar când rezultatul necesită o decizie; salvările obișnuite folosesc feedback compact. |
| 5 — Listă cu fotografie | Filtre sus, rânduri cu miniatură în stânga și informații/acțiune în dreapta | Rețete, amintiri, animale și vehicule. Documentele își păstrează cardurile compacte stabilite; familia își păstrează rândurile de 80–90 px. |
| 6 — Listă filtrată | Aceeași structură după selecție, filtru activ și număr real de rezultate | Filtre consistente între module. Nu introduce Favorite fără o funcție reală și o decizie separată. |
| 7 — Listă de mesaje | Numai ritmul rândurilor, marcajul necitit, momentul și separarea elementelor | Centru de notificări: iconul sursei, titlu direct, dată/oră, stare citit/necitit și deschiderea resursei. Nu copiem conversații, avatar de interlocutor sau status online. |
| 8 — Conversație | Nu se preia ca ecran de produs | Fără mesaje/chat, apeluri, câmp de compunere sau buton de trimitere. Detaliul unei notificări conduce la resursă, nu la o conversație. |

## Adaptare desktop și legături între ecrane

- Sidebar pentru modulele disponibile; antet cu familia activă, căutare contextuală, notificări și cont. Ierarhia și spațierea provin din mock-up, cu fotografii personale și carduri neutre.
- Dashboard Acasă H1 implementat: zonă principală pentru categorii și documente recente; coloană pentru calendar compact, evenimente apropiate și remindere personale. Grila se restrânge la o singură coloană pe ecrane înguste; listele extinse au pagini proprii. Fără statistici sau elemente fictive.
- Calendarul selectează ziua și arată evenimentele ei; reminderele sunt o listă separată cu sursa, scadența și starea. Crearea/editarea folosesc structura ecranului 3. O notificare deschide evenimentul ori documentul asociat, după verificarea accesului.
- Clopoțelul deschide un panou pe desktop și o foaie/pagină pe mobil. Indicatorul necitit reflectă date reale; stările goale/eroare sunt explicite. Implementarea se leagă de Calendar C3 și transportul comun P4, fără modul de mesagerie.
- Detaliile desktop folosesc două coloane când există galerie și informații suficiente; formularele scurte rămân într-un dialog. Acțiunea principală rămâne ușor de găsit, fără bare fixe care acoperă conținutul sau tastatura.
- Autentificarea, invitațiile și administrarea accesului preiau containerele, spațierea și controalele, fără imagini sau texte decorative. Aplică această fișă la pasul curent; nu deschide automat Calendar/Acasă pentru că referința le descrie.

## Verificare pentru fiecare flux modificat

- [ ] Titlurile și butoanele numesc obiectul sau acțiunea; etichetele câmpurilor rămân prezente. Textul ajutător apare numai pentru restricții, erori și consecințe necesare.
- [ ] Paleta caldă, Helvetica cu fallback, iconurile semantice și dimensiunile cardurilor respectă AGENTS; stilurile comune sunt modificate în locul existent.
- [ ] Pe mobil și desktop nu există overflow, titluri inaccesibile sau controale sub 44 px. Verifică cel puțin un viewport îngust de 360 px și unul desktop.
- [ ] Tastatura poate parcurge fluxul; focusul este vizibil. Dialogul captează/restabilește focusul, Escape funcționează, iar fundalul nu se derulează.
- [ ] Încărcarea, lipsa datelor, lipsa rezultatelor, eroarea și retry au comportament real. Previne dublul submit și gestionează răspunsurile întârziate după schimbarea familiei.
- [ ] Feedbackul și tranzițiile sunt discrete; `prefers-reduced-motion` și safe areas sunt respectate.

## Acasa H1

Stare: **implementat și verificat local**, 21 octombrie 2026. Utilizatorul a confirmat Acasă prima în meniu și ca pagină după autentificare. După K1, ordinea confirmată este identică pe desktop și mobil: Acasă → Familia mea → Documente → Bucătărie → Calendar; Cont rămâne accesibil separat și în bara mobilă. Datele provin din baza aplicației, inclusiv datele demo existente; H1 nu a adăugat exemple permanente pentru a umple dashboardul.

### Contract și surse

- GET `/api/v1/families/{family}/home?month=YYYY-MM`, autentificat prin `TenantContext`, RLS și `PrivateResponse`; răspuns privat fără cache. Luna este validată în intervalul 1900–2100. Endpointul nu scrie date și nu introduce tabele/migrații.
- Documents expune `DocumentAccess::recent`: primele patru documente autorizate după creare, cu ordine stabilă după ID la egalitate. Politica existentă include proprietatea, familia și granturile selective. Rezumatul conține numai ID, titlu, categorie, acces, MIME și starea verificării; fără chei de stocare, bytes, granturi, date de profil sau numele fișierului original.
- Calendar expune `CalendarOverview::home`, folosind `CalendarAccess::visible`, politica comună cu C1. Marcajele calendarului provin numai din evenimente vizibile, active, ale lunii selectate. Sunt returnate maximum cinci evenimente în următoarele 30 de zile, inclusiv azi; evenimentele cu oră deja trecute sunt excluse, cele fără oră rămân vizibile în ziua lor. Nu se transmit notițele.
- `CalendarReminders::upcoming` returnează maximum cinci ocurențe în așteptare adresate utilizatorului curent, cu sursă/versiune și apartenențe încă autorizate, neexpirate, până la 30 de zile înainte. Ocurențele livrate/anulate sunt excluse; momentele sunt serializate cu offset explicit.
- Home consumă aceste contracte publice și nu citește direct tabelele altor module. Interogările listelor sunt limitate, cu joinuri și fără lookupuri per element. Detaliile și fișierele se deschid pe rutele canonice și verifică accesul actual; rezumatele nu acordă drepturi suplimentare.
- Interfața se reîncarcă la revenirea pe Acasă, schimbarea lunii, „Actualizează” și la 60 de secunde cât pagina este vizibilă. Remontarea după schimbarea familiei/contului și protecția contra răspunsurilor întârziate împiedică afișarea rezultatului cererii precedente. Nu există cache offline pentru date private.
- `frontend/src/shared/DocumentCard.tsx`, `documentCards.css`, `CalendarMonth.tsx`, `calendarMonth.css` și `calendarDates.ts` sunt refolosite de paginile canonice și de Acasă. Tipul C1 este în `modules/calendar/calendarTypes.ts`. Stilurile mutate au fost scoase din CSS-ul modulelor, păstrând numai regulile specifice. Navigarea din Acasă este coordonată de `App.tsx`; modulele nu importă ecranele altor module.
- Fișiere de intrare: `backend/app/Modules/Home/Http/HomeController.php`, `backend/app/Modules/Calendar/Application/CalendarOverview.php`, `frontend/src/modules/home/Home.tsx` și `home.css`, ruta din `backend/routes/web.php`.

### Verificare H1 — 21 octombrie 2026

- `HomeOverviewTest.php`: **7 teste / 84 aserțiuni** trecute. Acoperă autentificarea, familie străină/invalidă, revocare în sesiune existentă, privat/selectiv și grant retras, răspuns fără metadate de stocare, headere fără cache, zile/liste autorizate, arhivare, date cu/fără oră, fereastra de timp și luna, remindere personale/livrate și autor revocat, rol limitat, listă goală și luni invalide. Proba cu 12 documente/evenimente/remindere suplimentare păstrează numărul de interogări și limitele 4/5/5.
- După extragerea politicii Calendar au trecut și cele 9 teste C1. Prima rulare comună a avut o eroare în fixture-ul de sortare Home: seederul pornea înaintea ceasului controlat. S-a corectat exclusiv setupul Home și s-au reluat cele șapte teste Home; C1 nu a fost rerulat fără schimbări suplimentare. Nu s-au reluat suitele backend F/D/A/C3 încheiate.
- Build TypeScript/Vite, 20 teste frontend, Pint și `git diff --check` trecute. Testele de date/calendar folosesc acum utilitarul din shared.
- Browser desktop 1376 × 1244 și mobil 360 × 800: card/categorie → Documente, detaliu și formular upload, creare eveniment din Acasă, marker de zi și apariția evenimentului/reminderului în dashboard, zi selectată și „Toată luna”, reminder → detaliu canonic, arhivare → dispariție din dashboard. Popescu/Ionescu afișează date separate; schimbarea familiei pe Acasă păstrează pagina. Deconectare și autentificare demo Ana → Acasă verificate. Escape închide dialogurile; Tab/Shift+Tab și Enter în meniu, cu focus vizibil, verificate.
- La 360 px: fără overflow, carduri de documente de 156 px, zile de 45 × 44 px și aceeași ordine a meniului. Capturi ignorate: `.runtime/h1-home-desktop.png` și `.runtime/h1-home-mobile.png`; captura mobilă include proba fictivă înainte de curățare.
- Evenimentul de probă `4edba548-20ca-4023-b295-6617eacfd3e1` a fost arhivat și apoi eliminat împreună cu destinatarii, ocurențele și două intrări de audit; verificare DB: zero rânduri rămase pentru acel ID în cele patru tabele. Baza aplicației, documentele, apartenențele și parolele existente nu au fost resetate.
- Nu s-au provocat separat eroarea de rețea/reîncercarea în browser, nu s-au verificat dispozitive fizice și nu s-a făcut un audit general de securitate. Acceptarea privește suprafața H1 și contractele modificate, nu pregătirea întregului produs pentru producție. Modulele noi, emailul, exporturile și infrastructura de găzduire rămân pași separați.

## Limite de arhitectură

Modulele consumă shared UI și API; nu importă ecranele altui modul. Când `App.tsx` sau un modul devine greu de întreținut, extrage componente/hooks după responsabilități concrete, păstrând comportamentul și contractele. Nu face refactorizare largă doar pentru a muta fișiere.

Nu adăuga statistici, meniuri, grafice sau dashboarduri fără date și acțiuni implementate. Nu porni cache offline pentru conținut privat. Testarea vizuală în browser se raportează distinct de testarea pe dispozitive reale.
