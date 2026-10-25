import { useEffect, useRef, useState, type FormEvent } from "react";
import { ArrowLeft, Download, Printer } from "lucide-react";
import { Brand } from "../shared/Brand";
import { api, errorMessage } from "../shared/api";
import { ActionButton } from "../shared/ActionButton";
import { FocusInput } from "../shared/FocusInput";
import { FeedbackMessage } from "../shared/FeedbackMessage";
import {
  publicRequestTypes,
  downloadText,
  type PublicRequestType,
} from "../shared/publicRequests";
import { legalPages, type LegalPageId } from "./legalContent";
import { LegalFooter } from "./LegalFooter";
import "./legal.css";

export function LegalPage({ id }: { id: LegalPageId }) {
  const page = legalPages.find((item) => item.id === id)!;
  useEffect(() => {
    document.title = `${page.title} · Amora`;
    if (window.location.hash !== "#contact-form") return;
    const frame = requestAnimationFrame(() => {
      document
        .getElementById("contact-form")
        ?.scrollIntoView({ block: "start" });
    });
    return () => cancelAnimationFrame(frame);
  }, [page.title]);
  return (
    <div className="legal-page">
      <header className="legal-header">
        <a href={window.location.pathname} aria-label="Amora — Autentificare">
          <Brand />
        </a>
        <a className="button secondary" href={window.location.pathname}>
          <ArrowLeft size={17} aria-hidden="true" /> Înapoi în aplicație
        </a>
      </header>
      <main className="legal-layout">
        <nav className="legal-navigation" aria-label="Pagini de informare">
          {legalPages.map((item) => (
            <a
              key={item.id}
              href={`?legal=${item.id}`}
              aria-current={item.id === id ? "page" : undefined}
            >
              {item.title}
            </a>
          ))}
        </nav>
        <article className="legal-article">
          <div className="legal-title">
            <h1>{page.title}</h1>
            <button
              type="button"
              className="icon-button"
              aria-label="Tipărește sau salvează ca PDF"
              onClick={() => window.print()}
            >
              <Printer size={19} />
            </button>
          </div>
          <p className="legal-date">Actualizat: 25 octombrie 2026</p>
          {page.sections.map((section, index) => (
            <section
              key={section.heading}
              aria-labelledby={`legal-section-${index}`}
            >
              <h2 id={`legal-section-${index}`}>{section.heading}</h2>
              {section.paragraphs.map((paragraph) => (
                <p key={paragraph}>{paragraph}</p>
              ))}
              {section.links && (
                <ul>
                  {section.links.map((link) => (
                    <li key={link.href}>
                      <a
                        href={link.href}
                        {...(link.href.startsWith("https:")
                          ? { target: "_blank", rel: "noopener noreferrer" }
                          : {})}
                      >
                        {link.label}
                      </a>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          ))}
          {id === "contact" && <PublicRequestForm />}
          {id === "refunds" && (
            <div className="legal-actions">
              <a
                className="button primary"
                href="?legal=contact&type=withdrawal#contact-form"
              >
                Trimite o solicitare
              </a>
              <button
                className="button secondary"
                onClick={() =>
                  downloadText(
                    "amora-formular-retragere.txt",
                    withdrawalTemplate,
                  )
                }
              >
                <Download size={17} aria-hidden="true" /> Descarcă formularul de
                retragere
              </button>
            </div>
          )}
          {id === "privacy" && (
            <div className="legal-actions">
              <a
                className="button primary"
                href="?legal=contact&type=privacy#contact-form"
              >
                Trimite o cerere privind datele
              </a>
            </div>
          )}
        </article>
      </main>
      <LegalFooter />
    </div>
  );
}

const withdrawalTemplate = `Către CENTRUL DE CODERI S.R.L., CUI 42281220, J34/102/2020\nStrada Baracea nr. 102, sat Peretu, comuna Peretu, Teleorman, 147240, România\n\nVă notific retragerea mea din contractul privind următorul serviciu:\nServiciul și numărul contractului/comenzii:\nData încheierii contractului:\nNumele consumatorului:\nAdresa consumatorului:\nEmail pentru răspuns:\nData:\nSemnătura (numai dacă formularul este transmis pe hârtie):\n\nFormularul este un model. O declarație neechivocă de retragere poate fi transmisă și în alt format. În prezent, Amora nu încasează plăți pentru acces prin aplicație.\n`;

function PublicRequestForm() {
  const requestedType = new URLSearchParams(window.location.search).get("type");
  const [type, setType] = useState<PublicRequestType>(
    requestedType && Object.hasOwn(publicRequestTypes, requestedType)
      ? (requestedType as PublicRequestType)
      : "support",
  );
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [reference, setReference] = useState("");
  const [receivedAt, setReceivedAt] = useState("");
  const submitting = useRef(false);
  async function submit(event: FormEvent) {
    event.preventDefault();
    if (submitting.current) return;
    submitting.current = true;
    setBusy(true);
    setError("");
    try {
      await api("/session");
      const result = await api<{ reference: string; created_at: string }>(
        "/public/requests",
        {
          method: "POST",
          body: JSON.stringify({
            type,
            name: name.trim(),
            email: email.trim(),
            subject: subject.trim(),
            message: message.trim(),
          }),
        },
      );
      setReceivedAt(result.created_at);
      setReference(result.reference);
    } catch (cause) {
      setError(errorMessage(cause));
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  }
  return (
    <section
      id="contact-form"
      className="legal-contact"
      aria-labelledby="contact-form-title"
    >
      <h2 id="contact-form-title">Trimite o solicitare</h2>
      {reference ? (
        <div role="status" className="notice">
          <p>
            Solicitarea a fost înregistrată: <strong>{reference}</strong>.
          </p>
          <p>
            Păstrează numărul. Formularul nu trimite automat o confirmare prin
            email.
          </p>
          <button
            className="text-button"
            onClick={() =>
              downloadText(
                `amora-${reference}.txt`,
                `Amora — solicitare înregistrată\nNumăr: ${reference}\nÎnregistrată la: ${receivedAt}\nTip: ${publicRequestTypes[type]}\nNume: ${name}\nEmail: ${email}\nSubiect: ${subject}\n\n${message}\n`,
              )
            }
          >
            <Download size={16} aria-hidden="true" /> Descarcă dovada
          </button>
        </div>
      ) : (
        <form onSubmit={submit}>
          <fieldset disabled={busy}>
            <label className="field">
              Tipul solicitării
              <select
                value={type}
                onChange={(event) =>
                  setType(event.target.value as PublicRequestType)
                }
              >
                {Object.entries(publicRequestTypes).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              Nume
              <FocusInput
                autoComplete="name"
                required
                maxLength={100}
                value={name}
                onChange={(event) => setName(event.target.value)}
              />
            </label>
            <label className="field">
              Adresa de email pentru răspuns
              <FocusInput
                type="email"
                autoComplete="email"
                required
                maxLength={254}
                value={email}
                onChange={(event) => setEmail(event.target.value)}
              />
            </label>
            <label className="field">
              Subiect
              <FocusInput
                required
                maxLength={160}
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
              />
            </label>
            <label className="field">
              Mesaj
              <textarea
                required
                maxLength={8000}
                rows={6}
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                aria-describedby="contact-data-hint"
              />
            </label>
            <p className="legal-form-hint" id="contact-data-hint">
              Nu trimite parole, coduri de acces, acte de identitate sau
              documente medicale. Pentru o cerere GDPR, precizează dreptul
              solicitat și contul vizat; verificarea identității se face
              separat, dacă este necesară.
            </p>
            <p className="legal-form-hint">
              Folosim datele pentru a răspunde solicitării, conform{" "}
              <a
                href="?legal=privacy"
                target="_blank"
                rel="noopener noreferrer"
              >
                Politicii de confidențialitate
              </a>
              . Trimiterea nu reprezintă acord pentru marketing.
            </p>
            {error && (
              <FeedbackMessage variant="error">{error}</FeedbackMessage>
            )}
            <ActionButton
              type="submit"
              className="button primary"
              loading={busy}
              loadingLabel="Se trimite…"
            >
              Trimite solicitarea
            </ActionButton>
          </fieldset>
        </form>
      )}
    </section>
  );
}
