# Amintiri

Stare: planificat, fără implementare de produs. Etapa M06 urmează după Bucătărie și evenimentele durabile.

## Scop

Momente și note private sau partajate, fotografii, asocieri cu persoane/subiecți și timeline autorizat. Momentele sunt surse canonice aici; activitățile altor module apar ca proiecții, fără copierea proprietății asupra datelor lor.

## Pași

- [ ] **AM1 — Momente și note.** Stabilește contractul, accesul și modelul; livrează creare, listă, detaliu și editare cu controlul versiunii. Asocierile cu persoane/subiecți sunt limitate la familia și accesul actorului.
- [ ] **AM2 — Fotografii și partajare.** Folosește pipeline-ul Media, cu fișiere curate și accesul momentului. Verifică schimbarea vizibilității, revocarea, arhivarea și rollbackul după încărcare.
- [ ] **AM3 — Timeline.** Integrează momentele și preparările din Bucătărie prin providere/outbox, cu paginare și proiecții reconstruibile. Verifică mesaje repetate/vechi, ștergerea sau arhivarea sursei și autorizarea actuală la citire.

## Acceptare

- Un moment și o preparare apar fiecare o singură dată în timeline și trimit la sursa permisă.
- Revocarea ascunde atât cardul, cât și fotografia/bytes; un titlu privat nu rămâne într-o proiecție accesibilă.
- Reconstruirea timeline-ului nu modifică sursele și nu reactivează elemente arhivate.
- Paginarea are ordine stabilă; afișarea datei respectă fusul orar stabilit fără schimbarea momentului canonic.

## Surse și căi viitoare

Consultă operațiile `Memory_` și `Moment_`, providerul de timeline și scenariile de revocare/replay din pachetul `Memoria_Familiei_Review_v1.2`.

Implementarea va sta în `backend/app/Modules/Memory/` și `frontend/src/modules/memory/`. Adaptoarele pentru timeline consumă contracte publice, fără interogarea directă a tabelelor Kitchen/Pets/Vehicles.

Albumele sofisticate, montajul video, embeddings și poveștile generate AI rămân în afara MVP.
