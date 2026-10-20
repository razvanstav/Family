# Memoria Familiei — prima etapă

**Dezvoltare modulară:** [punct de reluare](docs/STATE.md) · [module](docs/README.md) · [plan de dezvoltare](docs/ROADMAP.md). La continuare se citește starea și fișa modulului activ, fără reluarea întregii documentații.

Aplicație locală funcțională: două familii, documente private, upload cu verificare antivirus, acces selectiv, invitații și revocare. Interfața React urmează [referința de design actuală](artifacts/design-reference-warm.png): fundal crem, carduri deschise, verde închis pentru acțiuni și iconițe colorate pe suporturi discrete.

Interacțiunile sunt inspirate de iOS: carduri aerisite, titluri clare, feedback discret la apăsare și ferestre care urcă de jos pe mobil. Tranzițiile durează 160–260 ms și sunt dezactivate cu `prefers-reduced-motion`. Dialogurile păstrează focusul, închiderea cu Escape și derularea conținutului; pagina din spate nu se derulează cât timp este deschis un dialog. Verificarea vizuală este făcută în browserul local, nu pe un dispozitiv iOS fizic.

**Continuare pe alt calculator:** clonează branchul `codex/documente-design` și urmează [instalarea de la zero](ops/SETUP.md). Git conține sursele și datele demo generate de seeder; instalările locale, parolele și documentele încărcate nu sunt transferate.

Toată interfața folosește `Helvetica, "Helvetica Neue", Arial, sans-serif`, cu căutare, categorii și acțiuni reale. Helvetica este folosită dacă există pe dispozitiv; Windows folosește de regulă Arial. Nu este inclus un fișier de font proprietar. Pentru același font exact pe toate dispozitivele este necesară o versiune web Helvetica cu licență potrivită. Regulile pentru paginile viitoare sunt în `AGENTS.md`; stilurile comune sunt centralizate în `frontend/src/styles.css`.

Capturi din aplicația verificată: [mobil](artifacts/mobile-documents.png) · [desktop](artifacts/documents-desktop.png).

Familia mea: [mobil](artifacts/family-mobile.png) · [desktop](artifacts/family-desktop.png) · [adaugă membru](artifacts/family-invite-mobile.png) · [gestionează membrul](artifacts/family-manage-mobile.png).

Autentificare: [mobil](artifacts/login-mobile.png).

## Deschidere

În acest workspace, PostgreSQL și ClamAV sunt instalate portabil în `.tools/`; datele și configurațiile locale sunt în `.runtime/`. Nu sunt servicii Windows și ascultă exclusiv pe `127.0.0.1`.

```powershell
.\ops\start-local.ps1
```

- Aplicație: http://127.0.0.1:8000/app/
- Previzualizare mobil la 390 px: http://127.0.0.1:8000/preview/mobile
- Oprire: `.\ops\stop-local.ps1`
- Dacă PHP este instalat în alt loc: `.\ops\start-local.ps1 -PhpPath 'cale/php.exe'`

Conturile sunt **fictive și exclusiv pentru dezvoltare**. Butoanele Ana / Mihai / Elena completează formularul, apoi apeși „Intră în cont”.

| Cont | Email | Acces inițial |
| --- | --- | --- |
| Ana | ana@example.test | Proprietar Popescu; adult Ionescu |
| Mihai | mihai@example.test | Adult Popescu |
| Elena | elena@example.test | Proprietar Ionescu |

Parola demonstrativă comună: `Familie-Demo-2026!`. Nu reutiliza aceste conturi în producție. Butoanele apar doar în `APP_ENV=local` cu `MEMORIA_DEMO=true`; seederul refuză producția.

## Ce funcționează

