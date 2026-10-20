# Platformă

Stare: izolare pe familie, stocare privată locală, scanare ClamAV și audit minimal există. M04 adaugă numai infrastructura cerută de următoarele module.

## Fișiere existente

- `backend/app/Platform/Contracts/PrivateMedia.php`.
- `backend/app/Platform/Media/LocalPrivateMedia.php`, `Scanner.php`, `ClamAvScanner.php`.
- `backend/app/Platform/Families/TenantContext.php`, `backend/app/Platform/Audit/Audit.php`.
- `backend/database/migrations/2026_10_20_000001_create_document_vertical.php`, `2026_10_20_215355_create_family_invitations_table.php`.
- RLS, FK, audit și rollback: `backend/tests/Feature/DocumentSecurityTest.php`.

## Pași

- [ ] **P1 — Limite și contracte.** Definește explicit proprietarul fiecărui tabel/resursă, contractele publice și registrul minim de subiecți per familie. User, membru al familiei și subiect sunt identități distincte. Subiectul nu conține datele de domeniu și nu acordă acces. Verifică FK compuse, RLS cu rolul runtime și înregistrarea explicită a modulelor.
- [ ] **P2 — Media și execuție în fundal.** Mută scanarea în worker cu stări explicite, rezervări atomice de cotă, retry și cleanup după crash. Media se revendică atomic de o singură resursă; downloadul îi folosește politica actuală. Adaugă storage privat înlocuibil și verifică DB+bytes, fără a expune obiecte publice. Închide fiecare subflux înainte de următorul.
- [ ] **P3 — Evenimente durabile.** Adaugă outbox în aceeași tranzacție cu modificarea, livrare per handler, versiunea sursei, retry și idempotency. Testează crash după commit, mesaje duplicate și sosite în altă ordine; proiecția trebuie să poată fi reconstruită.
- [ ] **P4 — Providere și transport.** Adaugă contracte restrânse pentru resurse autorizate, căutare, timeline și scadențe, pe măsură ce au consumatori. Notifications transportă mesaje și gestionează retry/preferințe; Calendar calculează reminderele. Jobul are context de familie explicit și reverifică accesul și activarea modulului înainte de livrare.

## Acceptare

- Rolul runtime nu deține tabelele, nu are superuser/BYPASSRLS; contextul tranzacțional nu se scurge între familii. Bootstrapul de identitate rămâne filtrat explicit de aplicație.
- O legătură între module sau un ID de media nu permite extinderea accesului; politica resursei canonice este obligatorie și pentru joburi.
- Rollback, crash și retry nu produc fișiere accesibile neautorizat, depășiri de cotă sau proiecții duplicate.
- Evenimentele/auditul au identificatori și date minimale; fără conținut privat, parole, tokenuri sau identificatori auto sensibili în loguri.

## Referințe și limite

Pentru pasul activ consultă numai interfețele relevante din `Memoria_Familiei_Review_v1.2/contracts.reference.php`, structura din `monorepo.txt` și scenariul necesar din `acceptance-scenarios.feature`. Aceste contracte sunt propuneri de adaptat codului existent, nu implementări deja prezente.

Nu adăuga un motor generic de permisiuni, încărcător de pluginuri sau Repository pentru fiecare model. Interfața unui modul este contractul public; integrările folosesc contracte, fără acces la persistența altui modul.
