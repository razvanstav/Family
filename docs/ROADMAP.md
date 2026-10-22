# Planul de dezvoltare

Pornim de la aplicația locală existentă și livrăm un singur pas verificabil odată. Prima sesiune de implementare este 22 octombrie 2026, la cererea utilizatorului. Nu sunt stabilite termene de livrare pentru celelalte etape.

## Ordinea etapelor

**Prioritatea nouă — 23 octombrie 2026:** utilizatorul a cerut corectarea subcategoriilor din Membri, apoi [Auto V1a](modules/vehicles.md#v1a--ecranul-auto-23-octombrie-2026) după telefonul Mașini din mock-up și punerea schimbărilor pe Git. UT2 este o corecție punctuală; Auto este singurul layout nou. V1a precede restul M08/M09 cu profil, scadențe manuale, istoric și documente asociate prin politica existentă; Calendar/reminder/email și restul V1–V4 nu sunt declarate implementate. Auto este după Calendar, în aceeași poziție pe desktop și mobil. Cererea nouă autorizează commit și push pe ramura curentă; nu implică deploy.

**Prioritatea nouă — 22 octombrie 2026:** [Familie FP1 — profiluri personale](modules/families.md#fp1--profilul-membrului-22-octombrie-2026), cerut explicit după CH1: fotografie, sănătate, școală/cultură, activități, documente și reguli pe vârstă/părinți. „Public” înseamnă familia; privatele adolescentului nu sunt accesibile părinților. Numai acest modul, cu verificări proporționale ale noilor permisiuni. Profilurile fără cont rămân F4/P1.

**Etapa anterioară — 22 octombrie 2026:** după MH1, utilizatorul a ales [Calendar CH1 — layout mobil](modules/calendar.md#ch1--calendar-mobil-22-octombrie-2026), după imaginea Calendar/Agendă furnizată. Numai această pagină, apoi feedback împreună. Fără suite automate ori verificări extinse; build și browser. Meniul rămâne Acasă → Familia mea → Bucătărie → Amintiri → Calendar, identic desktop/mobil, cu Documente în Familie și Cont separat. Acasă H2, Bucătărie KH1, Familie FD1 și Amintiri MH1 rămân existente; planificatorul este numai schițat. D4a/C2, AM1–AM3 și O2a își păstrează limitele documentate; nu se reiau implicit. Commituri locale, fără push până la acord explicit.

**Prioritate actualizată la 21 octombrie 2026, la cererea utilizatorului:** după A1, livrăm Calendar C1 pentru testarea locală, apoi C3 pentru remindere in-app ale evenimentelor manuale și dashboardul cu date reale. Aceste felii preced restul M03–M06; folosesc numai dependențele necesare. A2–A3 și emailul extern rămân deschise pentru etapa de utilizare din afara mediului demo. C2/D4 și integrarea surselor se fac separat; livrarea C1 nu închide integral M07.

Rezultat actualizat la 22 octombrie 2026: C1, C3 in-app, [Acasă H1](modules/interface.md#acasa-h1), [Bucătărie K1, K2, K3a și K4a–K4b](modules/kitchen.md) sunt implementate și verificate local. K2 păstrează mesele pe versiunea folosită, iar K4a livrează accesul selectiv și arhivarea/restaurarea privată. La cererea utilizatorului, K4b include [P3 strict necesar](modules/platform.md#p3--contract-și-execuție): outbox DB, retry/replay, confirmări per handler și index Kitchen reconstruibil, cu API autorizat prin sursele canonice. Ecranul timeline rămâne AM3; K3b și restul M04 rămân deschise. Odată cu AM1, meniul este Acasă → Familia mea → Documente → Bucătărie → Amintiri → Calendar; Acasă rămâne prima și implicită.

| Etapă | Modul și pașii | Rezultat înainte de continuare | Dependențe |
| --- | --- | --- | --- |
| M00 | Organizare | Index, fișe și punct de reluare salvate | Documentația existentă |
| M01 | [Familie F1–F3](modules/families.md) | Membri, invitații, roluri și revocare verificate, cu defectele blocante corectate | Baza locală existentă |
| M02 | [Documente D1–D3](modules/documents.md) | Acces privat și partajat, upload, carantină și descărcare verificate | M01, media existentă |
| M03 | [Autentificare A1–A3](modules/identity.md), apoi [Familie F4](modules/families.md) | Recuperare parolă, email și reguli pentru familie/profil clarificate și implementate în pași separați | M01–M02; furnizor email pentru livrare reală |
| M04 | [Platformă P1–P4](modules/platform.md), [Documente D4](modules/documents.md), [Operare O1](modules/operations.md) | Contracte necesare, subiecți, media/worker, evenimente durabile și CI pregătite pentru noile module | M02–M03; stocare privată configurată înainte de staging |
| M05 | [Bucătărie K1–K4](modules/kitchen.md) | Rețete versionate, mese, voturi personale și comentarii | M04 |
| M06 | [Amintiri AM1–AM3](modules/memory.md) | Momente, fotografii și timeline autorizat, inclusiv preparările din Bucătărie | M04–M05 |
| M07 | [Calendar C1–C4](modules/calendar.md) | Evenimente și scadențe cu remindere in-app/email, editare și anulare consecvente | M03–M04, D4 |
| M08 | [Animale AN1–AN4](modules/pets.md) | Profil, greutate și îngrijire cu documente și scadențe | M02, M04, M07 |
| M09 | [Vehicule V1–V4](modules/vehicles.md) | Istoric și obligații cu documente și un singur reminder pentru aceeași scadență | M02, M04, M07 |
| M10 | [Căutare și date CD1–CD4](modules/data.md) | Căutare autorizată, activare module, export și ștergere controlată | M05–M09; deciziile de retenție stabilite |
| M11 | [Operare O2–O5](modules/operations.md) | Staging, restaurare demonstrată, verificări integrate și beta cu toate cele șase module din blueprint | M01–M10 |
| M12 | [Mobil MB1–MB3](modules/mobile.md) | Evaluare pe dispozitive, adaptoare și livrare separată după stabilizarea web | M11; dispozitive și conturi de distribuție |

[Interfața comună](modules/interface.md) se aplică în fiecare etapă. M00 organizează lucrul; acceptarea funcțională începe cu M01. Dependentele se verifică la începutul fiecărei etape, iar orice schimbare de ordine se salvează aici și în STATE.

## Cum executăm un pas

1. Citește STATE și pasul activ; verifică sursele și instrucțiunile locale aplicabile. Spune într-o propoziție ce rezultat urmărești.
2. Fixează un rezultat mic: un flux, o regulă sau un defect. La un modul nou, primul pas stabilește modelul, accesul și contractul public înaintea ecranului complet.
3. Implementează numai acel rezultat, cu migrații și tratarea erorilor necesare. Schimbările care traversează module folosesc contractele lor publice.
4. Rulează verificările relevante. Corectează eșecurile înainte de a declara pasul închis; nu ascunde un test eșuat și nu adăuga teste care doar repetă implementarea.
5. Actualizează fișa și STATE cu rezultatul, comanda/rezultatul verificării, limita rămasă și următoarea acțiune. La finalul unei sesiuni, utilizatorul primește rezultatul concret.

## Când considerăm un pas închis

- Comportamentul cerut funcționează în cazul normal și în erorile relevante; nu există defect blocant cunoscut în acel pas.
- O modificare de acces este verificată cu două familii, roluri diferite și sesiune existentă după revocare, pe suprafețele pe care le afectează.
- Scrierile concurente nu suprascriu tacit versiuni noi; validarea, rollbackul și retry sunt verificate când fluxul le folosește.
- Pentru date noi există proprietar clar, limite de familie/FK, autorizare și reguli de arhivare/ștergere. Migrațiile nu distrug date existente fără o decizie explicită.
- Un flux UI modificat are etichete accesibile, focus, stări de încărcare/gol/eroare, controale tactile și verificare pe mobil și desktop; fără texte decorative redundante.
- Testele relevante și buildul necesar au trecut. Se consemnează separat verificarea manuală, automată și cea neefectuată; o captură nu dovedește securitatea.

Acceptarea înseamnă criterii demonstrate, nu o promisiune că nu poate exista niciun defect. Un pas nevalidat rămâne deschis și nu primește starea „verificat”.

## Reguli care traversează modulele

- Proprietarul familiei nu primește acces implicit la datele private ale altui adult. Un link, un subiect sau o asociere nu acordă acces.
- Modulul sursă deține informația canonică; Calendar și timeline folosesc proiecții reconstruibile. Scadența unei obligații se editează la sursă, fără o copie concurentă în Documente.
- Pets și Vehicles folosesc contractele Documents, nu tabelele sau paginile lui interne. Media are un singur proprietar de resursă și reverifică accesul la bytes.
- Revocarea și dezactivarea unui modul se verifică și la executarea joburilor, la notificări și la descărcarea exporturilor. Copiile deja descărcate nu pot fi retrase.
- Modulele frontend nu importă ecranele altui modul; UI și clientul API comune rămân în zona shared. Extragem clase și porturi numai pentru limite reale.
- Nu extindem implicit MVP-ul cu OCR/AI, recomandări clinice, sincronizare offline, integrări cu registre auto sau calendare externe. Home poate fi ulterior un consumator al contractelor, după ce există date și acțiuni reale.

## Decizii care se iau când devin necesare

| Înainte de | Decizie | Până atunci |
| --- | --- | --- |
| A2–A3 | Furnizor email, expeditor, domeniu și link HTTPS; inbox local pentru dezvoltare | Nu pretindem că un link localhost funcționează pe alt dispozitiv |
| F4 | Plecarea/reactivarea membrilor, transferul proprietății și profilurile fără cont | Regulile restrictive actuale rămân active |
| P2 și D4 | Stocare privată, limite de fișiere, pagini și proprietatea scadenței | Limitele locale rămân 10 MB/fișier și 100 MB/familie; fără schimbare implicită la cotele din blueprint |
| C2–C3 | Fus orar, canale, destinatari și comportamentul la livrare incertă | Nu promitem livrare email exact o singură dată |
| CD3–CD4 | Scopul exportului/ștergerii, retenții și tratarea datelor altor membri | Valorile din blueprint rămân propuneri, nu politici adoptate |
| O2–O4 | Hosting, domeniu, costuri, ținte de recuperare și mediu de beta | Lucrăm local; fără achiziții sau publicare implicită |
| MB1 | Dispozitive reale și scopul livrării native | Verificarea în browser nu este prezentată drept test pe iPhone/Android |

Aceste decizii nu blochează M01. Se pregătesc opțiuni concrete când ajungem la pasul respectiv.

## Sursa planului

Starea inițială vine din sursele curente și istoricul Git până la `e9ef880`. Scopul viitor vine din blueprintul v1.2: modulele înguste, ownership, scenariile de acceptare și livrarea progresivă. Estimările de timp/buget și toate rutele viitoare ale blueprintului nu sunt tratate drept funcții livrate sau termene asumate.
