import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  ArrowDownToLine,
  Check,
  ChevronDown,
  FileText,
  FolderHeart,
  LockKeyhole,
  Plus,
  Search,
  SlidersHorizontal,
  UploadCloud,
  Users,
  X,
  Clock3,
  RefreshCw,
  CalendarDays,
  Pencil,
} from "lucide-react";
import {
  api,
  ApiError,
  initials,
  visibilityLabel,
  type Document,
  type Family,
  type Member,
  type DocumentRequest,
} from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { filterDocuments } from "./documentFilters";
import { ExpirationField } from "./ExpirationField";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import {
  categories,
  categoryName,
  FilePreview,
  DocumentCard,
  DocumentCategories,
} from "../../shared/DocumentCard";

export function Documents({
  family,
  userId,
  requestedAction,
  onActionOpened,
  onExpired,
}: {
  family: Family;
  userId: number;
  requestedAction: DocumentRequest | null;
  onActionOpened: () => void;
  onExpired: () => void;
}) {
  const [docs, setDocs] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState(requestedAction?.category ?? "all");
  const [privacy, setPrivacy] = useState("all");
  const [filterOpen, setFilterOpen] = useState(false);
  const [upload, setUpload] = useState(
    Boolean(requestedAction?.upload && family.role !== "limited"),
  );
  const [selected, setSelected] = useState<string | null>(
    requestedAction?.id ?? null,
  );
  const [notice, setNotice] = useState("");
  const endpoint = `/families/${family.id}`;
  const sequence = useRef(0);
  useEffect(() => {
    if (!requestedAction) return;
    setCategory(requestedAction.category ?? "all");
    setSelected(requestedAction.id ?? null);
    setUpload(Boolean(requestedAction.upload && family.role !== "limited"));
    onActionOpened();
  }, [requestedAction]);
  async function load() {
    const id = ++sequence.current;
    setLoading(true);
    setError("");
    try {
      const data = await api<Document[]>(`${endpoint}/documents`);
      if (id === sequence.current) setDocs(data);
    } catch (e) {
      if (id !== sequence.current) return;
      setDocs([]);
      if (e instanceof ApiError && [401, 419, 404].includes(e.status))
        onExpired();
      setError(
        e instanceof ApiError
          ? e.message
          : "Nu am putut încărca documentele. Verifică conexiunea și reîncearcă.",
      );
    } finally {
      if (id === sequence.current) setLoading(false);
    }
  }
  useEffect(() => {
    void load();
    return () => {
      sequence.current++;
    };
  }, [family.id]);
  useEffect(() => {
    if (family.role === "limited") setUpload(false);
  }, [family.role]);
  const visible = filterDocuments(docs, search, category, privacy);
  return (
    <div className="documents-page">
      <section className="page-heading">
        <h1>Documente</h1>
        <button
          className="button primary"
          aria-label="Adaugă document"
          onClick={() => setUpload(true)}
          disabled={family.role === "limited"}
        >
          <Plus size={21} /> <span>Adaugă document</span>
        </button>
      </section>
      <div className="library-tools">
        <div className="search">
          <Search size={20} />
          <input
            id="document-search"
            type="search"
            aria-label="Caută documente"
            placeholder="Caută un document..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          {search && (
            <button aria-label="Șterge căutarea" onClick={() => setSearch("")}>
              <X size={17} />
            </button>
          )}
        </div>
        <button
          className={`filter-button ${privacy !== "all" ? "active" : ""}`}
          aria-label="Filtrează după acces"
          aria-expanded={filterOpen}
          onClick={() => setFilterOpen(!filterOpen)}
        >
          <SlidersHorizontal size={18} />
          <span>Acces</span>
          <ChevronDown size={14} />
        </button>
      </div>
      {filterOpen && (
        <div className="filter-panel">
          <label htmlFor="privacy">Cine poate vedea</label>
          <select
            id="privacy"
            value={privacy}
            onChange={(e) => setPrivacy(e.target.value)}
          >
            <option value="all">Toate documentele</option>
            {Object.entries(visibilityLabel).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
      )}
      <section className="library" aria-labelledby="library-title">
        <div className="category-heading">
          <h2>Categorii</h2>
          <button
            className="text-button"
            aria-pressed={
              category === "all" && privacy === "all" && !search.trim()
            }
            onClick={() => {
              setCategory("all");
              setSearch("");
              setPrivacy("all");
            }}
          >
            Vezi toate
          </button>
        </div>
        <DocumentCategories selected={category} onSelect={setCategory} />
        <div className="section-header">
          <h2 id="library-title">
            {loading || error
              ? "Documente"
              : `${visible.length} ${visible.length === 1 ? "document" : "documente"}`}
          </h2>
          <button
            className="text-button"
            aria-label="Actualizează documentele"
            onClick={() => void load()}
            disabled={loading}
          >
            <RefreshCw size={15} />
            <span>Actualizează</span>
          </button>
        </div>
        {notice && (
          <div className="notice" role="status">
            <Check size={18} />
            {notice}
            <button
              aria-label="Închide notificarea"
              onClick={() => setNotice("")}
            >
              <X size={16} />
            </button>
          </div>
        )}
        {error && (
          <div className="error" role="alert">
            {error}
            <button className="text-button" onClick={() => void load()}>
              Reîncearcă
            </button>
          </div>
        )}
        {loading ? (
          <div className="document-grid" aria-label="Se încarcă documentele">
            {[1, 2, 3, 4].map((i) => (
              <div className="skeleton" key={i} />
            ))}
          </div>
        ) : error ? null : visible.length === 0 ? (
          <div className="empty">
            <FolderHeart size={40} strokeWidth={1.2} />
            <h3>{docs.length ? "Niciun rezultat" : "Niciun document"}</h3>
            <button
              className="button primary"
              disabled={!docs.length && family.role === "limited"}
              onClick={() =>
                docs.length
                  ? (setSearch(""), setCategory("all"), setPrivacy("all"))
                  : setUpload(true)
              }
            >
              {docs.length ? "Resetează filtrele" : "Adaugă document"}
            </button>
          </div>
        ) : (
          <div className="document-grid">
            {visible.map((doc) => (
              <DocumentCard document={doc} key={doc.id} onOpen={setSelected} />
            ))}
          </div>
        )}
      </section>
      {upload && (
        <UploadModal
          endpoint={endpoint}
          onClose={() => setUpload(false)}
          onAccessChanged={(status) => {
            setUpload(false);
            setSelected(null);
            if (status !== 403) setDocs([]);
            setNotice(
              status === 403
                ? "Nu mai ai permisiunea de a adăuga documente."
                : "Accesul la familie s-a schimbat.",
            );
            onExpired();
          }}
          onSaved={(doc) => {
            setUpload(false);
            setNotice(
              doc.status === "ready"
                ? "Document salvat. Acces: doar eu."
                : doc.status === "quarantine"
                  ? "Document salvat în carantină. Descărcarea rămâne blocată până la verificare."
                  : "Fișier respins la verificare. Conținutul a fost eliminat.",
            );
            void load();
          }}
        />
      )}
      {selected && (
        <Detail
          id={selected}
          endpoint={endpoint}
          userId={userId}
          canEdit={family.role !== "limited"}
          onClose={() => setSelected(null)}
          onChanged={() => void load()}
        />
      )}
    </div>
  );
}

