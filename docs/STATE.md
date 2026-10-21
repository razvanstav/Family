# Punct de reluare

Actualizat: 21 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Bucătărie, **K2 — editare și istoricul versiunilor — implementat și verificat local**. Contractele și probele sunt în [fișa Bucătărie](modules/kitchen.md#k2--editare-și-istoricul-versiunilor).
- **Prioritate îndeplinită:** editarea creează o versiune nouă; mesele deja adăugate păstrează versiunea și fotografia folosite. Istoricul permite citirea versiunilor anterioare, iar masa oferă „Rețeta folosită”.
- **Protecții:** editare numai de autorul activ cu rol proprietar/adult, acces canonic pentru istoric și fotografii, RLS/FK existente și trigger de imutabilitate păstrate. If-Match obligatoriu; 428/412 nu scriu versiuni/media/audit. Versiunea, pointerul curent, fișierul nou și auditul sunt atomice. O masă partajată nu deschide o rețetă privată.
- **Verificări automate:** Kitchen K1/K2/K3a: **31 teste / 450 aserțiuni**, dintre care K2 are 9 teste noi / 124 aserțiuni. Verificarea completă `ops/test-local.ps1`: **109 teste backend / 1.040 aserțiuni**, inclusiv ClamAV live, pe `memoria_test`, fără teste sărite. Cele **20 de teste frontend**, buildul TypeScript/Vite și Pint au trecut. Buildul a fost reluat după ultima corecție frontend; backendul nu s-a schimbat după rularea completă.
- **Verificări UI:** Ana a creat rețetă/masă/vot demo, a editat rețeta și a citit versiunea veche din istoric și din masă, cu ingredientele/prepararea inițiale și feedbackul intacte. Două ferestre au demonstrat conflictul, păstrarea draftului și reîncărcarea. Desktop 1376 × 1244 și mobil 360 × 800, fără overflow, controale minimum 44 px, focus și Escape. Capturi ignorate `.runtime/k2-history-desktop.png`, `.runtime/k2-history-mobile.png`, `.runtime/k2-used-recipe.png`.
- **Curățare:** rețeta temporară `328279bb-2ed7-4470-948e-82362fa2553c`, masa `99550474-7903-4d18-8b7a-177beed46712`, două versiuni, un vot/comentariu și patru evenimente audit eliminate strict după ID. Verificare DB: zero resurse ale probei, trei exemple păstrate. Nu s-au resetat identități, apartenențe, parole ori alte date. Fereastra suplimentară a fost închisă.
- **Limite:** uploadul foto keep/replace/remove, rolurile, revocarea și paginarea sunt verificate prin API; nu toate repetate în browser. Nu s-au provocat separat erori de rețea/închidere în timpul scrierii. Dispozitivele fizice și producția nu sunt verificate. Accesul modificabil/selectiv, arhivarea/restaurarea, preparările libere/participanții, editarea meselor, planificarea/cumpărăturile și integrarea meselor în Calendar/timeline rămân nelivrate.
- **Pași anteriori:** K1, K3a, F1–F3, D1–D3, A1, C1, C3 in-app și H1 sunt documentate în fișele lor. Ordinea confirmată rămâne Acasă → Familia mea → Documente → Bucătărie → Calendar, identică desktop/mobil; Acasă implicită. Schimbarea familiei păstrează pagina globală și remontează modulul.
- **Livrare solicitată:** utilizatorul a cerut commit și push **integral** al modificărilor proiectului, inclusiv cele anterioare. Ramură: `codex/documente-design`, remote `origin` (`razvanstav/Family`). Nu include fișierele ignorate cu date locale, secrete sau runtime.
- **Blocaje:** niciunul pentru K2.

## Prima acțiune la reluare

K2 este încheiat. Citește [fișa Bucătărie](modules/kitchen.md), secțiunea „Următorul pas”, și cererea nouă a utilizatorului înainte de a alege K4 sau K3b. Nu începe automat alt pas/modul. La extinderea ACL Recipes în K4, aliniază politica sursei din MealCatalog, istoricul și fotografiile. Nu modifica rândurile recipe_versions; mesele păstrează versiunea exactă.

Instrucțiunile sunt în `AGENTS.md`. Păstrează Helvetica, crema/verde închis, etichetele directe și numai funcții reale. Referința Bucătărie: `artifacts/kitchen-reference.png`; generală: `artifacts/design-reference-layout.jpeg`, cu paleta `artifacts/design-reference-warm.png`.

## Mediu local

- Aplicație: http://127.0.0.1:8000/app/, sesiune demo Ana, familia Popescu. Aplicația și workerul de remindere rămân pornite. PostgreSQL `127.0.0.1:55432`; ClamAV `127.0.0.1:3310`.
- PHP prin PATH: `C:/Users/razva/php84/php.exe`; `.tools/php84/php.exe` nu există. Runtime ignorat: `.tools/composer.phar`, `.tools/postgresql/pgsql/`, `.tools/clamav/clamav-1.5.4.win.x64/`. PHPUnit folosește exclusiv `memoria_test`, niciodată `memoria`.
- Pornire `.\ops\start-local.ps1` (acceptă `-PhpPath`), oprire `.\ops\stop-local.ps1`. Worker `calendar:deliver-reminders --watch`, la 15 s; loguri `.runtime/reminders.out.log` / `.runtime/reminders.err.log`. După modificarea livrării se repornește workerul. Procese ascunse.
- ClamAV: actualizare manuală a semnăturilor, vezi [SETUP](../ops/SETUP.md#4-clamav-și-pornire). Nu afișa credențiale sau `.env`. La `psql -c` pe Windows folosește SQL ASCII cu escape Unicode ori fișiere UTF-8.
