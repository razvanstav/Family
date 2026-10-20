# Reguli de interfață pentru Memoria Familiei

Preferință explicită a utilizatorului, valabilă pentru întregul proiect și pentru toate dezvoltările viitoare:

- Folosește titluri, etichete și butoane directe, care numesc obiectul sau acțiunea: „Adaugă document”, „Salvează”, „Acces”.
- Nu adăuga mini-titluri, subtitluri decorative, sloganuri sau microdescrieri care repetă ce este deja evident din titlu, buton ori context.
- Evită formule precum „Un document de păstrat” și „Adaugă-l în spațiul familiei. Accesul începe cu tine.” în formulare și alte fluxuri funcționale.
- Adaugă text ajutător numai dacă oferă informație necesară pentru decizie sau utilizare: restricții reale, formate și limite, erori, stări ori consecințe importante privind accesul și revocarea. Spune informația concret, o singură dată, lângă controlul relevant.
- Nu elimina etichetele câmpurilor, numele accesibile ale controalelor sau explicațiile necesare despre permisiuni pentru a simplifica vizual interfața.
- Aplică regula implicit la fiecare pagină nouă sau modificată. Nu înlocui un subtitlu inutil cu altă formulare la fel de redundantă.

## Direcție vizuală

Referința actuală aleasă de utilizator este `artifacts/design-reference.png`. Înlocuiește vechiul design cu suprafețe pastel și ilustrații decorative.

- Folosește Helvetica, cu Helvetica Neue și Arial ca fallback. Nu include fonturi proprietare fără licență.
- Interfață de dashboard neutră: fundal alb, grupuri de conținut gri foarte deschis, carduri albe, borduri fine și umbre discrete.
- Sidebar simplu, breadcrumb, instrumente compacte și ierarhie clară. Nu copia grafice, statistici sau meniuri din referință fără funcționalitate reală în proiect.
- Colțuri moderate: aproximativ 8–12 px pentru controale și 12–18 px pentru panouri. Evită butoanele predominant ovale, cardurile pastel mari, bannerele promoționale și ilustrațiile de fișiere.
- Culoarea indică o stare, o selecție sau o acțiune importantă; nu colorează decorativ fiecare card. Iconuri fine, monocrome și aliniate consecvent.
- Păstrează aceeași direcție în autentificare, Documente, Familia mea, ferestre și paginile viitoare. Pe mobil, adaptează coloanele și navigația fără overflow și păstrează controale tactile de minimum 44 px.
- Stilurile comune și tokenurile sunt în `frontend/src/styles.css`; stilurile specifice rămân în module. Modifică regulile existente, nu suprapune teme care se contrazic.