function UploadModal({
  endpoint,
  onClose,
  onSaved,
  onAccessChanged,
}: {
  endpoint: string;
  onClose: () => void;
  onSaved: (doc: Document) => void;
  onAccessChanged: (status: number) => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState("personal");
  const [expiresOn, setExpiresOn] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const input = useRef<HTMLInputElement>(null);
  function choose(chosen?: File) {
    if (!chosen) return;
    if (
      chosen.size > 10 * 1024 * 1024 ||
      chosen.size === 0 ||
      !["application/pdf", "image/jpeg", "image/png", "image/webp"].includes(
        chosen.type,
      )
    ) {
      setFile(null);
      setError("Alege un PDF, JPG, PNG sau WebP de cel mult 10 MB.");
      return;
    }
    setFile(chosen);
    setError("");
    if (!title) setTitle(chosen.name.replace(/\.[^.]+$/, "").slice(0, 160));
  }
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!file || busy) return;
    setBusy(true);
    setError("");
    const data = new FormData();
    data.set("file", file);
    data.set("title", title);
    data.set("category", category);
    data.set("expires_on", expiresOn);
    try {
      onSaved(
        await api<Document>(`${endpoint}/documents`, {
          method: "POST",
          body: data,
        }),
      );
    } catch (e) {
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
        onAccessChanged(e.status);
        return;
      }
      setError(e instanceof Error ? e.message : "Uploadul nu a reușit.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal
      title="Adaugă document"
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      <form onSubmit={submit}>
        <button
          type="button"
          disabled={busy}
          className={`dropzone ${file ? "has-file" : ""}`}
          onClick={() => input.current?.click()}
          onDragOver={(e) => e.preventDefault()}
          onDrop={(e) => {
            e.preventDefault();
            if (!busy) choose(e.dataTransfer.files[0]);
          }}
        >
          {file ? (
            <FileText size={32} />
          ) : (
            <UploadCloud size={34} strokeWidth={1.4} />
          )}
          <strong>
            {file ? file.name : "Alege un fișier sau trage-l aici"}
          </strong>
          {file && <span>Apasă pentru a înlocui</span>}
        </button>
        <p className="upload-limits">
          PDF, JPG, PNG, WebP · maximum 10 MB/fișier și 100 MB/familie. Imagini
          de maximum 16 megapixeli.
        </p>
        <input
          ref={input}
          hidden
          type="file"
          accept=".pdf,.jpg,.jpeg,.png,.webp"
          onChange={(e) => {
            choose(e.target.files?.[0]);
            e.target.value = "";
          }}
        />
        <label className="field">
          Numele documentului
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            required
            maxLength={160}
            disabled={busy}
          />
        </label>
        <label className="field">
          Categorie
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            disabled={busy}
          >
            {categories
              .filter((c) => c.id !== "all")
              .map((c) => (
                <option key={c.id} value={c.id}>
                  {c.label}
                </option>
              ))}
          </select>
        </label>
        <ExpirationField
          value={expiresOn}
          onChange={setExpiresOn}
          disabled={busy}
        />
        <div className="privacy-hint">
          <LockKeyhole size={20} />
          <p>Acces inițial: doar eu.</p>
        </div>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <button
          className="button primary full"
          type="submit"
          disabled={!file || !title.trim() || busy}
        >
          {busy ? (
            <>
              <RefreshCw className="spin" size={18} /> Se încarcă și se
              verifică…
            </>
          ) : (
            <>
              <Plus size={18} /> Salvează
            </>
          )}
        </button>
      </form>
    </Modal>
  );
}

