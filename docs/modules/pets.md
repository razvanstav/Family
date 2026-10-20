# Animale

Stare: planificat, fără implementare de produs. Etapa M08 cere Documente D4, subiecți, media, Calendar și notificări.

## Scop

Profilul animalului, greutate și înregistrări de îngrijire/vaccinare/vizită, cost simplu, document justificativ și următoarea scadență. Modulul deține datele animalului; registrul Subjects păstrează numai identitatea minimă.

## Pași

- [ ] **AN1 — Profil.** Definește modelul, politica de acces, contractele și asocierea cu subiectul. Livrează creare, listă, detaliu, editare și arhivare.
- [ ] **AN2 — Greutate și îngrijire.** Livrează înregistrări cu unități, date, cost și validări explicite. Păstrează istoricul și tratează corecțiile fără suprascriere tacită.
- [ ] **AN3 — Document și scadență.** Asociază prin contractul Documents, cu autorizare separată a documentului. Publică scadența îngrijirii către Calendar; editarea și anularea pornesc de la înregistrarea canonică.
- [ ] **AN4 — Flux complet.** Verifică vaccinare → document → Calendar → email, apoi editare, anulare, arhivare și revocare. Include membru fără acces la document, chiar dacă vede înregistrarea de îngrijire.

## Acceptare

- Legarea documentului nu îl face automat vizibil tuturor celor care văd animalul. UI nu afișează titlul sau bytes dacă politica documentului le refuză.
- Înregistrarea de îngrijire deține următoarea dată; Calendar nu păstrează o a doua valoare editabilă.
- Arhivarea animalului/înregistrării oprește alertele, păstrând istoricul conform regulilor stabilite.
- Greutatea, unitățile, datele și costurile invalide sunt respinse; două familii nu pot lega resurse între ele.

## Surse și căi viitoare

Consultă operațiile `Pets_` și `Pet_`, contractele Documents/Subjects și scenariul vaccinare-document-reminder din pachetul v1.2.

Implementarea va sta în `backend/app/Modules/Pets/` și `frontend/src/modules/pets/`. Nu se adaugă dependențe către tabelele interne ale Documents sau Calendar.

Diagnosticul, dozajele, integrările cu clinici și dosarele medicale umane sunt în afara MVP.
