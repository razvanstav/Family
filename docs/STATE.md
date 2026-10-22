# Punct de reluare

Actualizat: 22 octombrie 2026, Europe/Bucharest.

- **Modul activ:** Familie, **FP1 — profilul membrului implementat și verificat local**, pregătit pentru feedback. Numai acest profil, fără începerea altui modul. [Contracte și rezultate FP1](modules/families.md#fp1--profilul-membrului-22-octombrie-2026).
- **Rezultat:** fotografia/numele din Familie deschid profilul, inclusiv cel propriu. Fotografie circulară, Prezentare/Sănătate/Școală și cultură/Activități și Documente în cardul persoanei. Însemnări cu dată, detalii, privat/familie, editare cu versiune, arhivare/restaurare privată și paginare. Avatarul folosește PrivateMedia existent. Detaliul însemnării reutilizează payloadul listei; actualizare manuală/60 s fără suprascrierea draftului.
- **Reguli confirmate:** „public” înseamnă doar familia. Sub 14 ani scriu numai părinții desemnați; de la 14 și persoana; de la 18 numai persoana. Însemnările private sunt vizibile numai autorului, inclusiv cele ale adolescentului față de părinți/proprietar. Proprietarul stabilește data nașterii și părinții, fără acces implicit la private sau drept implicit de părinte. Vârsta și rolul sunt reverificate de API.
- **Navigație:** Acasă → Familia mea → Bucătărie → Amintiri → Calendar, identică desktop/mobil; Acasă implicită, Cont separat. Documente rămâne în Familie. Numele/fotografia deschid acum profilul; Documente din profil deschide dosarul autorizat FD1. Fără schimbarea permisiunilor documentelor.
- **Verificări FP1:** TypeScript/Vite build, Prettier, Pint și diff check trecute. `MemberProfileTest`: 7 teste / 67 de aserțiuni, numai pe memoria_test. Browser 390 px: Ana cu fotografie/adăugare, Mihai numai de consultare pentru Ana, patru file fără overflow, formularele sănătate/carte/fotografie, selector părinți și dosarul gol Mihai fără upload în numele lui. Exemplul de dată în formular a fost anulat. Nu s-au salvat date personale/fotografii demo în aplicație. Profilul Anei este deschis pe Prezentare.
- **Limite:** profilurile sunt pentru membrii cu cont existenți. Fără profiluri fără cont/legare la cont, galerie, tratamente automate sau Calendar nou. Datele necunoscute nu încadrează automat membrii drept copii. Browserul nu a probat salvări de însemnări ori uploaduri reale; acestea au probe în teste. Fără probă dedicată a intervalului 60 s, revocări live, scanner indisponibil, paginare mare sau dispozitiv fizic. F4/P1/P2 rămân deschise.
- **Probe:** `.runtime/fp1-profile-mobile.png` și `.runtime/fp1-parent-settings-mobile.png`, ignorate; a doua este un draft nesalvat. Referință: profilul Animale din `artifacts/design-reference-family-mvp.png`.
- **Pași anteriori:** H2, KH1, FD1, MH1 și CH1 existente. Cele șase evenimente demo Calendar, două amintiri și masa/votul Popescu sunt păstrate. Planificatorul este numai schițat. D4a și AM1–AM3 își păstrează limitele; nu le reîncepe implicit.
- **Livrare:** commit local pe `codex/documente-design`, fără push. Verificarea automată anterioară a respins publicarea GitHub fără acord explicit; nu reîncerca implicit. Nu blochează dezvoltarea locală.

## Prima acțiune la reluare

Preia feedbackul pentru [FP1 — Profilul membrului](modules/families.md#fp1--profilul-membrului-22-octombrie-2026). Nu începe alt profil/layout, F4 sau integrare Calendar fără cerere nouă. Pentru ajustări, citește numai fișa FP1 și codul relevant. Nu inventa date personale și nu dubla demo-urile existente.

## Mediu local

- Windows, PHP 8.4.24 în PATH (`C:\Users\razva\php84\php.exe`), Laravel 13.38.0; vendor și node_modules existente. CLI pentru DB/media: `-d extension=pdo_pgsql -d extension=gd`. Buildul `backend/public/app/` este ignorat. Fără dependențe noi.
- PostgreSQL `127.0.0.1:55432`, ClamAV `127.0.0.1:3310`. Pornire `.\ops\start-local.ps1`, oprire `.\ops\stop-local.ps1`; workeri ascunși outbox/remindere la 15 s. Reverifică procesele la reluare.
- Migrația FP1 `2026_10_22_201929_create_member_profiles.php` și granturile sunt aplicate aditiv pe `memoria`; pe `memoria_test` s-au aplicat și migrațiile restante K4b/AM/D4a. Testele refac exclusiv memoria_test; aplicația nu a fost resetată. Secretele/datele locale sunt ignorate, fără afișare sau commit.
- Alte medii cer migrațiile și `ops/grant-runtime.sql` înainte de noul build, cu rol runtime fără BYPASSRLS. Nu schimba `recipe_versions`; mesele păstrează versiunea exactă. Rebuild/replay în [SETUP](../ops/SETUP.md).