function Detail({
  id,
  endpoint,
  userId,
  canEdit,
  onClose,
  onChanged,
}: {
  id: string;
  endpoint: string;
  userId: number;
  canEdit: boolean;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [doc, setDoc] = useState<Document | null>(null);
  const [error, setError] = useState("");
  const [loadAttempt, setLoadAttempt] = useState(0);
  const [canRetryLoad, setCanRetryLoad] = useState(false);
  const [members, setMembers] = useState<Member[]>([]);
  const [visibility, setVisibility] =
    useState<Document["visibility"]>("private");
  const [grants, setGrants] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [sharing, setSharing] = useState(false);
  const [sharingStale, setSharingStale] = useState(false);
  const [editingExpiration, setEditingExpiration] = useState(false);
  const [expirationStale, setExpirationStale] = useState(false);
  const [expiresOn, setExpiresOn] = useState("");
  const path = `${endpoint}/documents/${id}`;
  function assign(data: Document) {
    setDoc(data);
    setVisibility(data.visibility);
    setGrants(data.grants ?? []);
  }
  useEffect(() => {
    let active = true;
    setDoc(null);
    setError("");
    setCanRetryLoad(false);
    api<Document>(path)
      .then((data) => {
        if (active) assign(data);
      })
      .catch((error) => {
        if (!active) return;
        const unavailable =
          error instanceof ApiError &&
          [401, 403, 404, 419].includes(error.status);
        setError(
          unavailable
            ? "Documentul nu mai este disponibil sau nu mai ai acces."
            : "Nu am putut încărca documentul. Verifică conexiunea și reîncearcă.",
        );
        setCanRetryLoad(!unavailable);
      });
    return () => {
      active = false;
    };
  }, [path, loadAttempt]);
  async function openSharing() {
    if (busy) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const [current, activeMembers] = await Promise.all([
        api<Document>(path),
        api<Member[]>(`${endpoint}/members`),
      ]);
      assign(current);
      setMembers(activeMembers);
      setSharingStale(false);
      setSharing(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Nu am putut încărca accesul.");
    } finally {
      setBusy(false);
    }
  }
  function openExpiration() {
    if (!doc || busy) return;
    setExpiresOn(doc.expires_on ?? "");
    setExpirationStale(false);
    setError("");
    setMessage("");
    setEditingExpiration(true);
  }
  async function reloadExpiration() {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      const current = await api<Document>(path);
      assign(current);
      setExpiresOn(current.expires_on ?? "");
      setExpirationStale(false);
    } catch (e) {
      handleExpirationError(e);
    } finally {
      setBusy(false);
    }
  }
  function handleExpirationError(e: unknown) {
    if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
      setDoc(null);
      setEditingExpiration(false);
      onChanged();
      setError(
        "Documentul nu mai este disponibil sau nu mai ai permisiunea de a-l modifica.",
      );
    } else if (e instanceof ApiError && e.status === 412) {
      setExpirationStale(true);
      setError("Documentul s-a schimbat. Reîncarcă-l înainte de a salva.");
    } else {
      setError(e instanceof Error ? e.message : "Nu am putut salva expirarea.");
    }
  }
  async function saveExpiration(e: FormEvent) {
    e.preventDefault();
    if (!doc || busy || expirationStale) return;
    setBusy(true);
    setError("");
    try {
      assign(
        await api<Document>(path, {
          method: "PATCH",
          headers: { "If-Match": `"${doc.version}"` },
          body: JSON.stringify({ expires_on: expiresOn || null }),
        }),
      );
      setEditingExpiration(false);
      setMessage("Data expirării a fost salvată.");
      onChanged();
    } catch (e) {
      handleExpirationError(e);
    } finally {
      setBusy(false);
    }
  }
  async function saveSharing(e: FormEvent) {
    e.preventDefault();
    if (!doc || busy || sharingStale) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      assign(
        await api<Document>(`${path}/sharing`, {
          method: "PUT",
          headers: { "If-Match": `"${doc.version}"` },
          body: JSON.stringify({
            visibility,
            membership_ids: visibility === "selected" ? grants : [],
          }),
        }),
      );
      setMessage("Acces actualizat.");
      setSharing(false);
      onChanged();
    } catch (e) {
      if (e instanceof ApiError && e.status === 412) {
        setSharingStale(true);
        setError(
          "Accesul documentului s-a schimbat. Reîncarcă setările înainte de a salva.",
        );
      } else {
        setError(e instanceof Error ? e.message : "Nu am putut salva accesul.");
      }
    } finally {
      setBusy(false);
    }
  }
  async function download() {
    setBusy(true);
    setError("");
    try {
      // Native navigation makes the server reauthorize every download; no cached blob or public URL.
      const fresh = await api<Document>(path);
      assign(fresh);
      if (fresh.status !== "ready")
        throw new Error("Fișierul nu a fost încă aprobat pentru descărcare.");
      window.location.assign(`/api/v1${path}/content`);
    } catch (e) {
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
        setDoc(null);
        onChanged();
        setError("Documentul nu mai este disponibil sau nu mai ai acces.");
      } else {
        setError(e instanceof Error ? e.message : "Descărcarea nu a reușit.");
      }
    } finally {
      setBusy(false);
    }
  }
  async function retry() {
    setBusy(true);
    setError("");
    try {
      const data = await api<Document>(`${path}/scan`, { method: "POST" });
      assign(data);
      setMessage(
        data.status === "quarantine"
          ? "Verificarea nu este disponibilă. Descărcarea rămâne blocată."
          : data.status === "ready"
            ? "Verificare încheiată. Fișierul poate fi descărcat."
            : "Fișier respins. Conținutul a fost eliminat.",
      );
      onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Verificarea nu a reușit.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal
      title={
        sharing
          ? "Acces"
          : editingExpiration
            ? "Expirare"
            : (doc?.title ?? "Document")
      }
      onClose={() => {
        if (!busy) onClose();
      }}
      wide
    >
      {!doc && !error && <p className="loading-text">Se încarcă…</p>}
      {doc && !sharing && !editingExpiration && (
        <>
          <div className="detail-art">
            <span className="category-label">{categoryName(doc.category)}</span>
            <FilePreview mime={doc.mime} category={doc.category} />
            <span className="detail-badge">
              <LockKeyhole size={14} />
              {visibilityLabel[doc.visibility]}
            </span>
          </div>
          <p className="document-meta">
            {doc.mime === "application/pdf" ? "PDF" : "Imagine"}
          </p>
          <div className="owner-row">
            <span className="avatar small">{initials(doc.owner_name)}</span>
            <div>
              <strong>{doc.owner_name}</strong>
              <p>Proprietar</p>
            </div>
            <span className="date">
              {new Date(doc.created_at).toLocaleDateString("ro-RO", {
                day: "numeric",
                month: "short",
                year: "numeric",
              })}
            </span>
          </div>
          <div className="document-expiration">
            <CalendarDays size={21} aria-hidden="true" />
            <dl>
              <dt>
                {doc.expires_on
                  ? doc.expires_on < bucharestToday()
                    ? "Expirat la"
                    : "Expiră la"
                  : "Expirare"}
              </dt>
              <dd>
                {doc.expires_on ? (
                  <time dateTime={doc.expires_on}>
                    {dateLabel(doc.expires_on, {
                      day: "numeric",
                      month: "long",
                      year: "numeric",
                    })}
                  </time>
                ) : (
                  "Nespecificată"
                )}
              </dd>
            </dl>
            {doc.owner_id === userId && canEdit && (
              <button
                className="icon-button"
                aria-label="Modifică data expirării"
                disabled={busy}
                onClick={openExpiration}
              >
                <Pencil size={18} aria-hidden="true" />
              </button>
            )}
          </div>
          {doc.status !== "ready" && (
            <div className="status-box">
              <Clock3 size={22} />
              <div>
                <strong>
                  {doc.status === "quarantine"
                    ? "În așteptarea verificării"
                    : "Fișier respins"}
                </strong>
                <p>
                  {doc.status === "quarantine"
                    ? "Descărcarea este blocată până la verificare."
                    : "Conținutul nu a trecut verificările și a fost eliminat."}
                </p>
              </div>
            </div>
          )}
          <div className="detail-actions">
            <button
              className="button primary"
              disabled={busy || doc.status !== "ready"}
              onClick={() => void download()}
            >
              <ArrowDownToLine size={18} /> Descarcă
            </button>
            {doc.owner_id === userId && (
              <button
                className="button secondary"
                disabled={busy}
                onClick={() => void openSharing()}
              >
                <Users size={18} /> Gestionează accesul
              </button>
            )}
          </div>
          {doc.owner_id === userId && doc.status === "quarantine" && (
            <button
              className="text-button retry-scan"
              onClick={() => void retry()}
              disabled={busy}
            >
              <RefreshCw size={15} /> Reîncearcă verificarea
            </button>
          )}
        </>
      )}
      {doc && editingExpiration && (
        <form onSubmit={saveExpiration}>
          <p className="modal-lead">{doc.title}</p>
          <ExpirationField
            value={expiresOn}
            onChange={setExpiresOn}
            disabled={busy || expirationStale}
            autoFocus
          />
          <button
            className="button primary full"
            disabled={busy || expirationStale}
          >
            {busy ? "Se salvează…" : "Salvează"}
          </button>
          <button
            className="text-button full center"
            type="button"
            disabled={busy}
            onClick={() => {
              setEditingExpiration(false);
              setError("");
            }}
          >
            Înapoi la document
          </button>
        </form>
      )}
      {doc && sharing && (
        <form onSubmit={saveSharing}>
          <p className="modal-lead">{doc.title}</p>
          <div className="access-options">
            {(["private", "family", "selected"] as const).map((value) => (
              <label
                key={value}
                className={`access-option ${visibility === value ? "chosen" : ""}`}
              >
                <input
                  type="radio"
                  name="visibility"
                  disabled={busy || sharingStale}
                  checked={visibility === value}
                  onChange={() => setVisibility(value)}
                />
                <span>
                  {value === "private" ? (
                    <LockKeyhole size={21} />
                  ) : (
                    <Users size={21} />
                  )}
                </span>
                <div>
                  <strong>{visibilityLabel[value]}</strong>
                  {value === "private" && doc.visibility !== "private" && (
                    <p>Retrage accesul celorlalți.</p>
                  )}
                  {value === "family" && <p>Toți membrii activi.</p>}
                </div>
              </label>
            ))}
          </div>
          {visibility === "selected" && (
            <fieldset
              className="member-options"
              disabled={busy || sharingStale}
            >
              <legend>Alege persoanele</legend>
              {!members.some((m) => m.user_id !== userId) && (
                <p>Niciun alt membru activ.</p>
              )}
              {members
                .filter((m) => m.user_id !== userId)
                .map((m) => (
                  <label key={m.id}>
                    <input
                      type="checkbox"
                      checked={grants.includes(m.id)}
                      onChange={(e) =>
                        setGrants(
                          e.target.checked
                            ? [...grants, m.id]
                            : grants.filter((id) => id !== m.id),
                        )
                      }
                    />
                    <span className="avatar small">{m.name[0]}</span>
                    {m.name}
                  </label>
                ))}
            </fieldset>
          )}
          <p className="fine-print">
            Revocarea blochează accesările viitoare. Copiile deja descărcate nu
            pot fi retrase.
          </p>
          <button
            className="button primary full"
            disabled={
              busy ||
              sharingStale ||
              (visibility === "selected" && !grants.length)
            }
          >
            {busy && !sharingStale ? "Se salvează…" : "Salvează"}
          </button>
          <button
            className="text-button full center"
            type="button"
            disabled={busy}
            onClick={() => setSharing(false)}
          >
            Înapoi la document
          </button>
        </form>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {!doc && canRetryLoad && (
        <button
          className="button secondary full"
          onClick={() => setLoadAttempt((attempt) => attempt + 1)}
        >
          Reîncearcă
        </button>
      )}
      {sharing && sharingStale && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => void openSharing()}
        >
          {busy ? "Se încarcă…" : "Reîncarcă accesul"}
        </button>
      )}
      {editingExpiration && expirationStale && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => void reloadExpiration()}
        >
          {busy ? "Se încarcă…" : "Reîncarcă documentul"}
        </button>
      )}
      {message && (
        <p className="notice" role="status">
          {message}
        </p>
      )}
    </Modal>
  );
}
