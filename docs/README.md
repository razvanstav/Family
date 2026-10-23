# Modulele aplicației

La reluare pornește din [STATE.md](STATE.md). Ordinea etapelor și criteriile comune sunt în [ROADMAP.md](ROADMAP.md). Citește numai fișa necesară pasului activ.

## Module de produs

| Modul | Situație la planificare | Fișă |
| --- | --- | --- |
| Familie | FW1: Familia mea în stilul cald, cu membri și administrare existente; antet comun Membri/Documente, profil și coduri de invitație păstrate | [Familie](modules/families.md) |
| Documente | În Familia mea → Documente; dosare pe persoană și comune, cu upload/acces/descărcare existente | [Documente](modules/documents.md) |
| Bucătărie | Rețete, versiuni, mese, vot și acces implementate; KH1 mobil pregătit pentru feedback | [Bucătărie](modules/kitchen.md) |
| Amintiri | MH1 mobil pregătit pentru feedback; AM1–AM3 implementate, acceptarea completă a fluxurilor rămâne deschisă | [Amintiri](modules/memory.md) |
| Calendar | C1/C3 in-app existente; CH1 mobil implementat, cu Lună/Săptămână/Agendă; așteaptă feedback | [Calendar](modules/calendar.md) |
| Animale | Planificat; fără implementare de produs | [Animale](modules/pets.md) |
| Vehicule | Planificat; fără implementare de produs | [Vehicule](modules/vehicles.md) |

## Servicii și funcții comune

| Zonă | Situație la planificare | Fișă |
| --- | --- | --- |
| Autentificare | Creare cont directă, familie nouă sau cod de invitație, sesiune/CSRF/login/logout și Contul meu; recuperarea parolei rămâne planificată | [Autentificare](modules/identity.md) |
| Platformă | Izolare PostgreSQL, media locală, antivirus și audit; restul se construiește la nevoie | [Platformă](modules/platform.md) |
| Interfață | H4 Acasă cald acceptat; FW1 și PB1 fotografii estompate în antete implementate pentru feedback; Nunito/Fraunces și navigația Mai multe păstrate | [Interfață](modules/interface.md) |
| Căutare și drepturi asupra datelor | Căutare locală în Documente; căutarea globală, exportul și ștergerea sunt planificate | [Căutare și date](modules/data.md) |
| Operare | Pornire locală și configurații O2a pentru predare Linux; instalarea staging, CI și restaurarea rămân deschise | [Operare](modules/operations.md) |
| Aplicații mobile | Planificate după stabilizarea web; fără PWA offline sau aplicații native actuale | [Mobil](modules/mobile.md) |

## Surse

Codul și rutele actuale stabilesc ce există. README-ul principal explică versiunea locală. `Memoria_Familiei_Review_v1.2/` păstrează blueprintul și contractele pentru dezvoltarea viitoare; acestea nu sunt inventarul funcțiilor livrate.

Fișele păstrează numai deciziile și verificările utile modulului. Nu copia aici jurnale lungi, rezultate complete de teste, date de acces sau conținut privat.
