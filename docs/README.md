# Modulele aplicației

La reluare pornește din [STATE.md](STATE.md). Ordinea etapelor și criteriile comune sunt în [ROADMAP.md](ROADMAP.md). Citește numai fișa necesară pasului activ.

## Module de produs

| Modul      | Situație la planificare                                                                                                                                                | Fișă                              |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| Familie    | FS1: profil cu cronologie, realizări și arbore genealogic cu rude fără cont, verificat local; așteaptă feedback                                                        | [Familie](modules/families.md)    |
| Cheltuieli | CH1 implementat și verificat local: pagină principală cu adăugare rapidă/evidență lunară, Familie/Personale, exemple Popescu și calendar românesc; CH2 verificat local | [Cheltuieli](modules/expenses.md) |
| Sarcini    | S1 acceptat; S2 verificat; SC1 Sarcini în Calendar cu deschidere directă, acces păstrat și verificări desktop/mobil; așteaptă feedback                                 | [Sarcini](modules/tasks.md)       |
| Documente  | În Familia mea → Documente; dosare pe persoană și comune, cu upload/acces/descărcare existente                                                                         | [Documente](modules/documents.md) |
| Bucătărie  | Rețete, versiuni, mese, vot și acces implementate; KH1 mobil pregătit pentru feedback                                                                                  | [Bucătărie](modules/kitchen.md)   |
| Amintiri   | MH2 album pe ani și În ziua asta implementat și verificat local, așteaptă feedback; acceptarea AM1–AM3 rămâne deschisă                                                 | [Amintiri](modules/memory.md)     |
| Calendar   | C1/C3/CH1 existente; VC1 scadențe Auto și BC1 aniversări cu activare explicită și reminder personal, verificate local                                                  | [Calendar](modules/calendar.md)   |
| Animale    | Planificat; fără implementare de produs                                                                                                                                | [Animale](modules/pets.md)        |
| Vehicule   | Auto V1a existent; VC1 leagă scadențele de Calendar și notificări in-app, verificat local                                                                              | [Vehicule](modules/vehicles.md)   |

## Servicii și funcții comune

| Zonă                               | Situație la planificare                                                                                                                                       | Fișă                                 |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------ |
| Autentificare                      | Creare cont directă, familie nouă sau cod de invitație, sesiune/CSRF/login/logout și Contul meu; recuperarea parolei rămâne planificată                       | [Autentificare](modules/identity.md) |
| Platformă                          | P1a: cuplările existente separate prin compoziție/contracte și gărzi de arhitectură, verificat local; izolarea, media, antivirusul și auditul rămân existente | [Platformă](modules/platform.md)     |
| Interfață                          | H4 Acasă cald acceptat; FW1 și PB1 fotografii estompate în antete implementate pentru feedback; Nunito/Fraunces și navigația Mai multe păstrate               | [Interfață](modules/interface.md)    |
| Căutare și drepturi asupra datelor | CD1 căutare comună autorizată în Amintiri/Rețete/Documente, verificată local; exportul și ștergerea rămân planificate                                         | [Căutare și date](modules/data.md)   |
| Operare                            | Pornire locală și configurații O2a pentru predare Linux; instalarea staging, CI și restaurarea rămân deschise                                                 | [Operare](modules/operations.md)     |
| Aplicații mobile                   | Planificate după stabilizarea web; fără PWA offline sau aplicații native actuale                                                                              | [Mobil](modules/mobile.md)           |

## Surse

Codul și rutele actuale stabilesc ce există. README-ul principal explică versiunea locală. `Memoria_Familiei_Review_v1.2/` păstrează blueprintul și contractele pentru dezvoltarea viitoare; acestea nu sunt inventarul funcțiilor livrate.

Fișele păstrează numai deciziile și verificările utile modulului. Nu copia aici jurnale lungi, rezultate complete de teste, date de acces sau conținut privat.
