# Vehicule

Stare: planificat, fără implementare de produs. Etapa M09 cere Documente D4, subiecți, Calendar și notificări.

## Scop

Profil și identificatori protejați, kilometraj, service, alimentări și obligații RCA/ITP/rovinietă introduse manual. Obligația deține scadența; documentul asociat dovedește obligația și are acces separat.

## Pași

- [ ] **V1 — Profil și identificatori.** Definește modelul, accesul și contractele. Livrează profilul cu editare concurentă protejată; câmpurile sensibile nu apar în loguri, audit sau căutare implicită.
- [ ] **V2 — Istoric.** Adaugă separat kilometraj, service și alimentări cu unități și validări. Corectarea unei citiri istorice este explicită și auditată; verifică efectul asupra intrărilor ulterioare, fără presupunerea că ordinea introducerii este ordinea evenimentelor.
- [ ] **V3 — Obligații și document.** Creează o obligație cu document asociat prin contract și o scadență canonică. Închiderea, modificarea și arhivarea actualizează Calendarul; verifică unicitatea la cereri concurente.
- [ ] **V4 — Flux complet.** Verifică obligație → document → un reminder → email, apoi editare/anulare și acces revocat. Probează document cu expirare independentă fără a genera tacit o dublură pentru aceeași obligație.

## Acceptare

- Datele din altă familie nu pot fi legate prin profil, citire, service, obligație sau document.
- Calendarul citește scadența obligației; nu oferă editarea unei copii concurente. Retry nu dublează obligația ori proiecția.
- Documentul privat nu devine accesibil prin simpla asociere cu un vehicul vizibil.
- Istoricul și corecțiile de kilometraj sunt consecvente; consumul nu se deduce din date insuficiente.

## Surse și căi viitoare

Consultă operațiile `Vehicles_` și `Vehicle_`, regulile pentru scadență unică și scenariile de concurență/kilometraj din pachetul v1.2.

Implementarea va sta în `backend/app/Modules/Vehicles/` și `frontend/src/modules/vehicles/`, cu acces numai la contractele publice ale dependențelor.

Verificarea automată în registre, telematica și integrările cu furnizori de asigurări sunt în afara MVP.
