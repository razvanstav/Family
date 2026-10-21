import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  ArrowDownToLine,
  Check,
  ChevronDown,
  FileText,
  FolderHeart,
  HeartPulse,
  House,
  Image,
  LockKeyhole,
  Plus,
  Search,
  SlidersHorizontal,
  UploadCloud,
  Users,
  X,
  Clock3,
  AlertCircle,
  RefreshCw,
} from "lucide-react";
import {
  api,
  ApiError,
  visibilityLabel,
  type Document,
  type Family,
  type Member,
} from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { filterDocuments } from "./documentFilters";

const categoryPhotos: Record<string, string> = {
  personal: new URL("../../assets/categories/personal.webp", import.meta.url)
    .href,
  home: new URL("../../assets/categories/home.webp", import.meta.url).href,
  health: new URL("../../assets/categories/health.webp", import.meta.url).href,
  memories: new URL("../../assets/categories/memories.webp", import.meta.url)
    .href,
};

export const categories = [
  { id: "all", label: "Toate", icon: FolderHeart },
  { id: "personal", label: "Personale", icon: FileText },
  { id: "home", label: "Casă", icon: House },
  { id: "health", label: "Sănătate", icon: HeartPulse },
  { id: "memories", label: "Amintiri", icon: Image },
];
const categoryName = (id: string) =>
  categories.find((c) => c.id === id)?.label ?? id;
function FilePreview({ mime, category }: { mime: string; category: string }) {
  const Icon = mime === "application/pdf" ? FileText : Image;
  return (
    <div className={`file-preview color-${category}`} aria-hidden="true">
      <Icon size={36} strokeWidth={1.25} />
    </div>
  );
}

export function Documents({
  family,
  userId,
  onExpired,
}: {
  family: Family;
  userId: number;
  onExpired: () => void;
}) {
  const [docs, setDocs] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("all");
  const [privacy, setPrivacy] = useState("all");
  const [filterOpen, setFilterOpen] = useState(false);
  const [upload, setUpload] = useState(false);
  const [selected, setSelected] = useState<string | null>(null);
  const [notice, setNotice] = useState("");
  const endpoint = `/families/${family.id}`;
  const sequence = useRef(0);
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
        <div>
          <h1>Documente</h1>
        </div>
        <button
          className="button primary desktop-upload"
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
        <div className="categories" aria-label="Categorii">
          {categories
            .filter(({ id }) => id !== "all")
            .map(({ id, label, icon: Icon }) => (
              <button
                key={id}
                className={`category color-${id} ${category === id ? "selected" : ""}`}
                aria-pressed={category === id}
                onClick={() => setCategory(id)}
              >
                <img src={categoryPhotos[id]} alt="" width="768" height="512" />
                <span className="category-caption">
                  <Icon size={16} strokeWidth={1.7} aria-hidden="true" />
                  {label}
                </span>
                {category === id && (
                  <Check
                    className="category-check"
                    size={15}
                    aria-hidden="true"
                  />
                )}
              </button>
            ))}
        </div>
        <div className="section-header">
          <div>
            <h2 id="library-title">
              {loading || error
                ? "Documente"
                : `${visible.length} ${visible.length === 1 ? "document" : "documente"}`}
            </h2>
          </div>
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
              <button
                className="document-card"
                key={doc.id}
                aria-label={`${doc.title} · ${categoryName(doc.category)} · ${visibilityLabel[doc.visibility]}${doc.status === "quarantine" ? " · În verificare" : doc.status === "rejected" ? " · Respins" : ""}`}
                onClick={() => setSelected(doc.id)}
              >
                <div className="card-top">
                  <FilePreview mime={doc.mime} category={doc.category} />
                  <span className="category-label">
                    {categoryName(doc.category)}
                  </span>
                  <span
                    className="mini-lock"
                    aria-label={visibilityLabel[doc.visibility]}
                  >
                    {doc.visibility === "private" ? (
                      <LockKeyhole size={15} />
                    ) : (
                      <Users size={15} />
                    )}
                  </span>
                </div>
                <div className="card-bottom">
                  <h3 title={doc.title}>{doc.title}</h3>
                  {doc.status === "ready" ? (
                    <p>{doc.mime === "application/pdf" ? "PDF" : "Imagine"}</p>
                  ) : (
                    <p className={`card-status ${doc.status}`}>
                      {doc.status === "quarantine" ? (
                        <>
                          <Clock3 size={13} />
                          În verificare
                        </>
                      ) : (
                        <>
                          <AlertCircle size={13} />
                          Respins
                        </>
                      )}
                    </p>
                  )}
                </div>
              </button>
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
          PDF, JPG, PNG, WebP · maximum 10 MB/fișier și 100 MB/familie.
          Imagini de maximum 16 megapixeli.
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
  onClose,
  onChanged,
}: {
  id: string;
  endpoint: string;
  userId: number;
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
      title={sharing ? "Acces" : (doc?.title ?? "Document")}
      onClose={onClose}
      wide
    >
      {!doc && !error && <p className="loading-text">Se încarcă…</p>}
      {doc && !sharing && (
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
            <span className="avatar small">
              {doc.owner_name
                .split(" ")
                .map((n) => n[0])
                .slice(0, 2)
                .join("")}
            </span>
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
      {message && (
        <p className="notice" role="status">
          {message}
        </p>
      )}
    </Modal>
  );
}
