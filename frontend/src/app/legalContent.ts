export type LegalPageId =
  | "terms"
  | "privacy"
  | "cookies"
  | "refunds"
  | "contact"
  | "consumer";

type LegalSection = {
  heading: string;
  paragraphs: string[];
  links?: { label: string; href: string }[];
};

type LegalPage = {
  id: LegalPageId;
  title: string;
  sections: LegalSection[];
};

const company = "CENTRUL DE CODERI S.R.L.";
const address =
  "Strada Baracea nr. 102, sat Peretu, comuna Peretu, județul Teleorman, cod poștal 147240, România";
const contact = {
  label: "Trimite o solicitare",
  href: "?legal=contact#contact-form",
};

export const legalPages: LegalPage[] = [
  {
    id: "terms",
    title: "Termeni și condiții",
    sections: [
      {
        heading: "Furnizor",
        paragraphs: [
          `Amora este furnizată de ${company}, CUI 42281220, înregistrată la Registrul Comerțului cu nr. J34/102/2020, cu sediul în ${address}. Ne poți contacta prin formularul public sau prin corespondență la sediu.`,
          "Acești termeni descriu utilizarea aplicației. Informarea privind datele personale este prezentată separat în pagina Confidențialitate. Acceptarea termenilor nu înlocuiește un consimțământ cerut de lege pentru o anumită prelucrare.",
        ],
        links: [
          contact,
          { label: "Confidențialitate", href: "?legal=privacy" },
        ],
      },
      {
        heading: "Utilizarea aplicației",
        paragraphs: [
          "Amora permite organizarea informațiilor familiei: profiluri, documente, amintiri, mese și rețete, cheltuieli, abonamente evidențiate de utilizatori, vehicule, animale, sarcini, evenimente și remindere. Funcțiile disponibile sunt cele afișate în aplicație. Evidențierea unei cheltuieli sau a unui abonament nu execută o plată și nu încheie un contract cu furnizorul acelui serviciu.",
          "Primești un drept personal, neexclusiv de utilizare a aplicației pentru funcțiile sale. Nu încerca accesarea familiilor altor utilizatori, eludarea permisiunilor, compromiterea serviciului ori încărcarea de conținut ilegal. Datele de autentificare trebuie protejate; anunță-ne când suspectezi folosirea neautorizată a contului.",
        ],
      },
      {
        heading: "Conturi și familii",
        paragraphs: [
          "Folosește date reale și invitații pentru care ai autorizare. Rolul din familie stabilește acțiunile disponibile. Administratorul unei familii nu dobândește prin acest rol drepturi nelimitate asupra conturilor, documentelor private sau datelor celorlalți membri.",
          "Copiii sub 14 ani pot avea profil fără email și fără cont, administrat prin părinții desemnați. Acest prag al funcției nu este pragul consimțământului digital: când un serviciu este oferit direct unui copil și prelucrarea se bazează pe consimțământ, sub 16 ani este necesară autorizarea titularului răspunderii părintești, în condițiile legii. Simpla includere într-o familie nu dovedește această autorizare.",
        ],
      },
      {
        heading: "Conținut și acces",
        paragraphs: [
          "Păstrezi drepturile asupra conținutului încărcat. Ne autorizezi să îl stocăm, prelucrăm și afișăm în măsura necesară funcțiilor cerute și permisiunilor aplicabile; nu transferi proprietatea asupra fotografiilor sau documentelor către noi. Pentru datele altor persoane trebuie să ai autorizarea sau alt temei valabil pentru utilizarea și partajarea lor.",
          "Verifică accesul indicat înainte de salvare. Documentele Casă sunt accesibile automat adulților familiei, inclusiv celor cu acces limitat; Amintiri sunt pentru familie. Personale și Sănătate pornesc private. Asocierea unui document cu un vehicul sau animal nu lărgește accesul. Revocarea oprește accesul ulterior în aplicație, dar nu poate retrage copii descărcate anterior.",
        ],
      },
      {
        heading: "Disponibilitate și încetare",
        paragraphs: [
          "Reminderele sunt instrumente de organizare și depind de configurare și funcționarea canalului ales. Un email acceptat de serverul de trimitere nu confirmă livrarea în inbox. Păstrează separat originalele importante și verifică termenele care produc consecințe medicale, administrative sau financiare. Aceste precizări nu înlătură răspunderea legală a furnizorului.",
          "Putem restrânge accesul pentru o obligație legală, un incident de securitate ori o încălcare demonstrabilă a termenilor, proporțional cu situația. Poți cere explicații sau contestarea măsurii prin formular. Încetarea utilizării, părăsirea unei familii și ștergerea datelor sunt operații distincte; o solicitare este analizată și în raport cu drepturile celorlalte persoane.",
          "Modificările materiale ale serviciului sau termenilor vor fi comunicate adecvat. Legea română se aplică fără a înlătura protecția obligatorie a consumatorului din statul său de reședință, când aceasta este aplicabilă. Dreptul de a sesiza autoritățile și instanța competentă potrivit legii rămâne neafectat.",
        ],
        links: [
          { label: "Retragere și rambursări", href: "?legal=refunds" },
          {
            label: "Legea 365/2002 privind comerțul electronic",
            href: "https://legislatie.just.ro/Public/DetaliiDocumentAfis/288595",
          },
        ],
      },
    ],
  },
  {
    id: "privacy",
    title: "Confidențialitate",
    sections: [
      {
        heading: "Operator și contact",
        paragraphs: [
          `${company}, CUI 42281220, J34/102/2020, cu sediul în ${address}, gestionează prelucrarea datelor pentru furnizarea și administrarea Amora. Pentru întrebări sau exercitarea drepturilor, folosește formularul și selectează solicitarea privind datele personale. Poți trimite o cerere și prin poștă la sediu.`,
        ],
        links: [contact],
      },
      {
        heading: "Date prelucrate",
        paragraphs: [
          "Datele provin direct de la tine sau indirect de la membrii familiei, prin invitații, profiluri și conținutul introdus de aceștia. Prelucrăm datele de cont și profil furnizate, precum nume, email, parolă protejată prin mecanismele de autentificare, fotografie și apartenența la familii. În funcție de ce alegi să introduci, conținutul poate include relații de familie, date de naștere, documente, fotografii, informații de sănătate, evenimente, cheltuieli și datele celorlalte module. Un profil de copil poate exista fără cont și fără email.",
          "Pentru funcționare și securitate se prelucrează și informații tehnice despre cereri, sesiuni și evenimentele aplicației, inclusiv adresa IP și date de jurnalizare, după configurarea infrastructurii. Formularul public prelucrează tipul cererii, datele de contact și textul pe care le trimiți. Nu include parole, coduri de acces, copii de acte sau informații medicale în acel formular dacă nu sunt necesare solicitării.",
        ],
      },
      {
        heading: "Scopuri și temeiuri",
        paragraphs: [
          "Folosim datele necesare contului, funcțiilor solicitate și răspunsului la cereri pentru executarea relației de servicii sau a demersurilor cerute înaintea acesteia. Obligațiile legale pot impune păstrarea unor evidențe și răspunsul către autorități. Securitatea, prevenirea abuzului și apărarea drepturilor pot necesita prelucrare bazată pe interese legitime, cu evaluarea impactului asupra persoanelor. Amora nu folosește profilare și nu ia decizii exclusiv automatizate cu efecte juridice sau similare asupra ta. Nu vindem datele pentru publicitate.",
          "Datele de sănătate și alte categorii speciale sunt opționale și au protecție suplimentară. Prelucrarea lor necesită, pe lângă un temei general, o condiție valabilă din articolul 9 GDPR. Când se invocă un consimțământ explicit, acesta trebuie obținut separat de termeni, de la persoana vizată sau reprezentantul autorizat. Existența unui câmp, încărcarea unui document ori acceptarea acestor pagini nu reprezintă singure un asemenea consimțământ.",
          "Pentru informațiile despre altă persoană trebuie respectate autorizarea, informarea acesteia și temeiul aplicabil. Nu presupunem că un membru adult poate consimți pentru alt adult. Pentru copii contează titularul răspunderii părintești și condițiile legale; desemnarea unui părinte în interfață nu înlocuiește verificarea dreptului său de reprezentare.",
        ],
      },
      {
        heading: "Destinatari și permisiuni",
        paragraphs: [
          "Conținutul este afișat persoanelor autorizate potrivit familiei, rolului, categoriei și opțiunilor de acces. În Calendar, datele provin din module și păstrează accesul sursei. Invitarea individuală la un abonament nu oferă acces la restul familiei sursă sau familiei destinatarului. Datele de acces ale abonamentelor sunt păstrate criptat și afișate la cerere utilizatorilor autorizați.",
          "Furnizarea tehnică implică infrastructura de găzduire și Cloudflare pentru livrarea și protecția accesului la aplicație, inclusiv informații tehnice despre conexiuni. Când trimiterea prin email este activată și utilizată, Brevo primește datele necesare mesajului, precum destinatarul și conținutul notificării. Datele strict necesare pot fi accesate de persoanele autorizate pentru administrare, securitate și soluționarea cererilor sau comunicate autorităților când legea o impune. O solicitare din formular este păstrată criptat pentru examinare internă; trimiterea ei nu înseamnă că a fost expediat un email.",
          "Locul prelucrării și eventualele transferuri internaționale depind de furnizorii și configurația efectivă. Nu afirmăm că toate datele rămân exclusiv în România sau în Uniunea Europeană. Poți cere informații concrete despre destinatarii aplicabili datelor tale, localizare și garanțiile transferurilor, prin formularul privind datele personale.",
        ],
        links: [
          {
            label: "Cloudflare – confidențialitate",
            href: "https://www.cloudflare.com/privacypolicy/",
          },
          {
            label: "Cloudflare – clauze contractuale standard",
            href: "https://www.cloudflare.com/cloudflare-customer-scc/",
          },
          {
            label: "Brevo – confidențialitate",
            href: "https://www.brevo.com/legal/privacypolicy/",
          },
          {
            label: "Brevo – condiții și acord de prelucrare a datelor",
            href: "https://www.brevo.com/legal/termsofuse/",
          },
        ],
      },
      {
        heading: "Păstrare și drepturi",
        paragraphs: [
          "Datele sunt păstrate pentru scopurile pentru care au fost colectate și pentru obligațiile legale aplicabile. Arhivarea unui animal, vehicul sau a unei înregistrări păstrează istoricul; nu este ștergere. În absența unei ștergeri efectuate în modul sau a unei cereri soluționate, datele arhivate nu dispar automat după un termen fix. O cerere de ștergere necesită și analiza conținutului comun, obligațiilor legale și copiilor de siguranță.",
          "În condițiile GDPR poți solicita informare, acces și copie, rectificare, ștergere, restricționare, portabilitate și opoziție. Când temeiul este consimțământul, îl poți retrage fără a afecta prelucrarea legală anterioară. Cererile sunt examinate individual; nu promitem export sau ștergere instantanee. Putem cere informații proporționale pentru verificarea identității și protejarea datelor celorlalți membri.",
          "Răspundem fără întârzieri nejustificate, în principiu în cel mult o lună. Pentru complexitate sau număr mare de cereri, termenul poate fi prelungit cu încă două luni, cu informarea ta în prima lună. Exercitarea drepturilor este în principiu gratuită. Poți depune plângere la ANSPDCP și te poți adresa instanței, fără a fi obligat să aștepți soluționarea unei discuții amiabile cu noi.",
        ],
        links: [
          {
            label: "GDPR – Regulamentul UE 2016/679",
            href: "https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=ro",
          },
          { label: "ANSPDCP", href: "https://www.dataprotection.ro/" },
          contact,
        ],
      },
    ],
  },
  {
    id: "cookies",
    title: "Cookie-uri și stocare locală",
    sections: [
      {
        heading: "Stocare pentru autentificare",
        paragraphs: [
          "Amora folosește cookie-uri necesare sesiunii și protejării formularelor. Cookie-ul XSRF-TOKEN permite protecția împotriva cererilor trimise neautorizat în numele tău. Cookie-ul memoria_staging_session identifică sesiunea aplicației. În configurația curentă, durata lor este de două ore; cookie-urile sunt transmise prin HTTPS, cu Secure și SameSite=Lax.",
          "Aceste cookie-uri susțin serviciul solicitat și nu sunt folosite pentru publicitate. Blocarea lor poate împiedica autentificarea sau salvarea formularelor. Le poți șterge din setările browserului; ștergerea cookie-urilor nu șterge conținutul familiei de pe server și poate încheia sesiunea curentă.",
        ],
      },
      {
        heading: "Infrastructură și opțiuni",
        paragraphs: [
          "Infrastructura Cloudflare poate prelucra informații tehnice despre conexiuni și erori de rețea, inclusiv prin mecanismul NEL al browserului. Acesta privește raportarea funcționării rețelei; nu este prezentat ca funcție de analiză publicitară. Informațiile tehnice și rapoartele pot include date despre conexiune și resursa solicitată.",
          "Codul aplicației nu folosește în prezent localStorage pentru preferințe ori identificatori și nu include instrumente de analytics sau publicitate. Pentru stocarea strict necesară nu cerem un acord formal printr-un banner. Dacă introducem tehnologii opționale care necesită acord, acestea trebuie explicate și activate numai după consimțământul cerut de lege, cu posibilitate reală de refuz și retragere.",
          "Regulile privind accesul la dispozitiv se aplică și altor tehnologii decât cookie-urile. Faptul că o informație rămâne local sau că nu este personală nu exclude singur aceste reguli. Pentru întrebări despre configurația folosită la accesarea ta, trimite o solicitare prin Contact.",
        ],
        links: [
          {
            label: "Legea 506/2004, art. 4",
            href: "https://legislatie.just.ro/Public/DetaliiDocument/214211",
          },
          {
            label: "EDPB – domeniul tehnic al regulilor ePrivacy",
            href: "https://www.edpb.europa.eu/documents/guideline/guidelines-22023-on-technical-scope-of-art-53-of-eprivacy-directive_en",
          },
          contact,
        ],
      },
    ],
  },
  {
    id: "refunds",
    title: "Retragere și rambursări",
    sections: [
      {
        heading: "Situația actuală",
        paragraphs: [
          "Amora nu are în prezent un checkout și nu încasează plăți pentru abonamente proprii. Prin urmare, nu există o sumă plătită prin aplicație pentru accesul Amora care să fie rambursată. Sumele introduse în Cheltuieli sau Abonamente sunt evidențe ale utilizatorului; o eventuală restituire de la un furnizor terț se solicită acelui furnizor.",
        ],
      },
      {
        heading: "Dacă va exista o ofertă plătită",
        paragraphs: [
          "Orice ofertă plătită va necesita prezentarea și acceptarea separată a prețului total, duratei, reînnoirii, modalităților de încetare și drepturilor legale, înainte de comandă. Această pagină nu activează o plată și nu constituie acceptarea unei oferte viitoare. Pentru un plan Amora plătit, oferit și acceptat separat, nu acordăm rambursări comerciale pentru simpla neutilizare ori renunțarea după expirarea dreptului legal de retragere. Rămân neafectate toate rambursările și celelalte drepturi obligatorii prevăzute de lege.",
          "Dreptul legal de retragere de 14 zile, când este aplicabil, nu poate fi eliminat prin formula «plățile nu se returnează». Începerea accesului la un serviciu continuu nu echivalează automat cu prestarea lui integrală. O sumă proporțională pentru serviciul deja prestat poate fi datorată numai în condițiile legii, inclusiv cererea expresă de începere în perioada de retragere și informarea necesară.",
          "Excepția pentru conținut digital nelivrat pe suport material are condiții proprii: acord prealabil expres pentru începere, recunoașterea pierderii dreptului și confirmarea contractuală cerută de lege. Nu aplicăm automat această excepție oricărui serviciu online. Rămân protejate remediile obligatorii pentru nefurnizare sau neconformitate, inclusiv reducerea prețului, încetarea și rambursările datorate.",
        ],
      },
      {
        heading: "Transmiterea unei cereri",
        paragraphs: [
          "Poți trimite prin formular o cerere privind retragerea, încetarea sau o problemă de serviciu. Identifică relația contractuală, dacă există, și descrie solicitarea; nu transmite date de card. Poți păstra formularul tip de retragere pentru completare. Existența acestuia nu înseamnă că ai cumpărat acces Amora.",
          "Atunci când va exista un contract online pentru care se aplică dreptul de retragere, este necesară și funcția online prevăzută de lege, cu confirmare distinctă și dovadă pe suport durabil. Solicitările și eventualele rambursări sunt tratate potrivit drepturilor aplicabile, nu printr-o excludere generală a răspunderii.",
        ],
        links: [
          contact,
          {
            label: "OUG 34/2014 – drepturile consumatorilor",
            href: "https://legislatie.just.ro/Public/DetaliiDocument/158913",
          },
          {
            label: "OUG 141/2021 – servicii și conținut digital",
            href: "https://legislatie.just.ro/Public/DetaliiDocument/250054",
          },
          {
            label: "OUG 18/2026 – funcția online de retragere",
            href: "https://legislatie.just.ro/Public/DetaliiDocumentAfis/308474",
          },
        ],
      },
    ],
  },
  {
    id: "contact",
    title: "Contact",
    sections: [
      {
        heading: "Datele furnizorului",
        paragraphs: [
          `${company} · CUI 42281220 · Registrul Comerțului J34/102/2020. Sediu și adresă pentru corespondență: ${address}.`,
          "Formularul de mai jos este canalul electronic pentru suport, solicitări privind datele personale, retragere și sesizarea conținutului ilegal. Alege tipul potrivit și oferă informațiile necesare identificării problemei. Nu este necesar să ai cont pentru a trimite o solicitare.",
        ],
      },
      {
        heading: "Solicitări",
        paragraphs: [
          "O confirmare în formular arată înregistrarea cererii în sistemul intern; nu afirmă trimiterea sau livrarea unui email. Păstrează referința cererii pentru reveniri. Datele de contact furnizate sunt folosite pentru tratarea solicitării și răspuns, nu ca acord pentru mesaje comerciale.",
          "Pentru drepturi asupra datelor, precizează dreptul exercitat și datele sau contul la care te referi. Verificarea identității se face proporțional; nu atașa preventiv un act de identitate. Pentru conținut presupus ilegal, indică elementul precis, locul unde apare și motivele concrete; simpla nemulțumire față de un membru nu acordă acces la datele sale private.",
          "Cererile privind datele personale sunt soluționate, în principiu, în cel mult o lună, cu prelungirile motivate prevăzute de GDPR. Pentru celelalte solicitări, termenul depinde de natura problemei și de obligațiile legale aplicabile. Formularul nu este un canal pentru urgențe și nu oferă asistență medicală sau intervenție imediată.",
        ],
        links: [
          { label: "Cum sunt folosite datele cererii", href: "?legal=privacy" },
        ],
      },
    ],
  },
  {
    id: "consumer",
    title: "Protecția consumatorilor",
    sections: [
      {
        heading: "Reclamații și soluționare",
        paragraphs: [
          "Poți sesiza o problemă privind Amora prin formularul Contact sau prin corespondență la sediul furnizorului. Descrie situația, perioada și rezultatul solicitat. O discuție amiabilă cu noi nu înlătură dreptul de a sesiza autoritățile ori instanța competentă și nu reprezintă renunțarea la drepturile obligatorii ale consumatorului.",
          "Pentru protecția consumatorilor din România, consultă Autoritatea Națională pentru Protecția Consumatorilor. Mecanismul SAL urmărește soluționarea alternativă a litigiilor dintre consumatori și comercianți, potrivit competenței și condițiilor sale. Sesizarea SAL și reclamația administrativă către ANPC sunt proceduri distincte.",
          "Platforma europeană SOL/ODR a fost închisă la 20 iulie 2025 și nu mai primește reclamații. Pentru SAL din România folosește platforma actuală a ANPC. Pentru o plângere privind datele personale, autoritatea competentă este ANSPDCP; poți folosi direct canalul acesteia.",
        ],
        links: [
          contact,
          {
            label: "Protecția consumatorilor – ANPC",
            href: "https://anpc.ro/",
          },
          {
            label: "Soluționarea alternativă a litigiilor – SAL",
            href: "https://reclamatiisal.anpc.ro/",
          },
          {
            label: "Protecția datelor – ANSPDCP",
            href: "https://www.dataprotection.ro/",
          },
          {
            label: "Informarea oficială ANPC privind SAL, 19 mai 2026",
            href: "https://anpc.ro/comunicat-sal/",
          },
        ],
      },
    ],
  },
];