- Autentificare cu sesiune Laravel, cookie HttpOnly, regenerarea sesiunii la login și protecție CSRF. Fără tokenuri în localStorage.
- Selector de familie, listă și căutare locală în documentele autorizate, categorii, filtru de acces, detaliu, descărcare.
- Pagina Familia mea: carduri compacte de aproximativ 80 px pe mobil, avatare și iconițe colorate după rol, căutare după nume și administrare prin butonul cu trei puncte. Proprietarul poate crea/anula invitații, schimba rolurile adult/acces limitat și revoca accesul cu confirmare.
- Schimbarea rolului se aplică la cererile următoare, inclusiv sesiunilor existente. „Acces limitat” blochează încărcările noi; păstrează documentele proprii și accesul la cele partajate. Rolul proprietarului este protejat. Formularul refuză salvarea dacă rolul curent s-a schimbat între timp, iar aplicația reverifică sesiunea la revenirea în tab.
- Invitație prin link, valabilă 7 zile și utilizabilă o singură dată. Conturile existente se autentifică cu emailul invitat; persoanele noi își aleg numele și parola. Membrii revocați nu pot fi reactivați prin invitație.
- Upload PDF/JPEG/PNG/WebP, maximum 10 MB; cotă cumulată de 100 MB/familie pentru această etapă.
- Verificarea tipului din conținut, hash SHA-256, scanare ClamAV prin INSTREAM; imagini decodate și recodate cu GD, fără EXIF/metadate. Maximum 16 megapixeli; limitele de dimensiune/cotă se verifică și după recodare.
- Fișier nou privat implicit. `private`, `family` sau `selected` cu permisiuni de citire pentru membrii aleși. Numai proprietarul documentului schimbă accesul.
- Modificarea accesului folosește `If-Match` pentru a respinge versiunile vechi.
- Revocarea accesului unui membru de către proprietarul familiei. Nu este permisă revocarea proprietarului din acest ecran.
- Audit minimal: actor, acțiune, ID resursă, dată; fără titluri, nume de fișiere, conținut sau parole.

Un fișier respins este eliminat. Dacă scannerul este indisponibil, documentul rămâne în **carantină**, fără descărcare. Proprietarul poate relansa verificarea din detaliu.

Invitațiile nu trimit email automat. Proprietarul copiază linkul afișat o singură dată și îl transmite doar destinatarului. Linkul permite crearea contului pentru emailul invitat dacă acesta nu există; adresa nu este marcată drept verificată. Tokenul are 256 de biți, este stocat numai ca hash SHA-256 și apare în fragmentul URL, nu în path/query. Inspectarea și acceptarea folosesc POST cu CSRF, rate limiting și răspunsuri fără cache. Tokenul nu apare în audit. Linkurile de pe `127.0.0.1` funcționează numai pe calculatorul local; folosirea între dispozitive necesită publicarea aplicației pe HTTPS.

## Arhitectură și limite de securitate

Laravel 13 / PHP 8.4, React 19 / TypeScript / Vite, PostgreSQL 18.6, ClamAV 1.5.4. Backend și frontend sunt servite pe aceeași origine.

```text
backend/app/Platform/
  Identity/           sesiune și răspunsuri fără cache
  Families/           context de familie, invitații și revocarea membrilor
  Contracts/          portul PrivateMedia
  Media/              stocare privată și scanner ClamAV
  Audit/              evenimente minimale
backend/app/Modules/Documents/
  Application/        politica de vizibilitate
  Http/               API și orchestration pentru documente
frontend/src/
  app/                autentificare, selector, navigație
  modules/documents/  listă, detaliu, upload, acces
  modules/families/   membri, invitații, acceptare și revocare
  shared/             client API tipizat și dialog accesibil
ops/                  pornire, oprire, configurare, teste
```

`memoria_app` este rolul runtime: **nu este proprietarul tabelelor, nu este superuser, nu are BYPASSRLS**. Migrațiile și fixture-urile folosesc separat rolul local de administrare. `FORCE ROW LEVEL SECURITY` și contextul `set_config(..., true)` din tranzacție protejează documentele, fișierele, granturile, invitațiile și auditul. Lipsa contextului nu permite citirea lor. Cheile străine compuse împiedică legarea între familii.

Tabelele bootstrap de identitate/familii nu au RLS; API-ul le filtrează explicit după utilizatorul autentificat și statutul membrului. RLS asigură limita familiei; politica aplicației asigură intimitatea fiecărui adult. Proprietarul familiei nu primește automat acces la documentele private ale altor adulți.

Fișierele sunt în `backend/storage/app/private`, în afara directorului public. Nu există symlink public sau URL semnat de descărcare. Fiecare `GET .../content` reverifică membrul activ și politica documentului și răspunde ca attachment, cu `no-store` și `nosniff`.

Pentru această etapă, cererile din aceeași familie sunt serializate prin lock pe rândul familiei. Revocarea și verificarea accesului au astfel o ordine tranzacțională clară. O cerere de descărcare deja autorizată poate fi în curs când revocarea se încheie; cererile noi sunt blocate. Copiile deja descărcate nu pot fi retrase.

