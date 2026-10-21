# Interfață comună

Stare: direcția vizuală și componentele comune există. Această fișă se aplică fluxului UI modificat în fiecare etapă; nu introduce o etapă separată de redesenare generală.

## Fișiere relevante

- Tokenuri, tipografie și stiluri comune: `frontend/src/styles.css`.
- Shell, context de familie și autentificare: `frontend/src/app/App.tsx`.
- Dialog accesibil: `frontend/src/shared/Modal.tsx`.
- Client API: `frontend/src/shared/api.ts`; se aliniază rutelor efectiv implementate.
- Referință principală de structură: [mock-up ales](../../artifacts/design-reference-layout.jpeg). Paletă și atmosferă: `artifacts/design-reference-warm.png`; referință secundară: `artifacts/design-reference.png`.

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
- Dashboard Acasă planificat: zonă principală de aproximativ două treimi pentru conținutul familiei; coloană de aproximativ o treime pentru calendar compact, evenimente apropiate și remindere. Grila se restrânge la o singură coloană pe ecrane înguste; listele extinse au pagini proprii. Nu umple coloana cu statistici sau elemente fictive.
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

## Limite de arhitectură

Modulele consumă shared UI și API; nu importă ecranele altui modul. Când `App.tsx` sau un modul devine greu de întreținut, extrage componente/hooks după responsabilități concrete, păstrând comportamentul și contractele. Nu face refactorizare largă doar pentru a muta fișiere.

Nu adăuga statistici, meniuri, grafice sau dashboarduri fără date și acțiuni implementate. Nu porni cache offline pentru conținut privat. Testarea vizuală în browser se raportează distinct de testarea pe dispozitive reale.
