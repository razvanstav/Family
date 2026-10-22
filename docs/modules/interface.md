# Interfață comună

Stare: H2 Acasă are feedback pozitiv, KH1 Bucătărie este implementat și verificat local. Ultima cerere explicită este FD1: Documente în Familia mea, cu dosar comun și dosare pe persoană, reducând navigația principală la cinci secțiuni. Planificatorul rămâne schiță; probele istorice H1/H2 rămân mai jos.

## Fișiere relevante

- Tokenuri, tipografie și stiluri comune: `frontend/src/styles.css`.
- Shell, context de familie și autentificare: `frontend/src/app/App.tsx`.
- Dialog accesibil: `frontend/src/shared/Modal.tsx`.
- Client API: `frontend/src/shared/api.ts`; se aliniază rutelor efectiv implementate.
- Referință principală: [MVP de familie — 22 octombrie](../../artifacts/design-reference-family-mvp.png), copia imaginii furnizate de utilizator. [Mock-upul anterior](../../artifacts/design-reference-layout.jpeg) rămâne referință secundară de structură. Paletă și atmosferă: `artifacts/design-reference-warm.png`.
- Bucătărie are și [referința specifică aleasă](../../artifacts/kitchen-reference.png): rețete în interiorul Bucătăriei, fotografii, carduri și etichete compacte; funcțiile se adaugă numai când sunt reale. K3a adaugă secțiunea „Mese”, zile, vot binar și comentariu personal, la cererea utilizatorului. Implementare și probe: [Bucătărie](kitchen.md).

## Referință pe pagini — 22 octombrie 2026

Utilizatorul a cerut adaptarea produsului existent, incremental. Păstrăm Helvetica, paleta caldă și regulile de text direct; preluăm compoziția și ritmul din imagine. Nu introducem sloganul, statistici, ratinguri, favorite, planificare/cumpărături ori căutare globală fără funcționalitate reală. Navigația existentă și pagina implicită rămân cele stabilite.

**Ritm explicit:** finalizăm și arătăm un singur layout, așteptăm feedback, apoi alegem pasul următor. După Acasă și Bucătărie, utilizatorul a ales explicit FD1, mutarea Documentelor în Familie. Nu redesena alte pagini în acest pas.

| Pagină | Adaptare | Stare |
| --- | --- | --- |
| Acasă — H2 | Acces rapid la module reale; amintire cu fotografie și dată; note compacte; documente/categorii existente; evenimente și remindere reale; calendar în coloana laterală desktop. | Implementat, probe locale mai jos; feedback pozitiv primit. |
| Bucătărie — KH1, pas curent | Rețete cu fotografii în rând derulabil pe mobil, căutare în antet, listă completă cu filtre și rânduri compacte pentru mesele zilei; versiuni și voturi existente. | Implementat, build/probe vizuale locale trecute; așteaptă feedback. Vezi [fișa Bucătărie](kitchen.md#kh1--layout-bucătărie-22-octombrie-2026). |
| Amintiri | Fotografii și liste compacte, continuitate cu noul card Acasă; momente/note și cronologia rămân funcționale. | Planificat după Bucătărie. |
| Familia mea / Documente — FD1 | Membri/Documente în Familie, dosar comun și pe persoană; numele membrului deschide documentele autorizate, administrarea rămâne separată. Meniul principal are cinci secțiuni. | Pas curent, cerut explicit; vezi [Familie](families.md#fd1--documente-în-familie-22-octombrie-2026). |
| Calendar | Aceeași densitate, borduri și iconuri, cu păstrarea politicilor și controalelor existente. | Pagină ulterioară, neîncepută în FD1. |
| Animale / Mașini | Structura profilului, următoarelor activități și istoricului din imagine se folosește când modulele AN/V există. | Module de produs planificate; fără pagini sau numere fictive. |

### H2 — Contractul Acasă

- Endpointul Home păstrează documentele, calendarul și reminderele H1 și adaugă `memories`, maximum trei rezumate active, autorizate de `Memory\Application\RecentMoments` prin `MomentAccess`. Un singur query cu join media; fără granturi, ID media, cale de stocare ori text integral. Data rămâne calendaristică, extrasul are maximum 200 de caractere plus elipsă.
- Cardul principal preferă fotografia gata din cele trei amintiri autorizate; în lipsa ei arată textul existent. Fotografia folosește ruta privată canonică și `PrivatePhoto`; eșecul încărcării are fallback. Notele rămase sunt rânduri compacte, fără carusel sau indicatori fictivi.
- App coordonează accesul rapid și deschiderea amintirii prin ID; Home nu importă ecranul Memory. Amintiri citește detaliul canonic complet înainte de deschidere, deoarece rezumatul nu conține people/grants/version. Schimbarea familiei/contului golește ținta; modulele se remontează cu cheia existentă. Eroarea Home golește rezumatele încărcate.
- Categorii, documente recente, creare eveniment, remindere și calendar rămân accesibile. Nu sunt implementate statistici ale modulelor, module AN/V, recomandări sau căutare globală. Shellul și ordinea navigației rămân cele existente.

### Verificare H2 — 22 octombrie 2026

- Build TypeScript/Vite, Prettier, Pint și lint PHP trecute. Fără dependențe noi; nu s-au creat teste și nu s-au rulat suite automate, conform priorității utilizatorului. `RecentMoments` nu schimbă sursele sau indexul AM3.
- Browser desktop: fotografia privată se încarcă din ruta canonică; cardul deschide detaliul complet în Amintiri. Desktop și previzualizare 390 px: acces rapid, card foto și notă, categorii/documente, calendar și stările goale; pe mobil s-a deschis formularul Calendar din Acasă, fără a salva un eveniment nou.
- API local cu sesiuni demo independente: Ana vede două amintiri (una cu fotografie); Mihai vede numai nota de familie, fără amintirea privată. Familia Ionescu nu primește amintirile Popescu; requestul Mihai către Ionescu dă 404. Rezumatele expun exclusiv `id,title,kind,excerpt,occurred_on,visibility,photo_status`.
- Popescu → Ionescu → Popescu verificat și în previzualizarea mobilă: Acasă rămâne activă, Ionescu arată starea goală pentru amintiri și propriul document, apoi revin datele Popescu. Capturi ignorate: `.runtime/h2-home-desktop.png`, `.runtime/h2-home-mobile.png`. Nu s-au creat evenimente sau schimbat amintiri pentru noul layout.
- Rămân neexercitate: revocare/grant retras în timp ce cardul Home este deschis, erori de rețea provocate, dispozitive fizice și limite cu mai mult de trei amintiri. Refolosirea politicii/media existente nu este prezentată ca repetarea întregii acceptări AM2/AM3. Utilizatorul a dat feedback vizual pozitiv și a ales Bucătărie ca următor layout.

## Referință secundară pe ecrane — 21 octombrie 2026

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

Stare: **implementat și verificat local**, 21 octombrie 2026. Utilizatorul a confirmat Acasă prima în meniu și ca pagină după autentificare. După K1, ordinea confirmată era Acasă → Familia mea → Documente → Bucătărie → Calendar. La dezvoltarea AM1 din 22 octombrie, Amintiri este adăugat după Bucătărie, înainte de Calendar, identic pe desktop și mobil; această extindere are build reușit, fără verificare vizuală nouă. Cont rămâne accesibil separat și în bara mobilă. Datele provin din baza aplicației, inclusiv datele demo existente; H1 nu a adăugat exemple permanente pentru a umple dashboardul.

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