Uploadul și scanarea sunt sincrone în prima etapă. Un rollback șterge fișierul nou. Pentru volum real, mutăm scanarea în worker și introducem rezervări de cotă și curățare periodică a fișierelor orfane după întreruperi de proces.

## API implementat

Toate rutele sunt sub `/api/v1`. Obține tokenul CSRF din `GET /session`; trimite-l în `X-CSRF-TOKEN` la mutații.

| Metodă | Rută | Efect |
| --- | --- | --- |
| GET | `/session` | Utilizator, familii active, token CSRF |
| POST | `/login` | Email + parolă |
| POST | `/logout` | Închide sesiunea |
| GET | `/families/{family}/members` | Membri activi |
| PATCH | `/families/{family}/members/{member}/role` | role + expected_role (adult/limited); numai proprietarul; 409 dacă rolul curent diferă |
| DELETE | `/families/{family}/members/{member}` | Revocă membru |
| GET | `/families/{family}/invitations` | Invitații active, numai proprietarul |
| POST | `/families/{family}/invitations` | email + role; returnează tokenul o singură dată |
| DELETE | `/families/{family}/invitations/{invitation}` | Anulează invitația |
| POST | `/invitations/{family}/inspect` | token; informații pentru acceptare |
| POST | `/invitations/{family}/accept` | token; cont nou: name, password, password_confirmation |
| GET | `/families/{family}/documents` | Numai documente autorizate |
| POST | `/families/{family}/documents` | Multipart: title, category, file |
| GET | `/families/{family}/documents/{id}` | Detaliu și ETag |
| PUT | `/families/{family}/documents/{id}/sharing` | visibility, membership_ids; If-Match obligatoriu |
| GET | `/families/{family}/documents/{id}/content` | Descărcare autorizată |
| POST | `/families/{family}/documents/{id}/scan` | Reîncearcă verificarea |

Contractul complet din `Memoria_Familiei_Review_v1.2` este **proiectul arhitectural pentru etapele viitoare**, nu inventarul API deja implementat. Actuala etapă folosește un client TypeScript scris explicit pentru rutele de mai sus.

## Verificare

```powershell
.\ops\test-local.ps1
```

Testele folosesc exclusiv `memoria_test`, iar fixture-urile folosesc un disc de test Laravel. Acest script include testele cu ClamAV real. `-WithoutAntivirus` omite cele două teste de integrare antivirus; celelalte teste continuă să verifice scenariile clean/infected/unavailable cu un dublu de test.

Scenarii: acces neautentificat; familii distincte; proprietar de familie vs. document privat; acces prin ID străin; acordare/revocare grant; membru revocat cu sesiune existentă; roluri; ETag învechit; MIME falsificat; limită de dimensiune și cotă; carantină și retry; ștergerea fișierelor respinse; recodare imagini; RLS real cu rol restricționat; context fără scurgeri între tranzacții; FK compus; CSRF; audit fără conținut privat; upload cu ClamAV real; semnătura inofensivă EICAR transmisă în memorie scannerului local.

Pentru dezvoltarea UI: `npm ci` și `npm run build` în `frontend`. Pentru backend: `php ../.tools/composer.phar install` în `backend`. Extensiile PHP necesare includ pdo_pgsql, fileinfo, mbstring, openssl, gd.

Semnăturile antivirus se actualizează astfel (executabilul poate avea alt director după un upgrade):

```powershell
.\.tools\clamav\clamav-1.5.4.win.x64\freshclam.exe --quiet --config-file=.runtime/freshclam.conf
```

## Ce rămâne pentru etapele următoare

Aceasta este o versiune locală de dezvoltare, nu o lansare în producție. Urmează livrarea invitațiilor prin email și verificarea adresei, recuperarea parolei, reactivarea membrilor, transferul proprietății familiei, ștergere și retenție, worker de scanare, storage S3 privat, backup/restaurare și monitorizare. Celelalte module din blueprint nu sunt încă implementate. PWA offline, service worker și aplicațiile native nu sunt activate; datele private nu sunt păstrate offline.

Înainte de publicare: conturi reale fără fixture-uri, HTTPS și `SESSION_SECURE_COOKIE=true`, deployment cu roluri DB separate, actualizarea automată a semnăturilor ClamAV, scanare/cleanup în worker, politici de retenție și backup testat. `.tools`, `.runtime`, `.env`, `.env.testing`, vendor, node_modules și fișierele private sunt excluse din Git.
