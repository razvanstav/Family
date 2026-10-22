# Operare și verificare

Stare: mediul local și scripturile de pornire/test există. CI de bază se introduce în M04; stagingul și acceptarea pentru beta se închid în M11.

Decizie de găzduire la 22 octombrie 2026: utilizatorul predă instalarea pe serverul Linux dedicat propriu unei alte persoane. Cerințele și limitele sunt în [nota pentru instalator](../../ops/SETUP.md#predare-pentru-serverul-linux). O2 rămâne planificat și neverificat pe server; nu există încă o configurație Linux gata de aplicat.

## Fișiere și comenzi existente

- Instalare: [ops/SETUP.md](../../ops/SETUP.md). Consultă-l la instalare ori la o problemă de mediu, nu la fiecare reluare.
- Pornire: `./ops/start-local.ps1`; oprire: `./ops/stop-local.ps1`.
- Suita locală cu antivirus real și build: `./ops/test-local.ps1`.
- `./ops/test-local.ps1 -WithoutAntivirus` omite integrarea ClamAV reală; nu poate închide acceptarea scannerului real.
- Pentru teste restrânse, folosește `Resolve-MemoriaPhp` din `ops/resolve-php.ps1` și PHPUnit din `backend`, cu extensiile `pdo_pgsql`/`gd` și fișierul ori filtrul afectat. Testele folosesc `memoria_test`, nu datele de dezvoltare.
- Frontend: `npm.cmd run check` sau `npm.cmd run build` din `frontend`, după natura schimbării.

## Pași

- [ ] **O1 — CI de bază în M04.** Fixează mediul reproductibil, PostgreSQL de test și rolul runtime real; rulează verificările backend, TypeScript și buildul. Adaugă verificarea contractelor și limitelor de import pe măsură ce apar. Schimbările PHP respectă formatterul și instrucțiunile locale; noile migrații sunt probate pe o bază curată și prin upgrade.
- [ ] **O2 — Staging.** Configurează separat DB, storage, email și chei, numai cu date sintetice. Livrează builduri versionate, migration job unic, worker/scheduler și HTTPS cu cookie sigur. Conturile demo și fixture-urile sunt excluse din publicare. Mută scanarea și cleanupul în execuție supravegheată înainte de utilizare reală.
- [ ] **O3 — Backup și restaurare.** Stabilește țintele de pierdere/recuperare acceptate; implementează backup pentru DB și obiecte, apoi restaurează într-un mediu izolat. Verifică consistența fișierelor, replayul ștergerilor și timpul măsurat. Nu deduce protecția bytes din existența unui backup DB.
- [ ] **O4 — Monitorizare și release.** Verifică readiness, erori redactate, corelare cereri/joburi, lag outbox, retry, quarantine, cotă și livrări incerte. Probează alarmele, drenarea workerilor și rollbackul compatibil cu schema; altfel folosește remediere înainte, fără rollback distructiv.
- [ ] **O5 — Acceptare beta.** Rulează regresia integrată, verificările de accesibilitate/mobil, cele trei fluxuri Bucătărie–Amintiri, Animale–Calendar și Vehicule–Calendar, plus revocarea pe toate suprafețele. Măsoară interogările și paginarea timeline pe un volum sintetic reprezentativ, inclusiv reperul de 100.000 de elemente din blueprint. Închide defectele blocante înainte de beta.

## Acceptare pentru beta

- Toate cele șase module din blueprint sunt utile și au fluxurile declarate acceptate; nu există defect blocant cunoscut de izolare, acces la fișiere sau pierdere de date.
- Restoreul DB+objects a fost executat și măsurat; logurile/backupurile nu ignoră ștergerile finalizate.
- Migrațiile, joburile și buildurile pot fi reproduse; secretele și datele reale nu sunt în Git sau în fixture-uri.
- Limitările, suportul și comportamentul la eroare sunt clare pentru utilizatorii beta. Publicarea aplicației este o acțiune separată de pushul surselor pe Git.

## Evidența verificărilor

În STATE se păstrează doar comanda relevantă, rezultatul și limita rămasă. Nu recopia outputuri mari în fișe. Testele din blueprint sunt specificații, nu rezultate executate.

Pentru documentație: verifică linkurile și referințele, coerența modulelor/pașilor și `git diff --check`. Nu porni servicii și întreaga suită pentru o schimbare exclusiv de documentație.
