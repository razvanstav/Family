# Modulele aplicației

La reluare pornește din [STATE.md](STATE.md). Ordinea etapelor și criteriile comune sunt în [ROADMAP.md](ROADMAP.md). Citește numai fișa necesară pasului activ.

## Module de produs

| Modul | Situație la planificare | Fișă |
| --- | --- | --- |
| Familie | Membri, invitații, roluri și revocare implementate local; prima etapă de reluat | [Familie](modules/families.md) |
| Documente | Upload, listă, detaliu, acces și descărcare implementate local | [Documente](modules/documents.md) |
| Bucătărie | Planificat; fără implementare de produs | [Bucătărie](modules/kitchen.md) |
| Amintiri | Planificat; fără implementare de produs | [Amintiri](modules/memory.md) |
| Calendar | Planificat; fără implementare de produs | [Calendar](modules/calendar.md) |
| Animale | Planificat; fără implementare de produs | [Animale](modules/pets.md) |
| Vehicule | Planificat; fără implementare de produs | [Vehicule](modules/vehicles.md) |

## Servicii și funcții comune

| Zonă | Situație la planificare | Fișă |
| --- | --- | --- |
| Autentificare | Sesiune, CSRF, login/logout și cont din invitație; recuperarea parolei și emailul rămân de construit | [Autentificare](modules/identity.md) |
| Platformă | Izolare PostgreSQL, media locală, antivirus și audit; restul se construiește la nevoie | [Platformă](modules/platform.md) |
| Interfață | Stiluri, shell, dialog și API comune; se verifică în fiecare modul | [Interfață](modules/interface.md) |
| Căutare și drepturi asupra datelor | Căutare locală în Documente; căutarea globală, exportul și ștergerea sunt planificate | [Căutare și date](modules/data.md) |
| Operare | Pornire și teste locale; CI, staging, restaurare și lansare sunt planificate | [Operare](modules/operations.md) |
| Aplicații mobile | Planificate după stabilizarea web; fără PWA offline sau aplicații native actuale | [Mobil](modules/mobile.md) |

## Surse

Codul și rutele actuale stabilesc ce există. README-ul principal explică versiunea locală. `Memoria_Familiei_Review_v1.2/` păstrează blueprintul și contractele pentru dezvoltarea viitoare; acestea nu sunt inventarul funcțiilor livrate.

Fișele păstrează numai deciziile și verificările utile modulului. Nu copia aici jurnale lungi, rezultate complete de teste, date de acces sau conținut privat.
