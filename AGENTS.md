# Reguli de interfață pentru Memoria Familiei

Preferință explicită a utilizatorului, valabilă pentru întregul proiect și pentru toate dezvoltările viitoare:

- Folosește titluri, etichete și butoane directe, care numesc obiectul sau acțiunea: „Adaugă document”, „Salvează”, „Acces”.
- Nu adăuga mini-titluri, subtitluri decorative, sloganuri sau microdescrieri care repetă ce este deja evident din titlu, buton ori context.
- Evită formule precum „Un document de păstrat” și „Adaugă-l în spațiul familiei. Accesul începe cu tine.” în formulare și alte fluxuri funcționale.
- Adaugă text ajutător numai dacă oferă informație necesară pentru decizie sau utilizare: restricții reale, formate și limite, erori, stări ori consecințe importante privind accesul și revocarea. Spune informația concret, o singură dată, lângă controlul relevant.
- Nu elimina etichetele câmpurilor, numele accesibile ale controalelor sau explicațiile necesare despre permisiuni pentru a simplifica vizual interfața.
- Aplică regula implicit la fiecare pagină nouă sau modificată. Nu înlocui un subtitlu inutil cu altă formulare la fel de redundantă.

## Direcție vizuală

Referința actuală aleasă de utilizator este `artifacts/design-reference-warm.png`: interfață caldă de familie, fundal crem, carduri deschise, verde închis și iconițe colorate pe suporturi discrete. Referința precedentă `artifacts/design-reference.png` rămâne utilă pentru structura desktop.

- Folosește Helvetica, cu Helvetica Neue și Arial ca fallback. Nu include fonturi proprietare fără licență.
- Fundal alb cald/crem, carduri albe calde, borduri fine și umbre discrete. Verde închis pentru acțiunile principale și selecții; folosește accente de culoare pe iconițe, fără suprafețe pastel mari.
- Sidebar simplu, breadcrumb, instrumente compacte și ierarhie clară. Nu copia grafice, statistici sau meniuri din referință fără funcționalitate reală în proiect.
- Senzație naturală inspirată de iOS: carduri cu colțuri de 18–22 px, controale de 12–14 px, separatoare discrete, spațiere generoasă și text lizibil. Păstrează paleta caldă și Helvetica. Fără carduri pastel mari, bannere promoționale sau ilustrații de fișiere.
- Mișcare discretă și consecventă: feedback la apăsare, tranziții de 140–280 ms cu decelerare, ferestre ca foi care urcă pe mobil. Nu anima fiecare card la filtrare și nu adăuga gesturi sugerate vizual fără funcționalitate. Respectă `prefers-reduced-motion`, focusul și zonele sigure ale ecranului.
- Cardurile de documente sunt compacte, apropiate de pătrat pe mobil (circa 156 px înălțime la două coloane), cu iconița și categoria pe același rând. Nu le lungi prin spații verticale mari sau prin repetarea accesului în footer. Păstrează stările de verificare/respins vizibile și titlul complet accesibil în detaliu și cititorului de ecran.
- Cardurile membrilor sunt rânduri compacte de aproximativ 80–90 px: avatar colorat discret, nume, rol cu iconiță colorată și un singur buton de administrare de minimum 44 px. Mută schimbarea rolului și revocarea în fereastra de administrare; nu adăuga un footer care înalță doar unele carduri.
- Folosește iconuri colorate, fine și aliniate consecvent: albastru pentru familie, ocru pentru documente, violet pentru acces limitat, roz pentru sănătate. Păstrează aceeași culoare pentru același sens în tot proiectul; panourile și cardurile rămân neutre.
- Păstrează aceeași direcție în autentificare, Documente, Familia mea, ferestre și paginile viitoare. Pe mobil, adaptează coloanele și navigația fără overflow și păstrează controale tactile de minimum 44 px.
- Stilurile comune și tokenurile sunt în `frontend/src/styles.css`; stilurile specifice rămân în module. Modifică regulile existente, nu suprapune teme care se contrazic.

## Lucru modular și reluare

- La „continuăm de unde am rămas”, citește mai întâi `docs/STATE.md`, apoi numai fișa modulului și pasului indicate acolo. `docs/README.md` este indexul; `docs/ROADMAP.md` se citește când alegi sau schimbi etapa, nu la fiecare sarcină.
- Lucrează la un singur pas dintr-un singur modul. Include dependențele strict necesare; nu începe automat alte module. Instrucțiunile noi ale utilizatorului actualizează prioritatea salvată.
- Fișele din `docs/modules/` dețin scopul, pașii și acceptarea. `docs/STATE.md` deține exclusiv punctul de reluare: modul, pas, rezultat, verificări, blocaje și următoarea acțiune. Actualizează-le după fiecare pas semnificativ și înainte de încheierea lucrului sau schimbarea modulului.
- Separă „planificat”, „în lucru”, „implementat” și „verificat”. Marchează acceptarea numai după verificări observate; consemnează ce nu a fost verificat și nu inventa rezultate din istoricul Git.
- Citește codul, rutele și testele relevante înainte de modificare. Fișele sunt orientare, nu înlocuiesc implementarea actuală. Respectă și instrucțiunile locale aplicabile căilor modificate.
- Nu reciti implicit tot `README.md`, toate fișele sau întregul blueprint `Memoria_Familiei_Review_v1.2`. Consultă numai secțiunea ori contractul necesar pasului curent. Blueprintul descrie și funcții neimplementate.
- Limitează căutările la sursele relevante. Nu enumera recursiv `.tools/`, `.runtime/`, `vendor/`, `node_modules/`, builduri, fișiere private sau capturi. Pentru un fișier mare folosește căutări și fragmente, nu afișarea integrală.
- Verifică proporțional cu schimbarea: teste relevante pentru comportament, build pentru UI funcțional și verificare vizuală pentru layout. Pentru documentație verifică legăturile, coerența și `git diff --check`; nu porni întreaga aplicație fără motiv.
- Pașii de reluare se păstrează în repository. Nu crea notificări sau automatizări programate pentru această evidență fără o cerere nouă a utilizatorului.
