import React from "react";
import ReactDOM from "react-dom/client";
import App from "./app/App";
import { LegalPage } from "./app/LegalPage";
import { legalPages } from "./app/legalContent";
import "./styles.css";
import "./modules/documents/documents.css";
import "./modules/families/family.css";

const legalId = new URLSearchParams(window.location.search).get("legal");
const legalPage = legalPages.find((page) => page.id === legalId);

ReactDOM.createRoot(document.getElementById("root")!).render(
  <React.StrictMode>
    {legalPage ? <LegalPage id={legalPage.id} /> : <App />}
  </React.StrictMode>,
);
