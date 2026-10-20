# Documente

Stare: bază implementată local. D1–D3 verifică M02; D4 extinde modulul în M04, după contractele necesare din Platformă.

## Ce există

Listă, căutare locală, categorii, filtru de acces, detaliu, upload PDF/JPEG/PNG/WebP, carantină/retry și descărcare. Documentul nou este privat. Proprietarul lui poate selecta acces privat, familie sau membri aleși; modificarea folosește `If-Match`.

## Fișiere relevante

- UI: `frontend/src/modules/documents/Documents.tsx`, `documents.css`.
- API și autorizare: `backend/app/Modules/Documents/Http/DocumentController.php`, `Application/DocumentAccess.php`.
- Teste: `backend/tests/Feature/DocumentSecurityTest.php`.
- Bytes, scanner și cotă: [Platformă](platform.md). Aspect și dialoguri: [Interfață](interface.md).

## Pași

- [ ] **D1 — Listă și detaliu.** Verifică cele două familii, căutarea, filtrele, titlurile lungi, stările goale/eroare și rolurile. Titlul complet rămâne accesibil în detaliu și cititorului de ecran; cardurile mobile rămân compacte.
- [ ] **D2 — Acces și descărcare.** Verifică private/family/selected, grantul retras, membrul revocat, accesul direct prin ID și versiunea învechită. Acoperă metadata și bytes; proprietarul familiei nu poate ocoli intimitatea altui adult.
- [ ] **D3 — Upload și verificare.** Verifică MIME falsificat, fișier prea mare, cotă, imagine recodată, scanner indisponibil, retry și fișier infectat. Rulează testele relevante și integrarea ClamAV reală înainte de închiderea pasului; nu prezenta dublul de test drept scanner real.
- [ ] **D4 — Contract pentru celelalte module.** După P1–P2, stabilește și implementează incremental paginile/atașamentele, subiecții și expirarea independentă a documentului. Expune contractele de asociere și acces pentru Pets/Vehicles; nu permite citirea tabelelor interne din alte module. Verifică modelul de media cu un singur proprietar și migrarea documentelor existente.

## Acceptare

- Fiecare descărcare verifică membrul activ, politica actuală și fișierul curat; fără URL public sau cache privat persistent.
- `If-Match` învechit nu suprascrie accesul nou; granturi și ID-uri străine nu lărgesc vizibilitatea.
- Un fișier respins este eliminat; scannerul indisponibil păstrează carantina fără descărcare. Un rollback nu lasă fișier nou utilizabil.
- Limitele actuale rămân 10 MB/fișier, 100 MB/familie și 16 megapixeli până la o schimbare explicită, verificată și comunicată în formular.

## Regula pentru scadențe

Expirarea proprie a unui document poate avea o scadență independentă. Dacă documentul dovedește o obligație auto sau un act de îngrijire, data acelei obligații rămâne la sursă. Calendarul nu generează tacit două remindere pentru aceeași obligație.

Arhivarea oprește proiecțiile și alertele aferente; ștergerea definitivă și retenția se închid în [Căutare și date](data.md). OCR, editorul și semnăturile electronice rămân în afara MVP.
