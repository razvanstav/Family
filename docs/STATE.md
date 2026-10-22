# Punct de reluare

Actualizat: 23 octombrie 2026, Europe/Bucharest.

- **Modul/pas:** Familie, **F4a/FD3 — copii fără cont și dosare pe categorii**, implementat. [Contract și verificări](modules/families.md#f4a-și-fd3--copii-fără-cont-și-dosare-pe-categorii-23-octombrie-2026).
- **Rezultat:** Familie → Documente are numai Casă/Amintiri; profil → Documente numai Personale/Sănătate. Adaugă membru → Copil sub 14 ani cere nume, data nașterii și părinți desemnați, fără email/user/invitație. Părinții pot adăuga însemnări, fotografii și documente în profilul copilului; fișierul rămâne deținut de autor, cu subiectul separat.
- **Decizii confirmate:** Casă se partajează adulților, inclusiv celor cu acces limitat; membrii înregistrați ca minori sunt excluși din grup, vârsta necunoscută păstrează clasificarea adult existentă. Amintiri este pentru familie. Utilizatorul a autorizat explicit aceeași regulă și documentelor Casă/Amintiri deja private. Personale/Sănătate păstrează accesul actual și pornesc private. Proprietarul fișierului păstrează accesul la propriul document.
- **Migrație:** `2026_10_22_212517_add_accountless_children_and_adult_document_access.php` aplicată aditiv pe `memoria_test` și `memoria`: identitate fără cont, acces adults, subiectul documentului, backfill comun cu audit/versiuni. Fără seed/reset al aplicației, modificarea bytes/proprietarilor sau copii/demo-uri noi.
- **Verificat:** TypeScript/Vite build, Prettier, Pint, `git diff --check`; serial pe `memoria_test`: 9 teste profil/99 aserțiuni + 6 documente/65 + 1 Home/16 = **16 teste / 180 aserțiuni**, trecute. Prima rulare concurentă pe aceeași bază de teste a fost invalidă; reluarea serială a trecut. API local după migrare: 4 documente Popescu, categorii/acces/subiecți conforme, aceiași 2 membri. Fără browser sau probe vizuale.
- **Limite/livrare:** acceptarea vizuală rămâne pentru feedback. Legarea ulterioară a copilului la cont și restul F4 rămân separate; nu există cont automat la 14 ani. Fără publicare în producție/push; nu reîncerca push implicit.
- **Context păstrat:** UT1 (cadru stabil al filelor) și KH2 (carduri Mese ca Rețete) sunt existente. Navigație: Acasă → Familia mea → Bucătărie → Amintiri → Calendar, Cont separat. Planificatorul rămâne schiță. Ana are cele trei însemnări demo private din FP1-demo; nu le duplica și nu începe alt layout automat.

## Următoarea acțiune

Preia feedbackul pentru cele două dosare și formularul Copil sub 14 ani. La reluare citește acest rezumat, numai secțiunea F4a/FD3 din fișa Familie și codul necesar cererii. Nu reciti tot planul sau toate modulele. După fiecare pas actualizează aici rezultatul, verificările, limitele și următoarea acțiune.

## Mediu local

Windows, PHP 8.4 în `C:\Users\razva\php84\php.exe`, Laravel 13, dependențe existente. PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`; teste exclusiv pe `memoria_test`, executate serial. CLI DB/media: `-d extension=pdo_pgsql -d extension=gd`. Migrațiile FP1/F4a-FD3 sunt aplicate local; alte medii cer migrația nouă înainte de build. Privilegiile de tabel existente acoperă coloanele noi. Buildul `backend/public/app/` este ignorat. Pornire/oprire și replay în [SETUP](../ops/SETUP.md); nu reseta/seedui baza aplicației. Secretele/datele locale nu se afișează sau comit.
