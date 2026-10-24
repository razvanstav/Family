# Documente

## DC1 — Fișier opțional și date în Calendar, 24 octombrie 2026

**Implementat și verificat local**, la cererea explicită a utilizatorului; acceptare vizuală deschisă. Un document poate rămâne doar cu nume/categorie și expirare opțională; fișierul se poate atașa ulterior, fără termen sau obligație. `asset_id` devine nullable, fără rând media fictiv, consum de cotă ori scanare pentru metadate. Listele, căutarea, detaliul și asocierile păstrează documentele fără fișier; MIME/starea sunt null și UI arată „Fără fișier”. Accesul pe categorii și subiect rămâne existent.

- `POST /api/v1/families/{family}/documents` acceptă `file` opțional. `POST /documents/{document}/file`, numai proprietarul activ fără rol limitat, primește fișier și `If-Match`; 428 fără versiune, 412 la conflict, 409 dacă există deja fișier. Aceeași scanare/cotă/validare și același ID/document/acces; crește versiunea. Fără fișier, content/scan returnează 404. Nu s-a adăugat înlocuirea sau eliminarea unui fișier deja atașat.
- Migrația `2026_10_24_144808_allow_documents_without_files.php` elimină numai NOT NULL; FK/UNIQUE rămân. Migrația inversă refuză revenirea dacă există documente fără fișier; nu creează fișiere fictive și nu șterge documente pentru rollback.
- **Verificat:** 6 teste noi/86 aserțiuni pentru creare fără media, listă/detaliu/contracte/granturi, atașare, versiuni, roluri, izolare și stări scanner. Rulare integrată: **45 teste backend/486 aserțiuni**, serial numai memoria_test, fixture-uri proprii, fără TRUNCATE/reset sau SMTP real; **28 teste frontend** și build final `CocPEpk7/BllcGDC3`, Pint/Prettier/gărzi/diff trecute. Migrația aditivă este aplicată în ambele baze, cu amprentele documentelor/media și datelor existente păstrate. Calendar nu necesită migrație nouă. Suplimentar, cele 29 teste existente Sarcini/Auto/recurențe au trecut cu 422 aserțiuni după adaptarea istoricului Calendar și izolarea fixture-urilor; 74 teste backend distincte/908 aserțiuni cumulat.
- **Browser 8001/memoria_test:** cont/familie DC1 izolate, document numai cu nume salvat și redeschis, atașare ulterioară de PNG cu scanare reală ClamAV și aceeași identitate/acces, alt document fără fișier cu expirare deschis din Calendar fără preferințe salvate. Formular desktop 1280×900, mobil 390×844 și 320×800 fără overflow, controale de minimum 44 px, focus picker/înapoi și salvare fără fișier pe mobil verificate. Capturi `.runtime/dc1-document-form-{desktop,mobile,320}.png`. Un singur formular ajustat, fără redesignul celorlalte corpuri de modul. Fără telefon fizic sau acceptare vizuală declarată.
- Integrarea completă a datelor utilizatorului este în [Calendar DC1](calendar.md#dc1--toate-datele-introduse-de-utilizator-24-octombrie-2026), independentă de activarea canalelor pentru remindere.
- Încheiere: datele și fișierul sintetic DC1 sunt curățate, serverul 8001 oprit și viewportul resetat. Formularul Adaugă document a fost verificat fără salvări de probă în baza aplicației; ultima navigare a utilizatorului pe 8000, la Sarcini, a fost păstrată. Fără commit/push/deploy sau emailuri reale.

**NS1, 24 octombrie 2026:** expirarea explicită poate genera remindere private per destinatar autorizat, activate prin Contul meu → Setări → Scadențe, cu 7 zile înainte la 09:00 România. Accesul canonic este reverificat; proiecția nu acordă acces nou și nu copiază date din Auto/Animale. Contracte/verificări și navigarea din Calendar în [NS1](calendar.md#ns1--setări-personale-și-emailuri-automate-24-octombrie-2026).

Stare: **D1–D3 verificate la 21 octombrie 2026; M02 închis local.** D4 extinde modulul în M04, după contractele necesare din Platformă.

**22 octombrie 2026 — D4a implementat, acceptare parțială:** expirarea independentă, opțională, a documentului. Utilizatorul a schimbat prioritatea către redesenarea paginilor după noua referință în timpul verificării. Pasul nu închide întregul D4.

## Ce există

- **P1a.3, 24 octombrie 2026:** `Contracts/DocumentLinks` oferă metadate autorizate și reverificarea accesului pentru Auto; `DocumentSummaries` oferă rezumatele Home. `DocumentAccess` implementează ambele, fără expunerea rândului intern la asociere. Curățarea granturilor la `MembershipRevoked` aparține acum Documentelor și rămâne atomică; testele noi acoperă readmiterea și rollbackul. [Verificări P1a](platform.md#p1a3--contracte-backend-și-ownership-verificat-la-24-octombrie-2026).

Listă, căutare locală, categorii, filtru de acces, detaliu, upload PDF/JPEG/PNG/WebP, carantină/retry și descărcare. Personale/Sănătate pornesc private; proprietarul poate selecta privat/familie/adulți/membri aleși, cu `If-Match`. Casă/Amintiri au accesul comun stabilit de categorie.

**FD3 — 23 octombrie 2026:** Familia mea → Documente are numai Casă/Amintiri; profilul membrului numai Personale/Sănătate, filtrate după `subject_membership_id` în lista autorizată. Categoria Casă se partajează automat grupului dinamic `adults`, inclusiv adulților cu acces limitat și cu excluderea minorilor înregistrați; Amintiri se partajează familiei. Utilizatorul a autorizat explicit aplicarea regulii și documentelor comune existente: migrație cu audit/versiuni, fără schimbarea proprietarilor sau bytes. Personale/Sănătate existente păstrează accesul și sunt asociate profilului proprietarului. Părinții desemnați pot încărca în profilul copilului fără cont, păstrând proprietatea fișierului; privatele rămân ale autorului. Nu există selector comun/personal/toate. Contractele și probele sunt în [Familie F4a/FD3](families.md#f4a-și-fd3--copii-fără-cont-și-dosare-pe-categorii-23-octombrie-2026).

## Fișiere relevante

- UI: `frontend/src/modules/documents/Documents.tsx`, `documentFilters.ts`, `documents.css`.
- API și autorizare: `backend/app/Modules/Documents/Http/DocumentController.php`, `Application/DocumentAccess.php`.
- Teste: `backend/tests/Feature/DocumentSecurityTest.php`.
- Bytes, scanner și cotă: [Platformă](platform.md). Aspect și dialoguri: [Interfață](interface.md).

## Pași

- [x] **D1 — Listă și detaliu.** Verifică cele două familii, căutarea, filtrele, titlurile lungi, stările goale/eroare și rolurile. Titlul complet rămâne accesibil în detaliu și cititorului de ecran; cardurile mobile rămân compacte.
- [x] **D2 — Acces și descărcare.** Verifică private/family/selected, grantul retras, membrul revocat, accesul direct prin ID și versiunea învechită. Acoperă metadata și bytes; proprietarul familiei nu poate ocoli intimitatea altui adult.
- [x] **D3 — Upload și verificare.** Verifică MIME falsificat, fișier prea mare, cotă, imagine recodată, scanner indisponibil, retry și fișier infectat. Rulează testele relevante și integrarea ClamAV reală înainte de închiderea pasului; nu prezenta dublul de test drept scanner real.
- [ ] **D4 — Contract pentru celelalte module.** După P1–P2, stabilește și implementează incremental paginile/atașamentele, subiecții și expirarea independentă a documentului. Expune contractele de asociere și acces pentru Pets/Vehicles; nu permite citirea tabelelor interne din alte module. Verifică modelul de media cu un singur proprietar și migrarea documentelor existente.

## Acceptare

- Fiecare descărcare verifică membrul activ, politica actuală și fișierul curat; fără URL public sau cache privat persistent.
- `If-Match` învechit nu suprascrie accesul nou; granturi și ID-uri străine nu lărgesc vizibilitatea.
- Un fișier respins este eliminat; scannerul indisponibil păstrează carantina fără descărcare. Un rollback nu lasă fișier nou utilizabil.
- Limitele actuale rămân 10 MB/fișier, 100 MB/familie și 16 megapixeli până la o schimbare explicită, verificată și comunicată în formular.

## Ajustări UI — 21 octombrie 2026

- La cererea utilizatorului: cele patru categorii au fotografii calde de acasă și etichete albe compacte. Imaginile WebP sunt în `frontend/src/assets/categories/`; sursa și prompturile Imagegen sunt în [manifest](../../artifacts/category-images.json).
- Dimensiunea fișierului nu se mai afișează în card, detaliu sau selecția de upload. Limita reală de 10 MB și validarea rămân.
- Build TypeScript/Vite trecut. Browser local la 1366 și 360 px: patru fotografii încărcate, patru coloane desktop/două mobil, fără overflow, carduri mobile de 156 px. Filtrul Casă arată un document; „Vezi toate” readuce cele patru documente. Captura desktop: `.runtime/categories-desktop.jpg` (ignorată de Git).
- Corecția uploadului când se schimbă rolul și verificarea accesului după revocare sunt documentate la [F2](families.md#verificare-f2--21-octombrie-2026). Aceste ajustări nu închid acceptarea integrală D1–D3.

## Verificare D1 — 21 octombrie 2026

- Corecții: căutarea ignoră spațiile de la capete; eroarea listei nu mai afișează simultan „Niciun document” sau un număr fals de rezultate; eroarea de conexiune din detaliu are mesaj distinct și „Reîncearcă”. Starea accesibilă „Vezi toate” ține cont și de căutare/filtrul de acces.
- Browser local, Ana: patru documente în Familia Popescu, unul în Familia Ionescu. Schimbarea familiei resetează filtrele. Detaliul documentului Elenei arată proprietarul și nu oferă administrarea accesului. Restricțiile rolului limitat și efectul revocării se preiau din F2, fără repetare.
- Filtre Doar eu/Familia mea/Persoane alese, combinații cu căutare/categorie, lipsa rezultatelor și resetare verificate. Trei teste noi acoperă spațiile și majusculele românești, intersecția filtrelor și resetarea/listele goale.
- Familie temporară fără documente: stare goală reală și acțiune de adăugare. Două înregistrări fictive au verificat „În verificare”/„Respins”, descărcarea dezactivată în detaliu și titlul de 160 de caractere, inclusiv segment fără spații. La 360 × 800, cardurile au 156 px, numele accesibil și detaliul păstrează titlul complet, fără overflow orizontal. Familia și înregistrările au fost eliminate după verificare; nu s-a executat scannerul.
- Întreruperea controlată a serverului local a reprodus erorile înainte de corecție. După corecție, detaliul și lista se recuperează prin „Reîncearcă”; lista readuce cele patru documente și elimină eroarea. Serverul a fost repornit și a rămas disponibil.
- Verificări: **10 teste frontend trecute, dintre care 3 noi pentru D1**, și build TypeScript/Vite reușit. Backendul nu a fost modificat; suitele F1–F3 nu au fost rerulate. Capturi ignorate de Git: `.runtime/d1-search.jpg`, `.runtime/d1-list-error.jpg`, `.runtime/d1-long-title-mobile.jpg`.
- Contract D1: lista/detaliul folosesc GET-urile existente ale documentelor din familia activă; filtrarea este locală asupra listei deja autorizate. D2 verifică accesul și descărcarea, D3 verifică uploadul/scannerul. Acestea rămân deschise.

## Verificare D2 — 21 octombrie 2026

- Corecție UI: un conflict 412 lasă formularul în stare învechită, dezactivează opțiunile și salvarea și oferă „Reîncarcă accesul”. Reîncărcarea citește documentul și membrii actuali, înlocuiește selecția veche și permite revizuirea fără salvare automată. Deschiderea administrării citește și ea versiunea actuală. Controalele sunt dezactivate pe durata cererii.
- Browser local: două ferestre Ana reproduc conflictul după private → family → selected. Formularul vechi nu suprascrie accesul, iar reîncărcarea arată „Persoane alese” și Mihai bifat. Fără destinatar, salvarea accesului selectat este dezactivată.
- Sesiune independentă Mihai: documentul selectat poate fi deschis și descărcat, fără buton de administrare. Descărcarea PDF efectivă a fost observată în browser. După retragerea grantului, descărcarea din detaliul deja deschis este refuzată, detaliul și cardul dispar; mesajul spune explicit că documentul nu mai este disponibil sau accesibil. Nu se poate retrage copia deja descărcată.
- „Jurnal de călătorie” a fost readus la „Doar eu”, fără granturi. Nu au fost modificați membrii sau celelalte documente. Revocarea apartenenței și restricțiile adultului sunt preluate din F2, fără repetare.
- Comandă din `backend`: `../.tools/php84/php.exe -d extension=pdo_pgsql -d extension=gd vendor/bin/phpunit tests/Feature/DocumentSecurityTest.php --filter='test_family_owner_cannot_read_other_adults_private_metadata_or_bytes|test_foreign_document_identifiers_and_wrong_parent_are_hidden|test_explicit_grant_and_revocation_apply_to_metadata_and_each_download|test_stale_versions_and_foreign_grants_cannot_change_access' --testdox`. **4 teste / 29 aserțiuni trecute**: document privat al altui adult, ID/părinte străin, grant retras pentru metadata și bytes, versiune veche și grant străin.
- Build TypeScript/Vite trecut; backendul nu a necesitat modificări. Fereastra de acces la 360 px: fără overflow orizontal, butoane de minimum 44 px. Contractul de descărcare rămâne cel existent: serverul verifică accesul la fiecare cerere. D3 și integrarea scannerului rămân deschise.

## Verificare D3 — 21 octombrie 2026

- ClamAV **1.5.4** instalat portabil din distribuția oficială în `.tools/clamav/clamav-1.5.4.win.x64`. FreshClam a validat bazele `daily` **28160**, `main` **63**, `bytecode` **339**. Configurațiile și bazele sunt în `.runtime/`, ignorate de Git. `clamd` ascultă numai pe `127.0.0.1:3310`; pornirea prin `ops/start-local.ps1` a fost verificată. Instrucțiunile sunt în [configurarea locală](../../ops/SETUP.md#4-clamav-și-pornire).
- Corecție reprodusă în browser: înlocuirea unui fișier valid cu unul prea mare lăsa fișierul vechi selectat și „Salvează” activ. Acum selecția se golește și salvarea se dezactivează. Inputul se resetează după alegere pentru a permite reselectarea aceluiași fișier. Limitele de 10 MB/fișier, 100 MB/familie și 16 megapixeli rămân vizibile inclusiv după selecție; dimensiunea fișierului nu este afișată.
- **6 teste D3 / 34 aserțiuni trecute**, cu dublu de scanner: MIME real și acces inițial privat; scanner indisponibil → carantină → retry; respingere și ștergerea bytes; depășirea limitei și cotei; recodarea imaginii și eliminarea datelor adăugate; curățarea fișierului după rollback. Filtrul PHPUnit: `test_clean_upload_is_private_and_checks_real_mime|test_scanner_failure_quarantines_and_retry_can_release_clean_bytes|test_infected_bytes_are_rejected_and_deleted|test_oversized_files_tenant_override_and_quota_are_rejected|test_image_reencoding_removes_extra_payload|test_failed_database_transaction_removes_new_private_bytes`, din `tests/Feature/DocumentSecurityTest.php`.
- **2 teste ClamAV reale / 7 aserțiuni trecute**, cu `CLAMAV_INTEGRATION=1` și filtrul `test_live_clamav`: upload de PDF curat până la descărcare și detectarea semnăturii inofensive EICAR transmisă numai în memorie. EICAR nu a fost încărcat prin formular; ștergerea după verdict infectat este verificată separat cu dublul de scanner, nu prezentată drept upload infectat complet prin ClamAV.
- Browser local: PDF salvat privat în carantină înainte de pornirea scannerului, descărcare dezactivată; după pornire, „Reîncearcă verificarea” produce starea disponibilă și activează descărcarea. HTML redenumit `.pdf` refuzat de server. PNG de 4001 × 4000 pixeli respins, cu `bytes=0` și absența fișierului confirmată pe disc. Cele două documente temporare, bytes și evenimentele lor au fost eliminate; documentele demo sunt păstrate.
- Build TypeScript/Vite trecut. Formular la 360 × 800: limite lizibile, fără overflow orizontal, buton „Salvează” de 44 px. Capturi ignorate de Git: `.runtime/d3-scan-ready.jpg`, `.runtime/d3-upload-mobile.jpg`. Backendul nu a necesitat modificări. F1–F3 și D1–D2 nu au fost repetate.

## Regula pentru scadențe

### D4a — Expirarea independentă, 22 octombrie 2026

- `documents.expires_on` este o dată calendaristică opțională între 1900 și 2100, cu validare API și constrângere PostgreSQL. Migrarea `2026_10_22_171438_add_expiration_to_documents.php` păstrează documentele existente cu `null`; aplicată local pe `memoria`, fără reset/seed. Nu este necesar un nou grant de tabel.
- Uploadul acceptă opțional `expires_on`; detaliul afișează data prin utilitarul calendaristic comun. Proprietarul cu rol nelimitat o poate modifica/șterge prin PATCH `/api/v1/families/{family}/documents/{document}`, cu `If-Match`. Alte metadate și accesul nu se modifică pe această rută. Aceeași valoare nu crește versiunea; schimbarea reală crește versiunea și înregistrează numai acțiunea/ID-ul în audit.
- Câmp comun pentru upload/editor, limite native, ștergere explicită, stări de salvare și conflict cu reîncărcare. Editorul folosește detaliul deja citit; nu face GET suplimentar la deschidere. Data apare în detaliu, fără modificarea cardurilor.
- Verificat: build TypeScript/Vite, Prettier, Pint, lint PHP, rută PATCH; în browser desktop, document existent fără dată → 30 decembrie 2026 → fără dată. Sursa demo a fost readusă la data nespecificată.
- Neexercitate: upload cu dată, validări runtime invalide, conflict concurent, rol limitat și revocare pe noua rută, layout mobil al editorului. Nu s-au scris sau rulat suite automate. Nu se declară acceptarea completă.
- Calendarul, reminderele derivate și contractele de asociere rămân următorii pași funcționali D4/C2. Nu există acum proiecție sau alerte automate pentru această dată.

Expirarea proprie a unui document poate avea o scadență independentă. Dacă documentul dovedește o obligație auto sau un act de îngrijire, data acelei obligații rămâne la sursă. Calendarul nu generează tacit două remindere pentru aceeași obligație.

Arhivarea oprește proiecțiile și alertele aferente; ștergerea definitivă și retenția se închid în [Căutare și date](data.md). OCR, editorul și semnăturile electronice rămân în afara MVP.
