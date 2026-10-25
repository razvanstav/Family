import { legalPages } from "./legalContent";
import "./legal.css";
const salImage = new URL("../assets/legal/anpc-sal.png", import.meta.url).href;

export function LegalFooter() {
  return (
    <footer className="legal-footer">
      <nav aria-label="Informații Amora">
        {legalPages.map((page) => (
          <a key={page.id} href={`?legal=${page.id}`}>
            {page.title}
          </a>
        ))}
      </nav>
      <div className="legal-footer-company">
        <p>CENTRUL DE CODERI S.R.L. · CUI 42281220 · J34/102/2020</p>
        <p>Strada Baracea nr. 102, Peretu, Teleorman, 147240, România</p>
      </div>
      <div className="legal-authorities">
        <a href="https://anpc.ro/" target="_blank" rel="noopener noreferrer">
          ANPC
        </a>
        <a
          href="https://reclamatiisal.anpc.ro/"
          target="_blank"
          rel="noopener noreferrer"
        >
          <img
            className="legal-sal"
            src={salImage}
            width="250"
            height="50"
            alt="ANPC — Soluționarea alternativă a litigiilor (SAL)"
          />
        </a>
      </div>
    </footer>
  );
}
