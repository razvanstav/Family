# Interfață comună

Stare: direcția vizuală și componentele comune există. Această fișă se aplică fluxului UI modificat în fiecare etapă; nu introduce o etapă separată de redesenare generală.

## Fișiere relevante

- Tokenuri, tipografie și stiluri comune: `frontend/src/styles.css`.
- Shell, context de familie și autentificare: `frontend/src/app/App.tsx`.
- Dialog accesibil: `frontend/src/shared/Modal.tsx`.
- Client API: `frontend/src/shared/api.ts`; se aliniază rutelor efectiv implementate.
- Referință: `artifacts/design-reference-warm.png`; structura desktop poate folosi `artifacts/design-reference.png`.

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
