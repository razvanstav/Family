import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  ArrowDownToLine,
  ArrowRight,
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
  size,
  visibilityLabel,
  type Document,
  type Family,
  type Member,
} from "../../shared/api";
import { Modal } from "../../shared/Modal";

export const categories = [
  { id: "all", label: "Toate", icon: FolderHeart },
  { id: "personal", label: "Personale", icon: FileText },
  { id: "home", label: "Casă", icon: House },
  { id: "health", label: "Sănătate", icon: HeartPulse },
  { id: "memories", label: "Amintiri", icon: Image },
];
const categoryName = (id: string) =>
  categories.find((c) => c.id === id)?.label ?? id;
const colors: Record<string, string> = {
  home: "green",
  personal: "blue",
  health: "peach",
  memories: "yellow",
};

function Paper({
  category,
  large = false,
  mime = "application/pdf",
}: {
  category: string;
  large?: boolean;
  mime?: string;
}) {
  const Icon = categories.find((c) => c.id === category)?.icon ?? FileText;
  return (
    <div className={`paper-scene ${large ? "large" : ""}`} aria-hidden="true">
      <div className="orbit orbit-one" />
      <div className="orbit orbit-two" />
      <div className="paper paper-back" />
      <div className="paper paper-front">
        <span className="paper-stamp">
          <Icon strokeWidth={1.5} size={26} />
        </span>
        <span className="paper-line long" />
        <span className="paper-line" />
        <span className="paper-line short" />
        <span className="paper-seal">
          <Check size={14} />
        </span>
      </div>
      <span className="file-pill">
        {mime === "application/pdf" ? "PDF" : mime.split("/")[1]?.toUpperCase()}
      </span>
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
        e instanceof Error ? e.message : "Nu am putut încărca documentele.",
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
  // Recheck when returning to the tab; never persist private lists in local storage.
  useEffect(() => {
    const check = () => {
      if (document.visibilityState === "visible") void load();
    };
    document.addEventListener("visibilitychange", check);
    return () => document.removeEventListener("visibilitychange", check);
  }, [family.id]);
  const visible = docs.filter(
    (d) =>
      (category === "all" || d.category === category) &&
      (privacy === "all" || d.visibility === privacy) &&
      d.title.toLocaleLowerCase("ro").includes(search.toLocaleLowerCase("ro")),
  );
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
      <section className="intro-row">
        <div className="hero-card">
          <div className="hero-copy">
            <p className="upload-formats">PDF, JPG, PNG, WebP</p>
            <p>Maximum 10 MB / fișier</p>
            <button
              className="button primary"
              onClick={() => setUpload(true)}
              disabled={family.role === "limited"}
            >
              Adaugă document <ArrowRight size={17} />
            </button>
          </div>
          <div className="hero-art">
            <Paper category="memories" large />
          </div>
        </div>
      </section>
      <section className="library" aria-labelledby="library-title">
        <div className="category-heading">
          <h2>Categorii</h2>
          <button
            className="text-button"
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
          {categories.map(({ id, label, icon: Icon }) => (
            <button
              key={id}
              className={`category ${category === id ? "selected" : ""}`}
              aria-pressed={category === id}
              onClick={() => setCategory(id)}
            >
              <span>
                <Icon size={18} strokeWidth={1.7} />
              </span>
              {label}
            </button>
          ))}
        </div>
        <div className="section-header">
          <div>
            <h2 id="library-title">
              {visible.length} {visible.length === 1 ? "document" : "documente"}
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
        ) : visible.length === 0 ? (
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
                className={`document-card ${colors[doc.category]}`}
                key={doc.id}
                onClick={() => setSelected(doc.id)}
              >
                <div className="card-top">
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
                <Paper category={doc.category} mime={doc.mime} />
                <div className="card-bottom">
                  <h3>{doc.title}</h3>
                  <p>
                    {doc.mime === "application/pdf" ? "PDF" : "Imagine"}{" "}
                    <span>·</span> {size(doc.bytes)}
                  </p>
                  <div className="card-access">
                    <span>
                      {doc.status === "quarantine" ? (
                        <>
                          <Clock3 size={13} /> În verificare
                        </>
                      ) : doc.status === "rejected" ? (
                        <>
                          <AlertCircle size={13} /> Respins
                        </>
                      ) : (
                        <>
                          {doc.visibility === "private" ? (
                            <LockKeyhole size={13} />
                          ) : (
                            <Users size={13} />
                          )}
                          {visibilityLabel[doc.visibility]}
                        </>
                      )}
                    </span>
                    <span className="card-arrow">
                      <ArrowRight size={19} />
                    </span>
                  </div>
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
}: {
  endpoint: string;
  onClose: () => void;
  onSaved: (doc: Document) => void;
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
          <span>
            {file
              ? `${size(file.size)} · Apasă pentru a înlocui`
              : "PDF, JPG, PNG, WebP · maximum 10 MB"}
          </span>
        </button>
        <input
          ref={input}
          hidden
          type="file"
          accept=".pdf,.jpg,.jpeg,.png,.webp"
          onChange={(e) => choose(e.target.files?.[0])}
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
  const [members, setMembers] = useState<Member[]>([]);
  const [visibility, setVisibility] =
    useState<Document["visibility"]>("private");
  const [grants, setGrants] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [sharing, setSharing] = useState(false);
  const path = `${endpoint}/documents/${id}`;
  function assign(data: Document) {
    setDoc(data);
    setVisibility(data.visibility);
    setGrants(data.grants ?? []);
  }
  useEffect(() => {
    let active = true;
    api<Document>(path)
      .then((data) => {
        if (active) assign(data);
      })
      .catch(() => {
        if (active)
          setError("Documentul nu mai este disponibil sau nu mai ai acces.");
      });
    return () => {
      active = false;
    };
  }, [path]);
  async function openSharing() {
    setBusy(true);
    setError("");
    try {
      setMembers(await api<Member[]>(`${endpoint}/members`));
      setSharing(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Nu am putut încărca membrii.");
    } finally {
      setBusy(false);
    }
  }
  async function saveSharing(e: FormEvent) {
    e.preventDefault();
    if (!doc) return;
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
      setError(e instanceof Error ? e.message : "Nu am putut salva accesul.");
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
      if (e instanceof ApiError && [401, 404].includes(e.status)) {
        setDoc(null);
        onChanged();
      }
      setError(e instanceof Error ? e.message : "Descărcarea nu a reușit.");
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
          <div className={`detail-art ${colors[doc.category]}`}>
            <span className="category-label">{categoryName(doc.category)}</span>
            <Paper category={doc.category} mime={doc.mime} large />
            <span className="detail-badge">
              <LockKeyhole size={14} />
              {visibilityLabel[doc.visibility]}
            </span>
          </div>
          <p className="document-meta">
            {doc.mime === "application/pdf" ? "PDF" : "Imagine"} ·{" "}
            {size(doc.bytes)}
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
            <fieldset className="member-options">
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
            disabled={busy || (visibility === "selected" && !grants.length)}
          >
            {busy ? "Se salvează…" : "Salvează"}
          </button>
          <button
            className="text-button full center"
            type="button"
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
      {message && (
        <p className="notice" role="status">
          {message}
        </p>
      )}
    </Modal>
  );
}
