# Căutare și drepturi asupra datelor

Stare: numai căutarea locală în Documente există. Căutarea globală, activarea modulelor, exportul și ștergerea controlată sunt planificate pentru M10. Regulile de lifecycle se definesc pentru fiecare model încă de la introducerea lui.

## Pași

- [ ] **CD1 — Căutare autorizată.** Fiecare modul declară prin provider ce poate căuta actorul. Combină rezultate deja autorizate, cu paginare și câmpuri controlate de modul; nu construi un index universal cu date private. Verifică revocarea și filtrele înaintea returnării titlurilor, fragmentelor sau numărului de rezultate.
- [ ] **CD2 — Activarea modulelor.** Folosește registrul explicit din P1 pentru UI și scrieri. Dezactivarea păstrează datele, oprește alertele noi și este reverificată de joburi; exportul și cleanupul pot folosi providerii fără a reactiva ecranele. Probează adăugarea unui provider fără modificarea internals ale celorlalte module.
- [ ] **CD3 — Export.** Definește solicitantul, scopul și resursele permise, apoi jobul, stocarea privată, expirarea și descărcarea. O revocare invalidează exportul pregătit; dreptul de descărcare este verificat din nou. Membrul exclus are un flux separat și verificat de cerere, fără redeschiderea accesului normal la familie.
- [ ] **CD4 — Ștergere și retenție.** Stabilește politicile înaintea execuției. Separă arhivarea, ștergerea resursei și ștergerea contului; curăță proiecții, remindere, exporturi și bytes neutilizați. Păstrează tombstones minimale pentru a nu reintroduce date șterse după restaurare; testează o restaurare cu replay.

## Acceptare

- Accesul privat nu se scurge prin căutare, timeline, export sau notificări, inclusiv după revocare cu sesiune deschisă.
- Dezactivarea modulului nu șterge date și nu lasă joburile vechi să trimită alerte.
- Exportul nu este public, are expirare și nu se poate descărca după pierderea dreptului relevant.
- Ștergerea contului nu șterge arbitrar datele altui membru și nu transferă date private proprietarului familiei.
- Restaurarea nu reactivează datele acoperite de ștergeri finalizate. Termenele efective de retenție sunt consemnate și aplicate, nu deduse din exemple.

## Dependențe și surse

M10 folosește contractele tuturor modulelor și infrastructura Media/Eventing. Câmpurile căutabile și regulile concrete de acces rămân la modulul proprietar.

Consultă numai operațiile `Search_`, `Settings_`, `Privacy_` și scenariile aferente din pachetul v1.2. Valorile de retenție din blueprint sunt propuneri de decis, nu politici actuale adoptate.

Nu sunt incluse OCR, căutare în conținutul documentelor scanate sau embeddings. Un dashboard Home poate consuma ulterior aceleași contracte, cu funcții și date reale stabilite separat.
