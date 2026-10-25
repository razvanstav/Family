import {
  useEffect,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import {
  ArrowDownToLine,
  Check,
  ChevronDown,
  FolderHeart,
  LockKeyhole,
  Plus,
  Search,
  SlidersHorizontal,
  Users,
  UploadCloud,
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
  shareableMembers,
  familyDocumentCategories,
  personalDocumentCategories,
  type Document,
  type DocumentDetail,
  type Family,
  type Member,
} from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { filterDocuments } from "./documentFilters";
import { ExpirationField } from "./ExpirationField";
import { DocumentFileField } from "./DocumentFileField";
import { documentFileType } from "../../shared/documentFiles";
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
  member,
  canCreate = member === undefined,
  navigation,
  requestedDocument = null,
  initialDocument,
  onDocumentOpened,
  onExpired,
}: {
  family: Family;
  userId: number;
  member?: Member;
  canCreate?: boolean;
  navigation?: ReactNode;
  requestedDocument?: string | null;
  initialDocument?: DocumentDetail;
  onDocumentOpened?: () => void;
  onExpired: () => void;
}) {
  const categoryIds = member
    ? personalDocumentCategories
    : familyDocumentCategories;
  const [docs, setDocs] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("all");
  const [privacy, setPrivacy] = useState("all");
  const [filterOpen, setFilterOpen] = useState(false);
  const [upload, setUpload] = useState(false);
  const [selected, setSelected] = useState<{
    id: string;
    document: DocumentDetail | null;
  } | null>(null);
  useEffect(() => {
    if (!requestedDocument) return;
    setSelected({
      id: requestedDocument,
      document:
        initialDocument?.id === requestedDocument ? initialDocument : null,
    });
    onDocumentOpened?.();
  }, [requestedDocument]);
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
    if (family.role === "limited" || !canCreate) setUpload(false);
  }, [family.role, canCreate]);
  const collectionDocs = docs.filter(
    (doc) =>
      categoryIds.includes(doc.category) &&
      (!member || doc.subject_membership_id === member.id),
  );
  const visible = filterDocuments(collectionDocs, search, category, privacy);
  const canAddToCollection = canCreate;
  const Heading = member ? "h2" : "h1";
  const heading = (
    <section className={member ? "section-header" : "page-heading"}>
      <Heading>{member ? "Documente" : "Familia mea"}</Heading>
      {canAddToCollection && (
        <button
          className="button primary"
          aria-label="Adaugă document"
          onClick={() => setUpload(true)}
          disabled={family.role === "limited"}
        >
          <Plus size={21} /> <span>Adaugă document</span>
        </button>
      )}
    </section>
  );
  return (
    <div className="documents-page">
      {member ? heading : <PageBackdrop image="family">{heading}</PageBackdrop>}
      {navigation}
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
        {member && (
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
        )}
      </div>
      {member && filterOpen && (
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
        <DocumentCategories
          selected={category}
          onSelect={setCategory}
          categoryIds={categoryIds}
        />
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
            <h3>
              {collectionDocs.length ? "Niciun rezultat" : "Niciun document"}
            </h3>
            {(collectionDocs.length > 0 || canAddToCollection) && (
              <button
                className="button primary"
                disabled={!collectionDocs.length && family.role === "limited"}
                onClick={() =>
                  collectionDocs.length
                    ? (setSearch(""), setCategory("all"), setPrivacy("all"))
                    : setUpload(true)
                }
              >
                {collectionDocs.length
                  ? "Resetează filtrele"
                  : "Adaugă document"}
              </button>
            )}
          </div>
        ) : (
          <div className="document-grid">
            {visible.map((doc) => (
              <DocumentCard
                document={doc}
                key={doc.id}
                onOpen={(id) => setSelected({ id, document: null })}
              />
            ))}
          </div>
        )}
      </section>
      {upload && (
        <DocumentCreateDialog
          endpoint={endpoint}
          categoryIds={categoryIds}
          initialCategory={category}
          subjectId={member?.id}
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
              doc.status === null || doc.status === "ready"
                ? `Document salvat. Acces: ${visibilityLabel[doc.visibility]}.`
                : doc.status === "quarantine"
                  ? "Document salvat în carantină. Descărcarea rămâne blocată până la verificare."
                  : "Fișier respins la verificare. Conținutul a fost eliminat.",
            );
            void load();
          }}
        />
      )}
      {selected && (
        <DocumentDetailDialog
          key={selected.id}
          id={selected.id}
          initialDocument={selected.document}
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

export function DocumentCreateDialog({
  endpoint,
  categoryIds,
  initialCategory,
  subjectId,
  onClose,
  onSaved,
  onAccessChanged,
}: {
  endpoint: string;
  categoryIds: readonly string[];
  initialCategory: string;
  subjectId?: string;
  onClose: () => void;
  onSaved: (doc: Document) => void | Promise<void>;
  onAccessChanged: (status: number) => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState(
    categoryIds.includes(initialCategory) ? initialCategory : categoryIds[0],
  );
  const [expiresOn, setExpiresOn] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [created, setCreated] = useState<Document | null>(null);
  const submitting = useRef(false);
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!title.trim() || submitting.current) return;
    submitting.current = true;
    setBusy(true);
    setError("");
    const data = new FormData();
    if (file) data.set("file", file);
    data.set("title", title.trim());
    data.set("category", category);
    if (subjectId) data.set("subject_membership_id", subjectId);
    data.set("expires_on", expiresOn);
    let document = created;
    try {
      if (!document) {
        document = await api<Document>(`${endpoint}/documents`, {
          method: "POST",
          body: data,
        });
        setCreated(document);
      }
      await onSaved(document);
    } catch (e) {
      if (
        !document &&
        e instanceof ApiError &&
        [401, 403, 404, 419].includes(e.status)
      ) {
        onAccessChanged(e.status);
        return;
      }
      setError(
        document
          ? "Documentul este salvat în dosar, dar nu a putut fi adăugat aici. Reîncearcă."
          : e instanceof Error
            ? e.message
            : "Nu am putut salva documentul.",
      );
    } finally {
      submitting.current = false;
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
        <label className="field">
          Numele documentului
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            required
            maxLength={160}
            disabled={busy || !!created}
          />
        </label>
        <label className="field">
          Categorie
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            disabled={busy || !!created}
          >
            {categories
              .filter((c) => categoryIds.includes(c.id))
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
          disabled={busy || !!created}
        />
        <DocumentFileField
          file={file}
          onChange={(chosen) => {
            setFile(chosen);
            if (chosen && !title)
              setTitle(chosen.name.replace(/\.[^.]+$/, "").slice(0, 160));
          }}
          onError={setError}
          disabled={busy || !!created}
          optional
        />
        <div className="privacy-hint">
          {familyDocumentCategories.includes(category) ? (
            <Users size={20} />
          ) : (
            <LockKeyhole size={20} />
          )}
          <p>
            {category === "home"
              ? "Acces automat: adulții familiei, inclusiv cei cu acces limitat."
              : category === "memories"
                ? "Acces automat: toți membrii familiei."
                : "Acces inițial: doar eu."}
          </p>
        </div>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <button
          className="button primary full"
          type="submit"
          disabled={!title.trim() || busy}
        >
          {busy ? (
            <>
              <RefreshCw className="spin" size={18} />{" "}
              {file && !created ? "Se încarcă și se verifică…" : "Se salvează…"}
            </>
          ) : (
            <>
              <Plus size={18} /> {created ? "Reîncearcă" : "Salvează"}
            </>
          )}
        </button>
      </form>
    </Modal>
  );
}

export function DocumentDetailDialog({
  id,
  initialDocument,
  endpoint,
  userId,
  canEdit,
  onClose,
  onChanged,
}: {
  id: string;
  initialDocument: DocumentDetail | null;
  endpoint: string;
  userId: number;
  canEdit: boolean;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [doc, setDoc] = useState<Document | null>(initialDocument);
  const [error, setError] = useState("");
  const [loadAttempt, setLoadAttempt] = useState(0);
  const [canRetryLoad, setCanRetryLoad] = useState(false);
  const [members, setMembers] = useState<Member[]>([]);
  const [visibility, setVisibility] = useState<Document["visibility"]>(
    initialDocument?.visibility ?? "private",
  );
  const [grants, setGrants] = useState<string[]>(initialDocument?.grants ?? []);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [sharing, setSharing] = useState(false);
  const [sharingStale, setSharingStale] = useState(false);
  const [editingExpiration, setEditingExpiration] = useState(false);
  const [expirationStale, setExpirationStale] = useState(false);
  const [expiresOn, setExpiresOn] = useState("");
  const [attachingFile, setAttachingFile] = useState(false);
  const [attachment, setAttachment] = useState<File | null>(null);
  const [attachmentStale, setAttachmentStale] = useState(false);
  const primaryAction = useRef<HTMLButtonElement>(null);
  const attachmentWasOpen = useRef(false);
  useEffect(() => {
    if (attachmentWasOpen.current && !attachingFile) {
      primaryAction.current?.focus();
    }
    attachmentWasOpen.current = attachingFile;
  }, [attachingFile]);
  const path = `${endpoint}/documents/${id}`;
  function assign(data: Document) {
    setDoc(data);
    setVisibility(data.visibility);
    setGrants(data.grants ?? []);
  }
  useEffect(() => {
    if (initialDocument && loadAttempt === 0) return;
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
  function openAttachment() {
    if (!doc || busy) return;
    setAttachment(null);
    setAttachmentStale(false);
    setError("");
    setMessage("");
    setAttachingFile(true);
  }
  function attachmentError(error: unknown) {
    if (
      error instanceof ApiError &&
      [401, 403, 404, 419].includes(error.status)
    ) {
      setDoc(null);
      setAttachingFile(false);
      onChanged();
      setError(
        "Documentul nu mai este disponibil sau nu mai ai permisiunea de a adăuga un fișier.",
      );
    } else if (
      error instanceof ApiError &&
      [409, 412, 428].includes(error.status)
    ) {
      setAttachmentStale(true);
      setError(
        "Documentul s-a schimbat. Reîncarcă-l înainte de a adăuga fișierul.",
      );
    } else {
      setError(
        error instanceof Error ? error.message : "Nu am putut adăuga fișierul.",
      );
    }
  }
  async function reloadAttachment() {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      const current = await api<DocumentDetail>(path);
      assign(current);
      setAttachmentStale(false);
      if (current.status !== null) {
        setAttachingFile(false);
        setAttachment(null);
        setMessage("Fișierul a fost deja adăugat.");
        onChanged();
      }
    } catch (error) {
      attachmentError(error);
    } finally {
      setBusy(false);
    }
  }
  async function saveAttachment(event: FormEvent) {
    event.preventDefault();
    if (!doc || !attachment || busy || attachmentStale) return;
    setBusy(true);
    setError("");
    const data = new FormData();
    data.set("file", attachment);
    try {
      assign(
        await api<DocumentDetail>(`${path}/file`, {
          method: "POST",
          headers: { "If-Match": `"${doc.version}"` },
          body: data,
        }),
      );
      setAttachingFile(false);
      setAttachment(null);
      setMessage("Fișierul a fost adăugat.");
      onChanged();
    } catch (error) {
      attachmentError(error);
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
            : attachingFile
              ? "Adaugă fișier"
              : (doc?.title ?? "Document")
      }
      onClose={() => {
        if (!busy) onClose();
      }}
      wide
    >
      {!doc && !error && <p className="loading-text">Se încarcă…</p>}
      {doc && !sharing && !editingExpiration && !attachingFile && (
        <>
          <div className="detail-art">
            <span className="category-label">{categoryName(doc.category)}</span>
            <FilePreview mime={doc.mime} category={doc.category} />
            <span className="detail-badge">
              <LockKeyhole size={14} />
              {visibilityLabel[doc.visibility]}
            </span>
          </div>
          <p className="document-meta">{documentFileType(doc.mime)}</p>
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
          {(doc.status === "quarantine" || doc.status === "rejected") && (
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
            {doc.status !== null && (
              <button
                ref={primaryAction}
                className="button primary"
                disabled={busy || doc.status !== "ready"}
                onClick={() => void download()}
              >
                <ArrowDownToLine size={18} /> Descarcă
              </button>
            )}
            {doc.status === null && doc.owner_id === userId && canEdit && (
              <button
                ref={primaryAction}
                className="button primary"
                disabled={busy}
                onClick={openAttachment}
              >
                <UploadCloud size={18} aria-hidden="true" /> Adaugă fișier
              </button>
            )}
            {doc.owner_id === userId &&
              !familyDocumentCategories.includes(doc.category) && (
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
      {doc && attachingFile && (
        <form onSubmit={saveAttachment}>
          <p className="modal-lead">{doc.title}</p>
          <DocumentFileField
            file={attachment}
            onChange={setAttachment}
            onError={setError}
            disabled={busy || attachmentStale}
          />
          <button
            className="button primary full"
            disabled={busy || attachmentStale || !attachment}
          >
            {busy ? "Se încarcă și se verifică…" : "Salvează"}
          </button>
          <button
            type="button"
            className="text-button full center"
            disabled={busy}
            onClick={() => {
              setAttachingFile(false);
              setAttachment(null);
              setError("");
            }}
          >
            Înapoi la document
          </button>
        </form>
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
            {(["private", "family", "adults", "selected"] as const).map(
              (value) => (
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
                    {value === "adults" && (
                      <p>
                        Adulții activi, inclusiv cei cu acces limitat. Membrii
                        înregistrați ca minori sunt excluși.
                      </p>
                    )}
                  </div>
                </label>
              ),
            )}
          </div>
          {visibility === "selected" && (
            <fieldset
              className="member-options"
              disabled={busy || sharingStale}
            >
              <legend>Alege persoanele</legend>
              {!shareableMembers(members, userId).length && (
                <p>Niciun alt membru activ.</p>
              )}
              {shareableMembers(members, userId).map((m) => (
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
      {attachingFile && attachmentStale && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => void reloadAttachment()}
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
