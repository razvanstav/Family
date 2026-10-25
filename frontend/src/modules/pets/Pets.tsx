import { useEffect, useRef, useState } from "react";
import { documentFileState } from "../../shared/documentFiles";
import type {
  DocumentCreationRender,
  DocumentDetailRender,
} from "../../shared/documentIntegration";
import {
  Camera,
  CalendarDays,
  ChevronRight,
  FileText,
  Link2Off,
  PawPrint,
  Pencil,
  Plus,
  RefreshCw,
} from "lucide-react";
import {
  api,
  ApiError,
  errorMessage,
  type Family,
  type PetRequest,
} from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { money } from "../../shared/numbers";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { SectionTabs } from "../../shared/SectionTabs";
import { PetEntryForm, PetForm, PetPhotoForm } from "./PetForms";
import {
  careKinds,
  type Pet,
  type PetDocument,
  type PetEntry,
  type PetEntryPage,
} from "./petData";
import "./pets.css";

type Tab = "overview" | "care" | "documents";
const tabs = [
  { value: "overview", label: "Prezentare" },
  { value: "care", label: "Îngrijire" },
  { value: "documents", label: "Documente" },
] as const;
const shortDate = (date: string) =>
  dateLabel(date, { day: "numeric", month: "long", year: "numeric" });
const weightFormat = new Intl.NumberFormat("ro-RO", {
  maximumFractionDigits: 3,
});

export function Pets({
  family,
  userId,
  requestedEntry,
  onEntryOpened,
  onChanged,
  onExpired,
  requestedProfile,
  onProfileOpened,
  renderDocumentCreation,
  renderDocumentDetail,
}: {
  family: Family;
  userId: number;
  requestedEntry: PetRequest | null;
  onEntryOpened: () => void;
  onChanged: () => void;
  onExpired: () => void;
  requestedProfile: string | null;
  onProfileOpened: () => void;
  renderDocumentCreation: DocumentCreationRender;
  renderDocumentDetail: DocumentDetailRender;
}) {
  const base = `/families/${family.id}/pets`;
  const [items, setItems] = useState<Pet[]>([]);
  const [selected, setSelected] = useState("");
  const [archived, setArchived] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [adding, setAdding] = useState(false);
  const canManage = family.role !== "limited";
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError("");
    api<Pet[]>(`${base}?archived=${archived ? 1 : 0}`, {
      signal: controller.signal,
    })
      .then(async (rows) => {
        if (controller.signal.aborted) return;
        const requestedId = requestedEntry?.petId ?? requestedProfile;
        if (requestedId && !rows.some((pet) => pet.id === requestedId)) {
          const target = await api<Pet>(`${base}/${requestedId}`, {
            signal: controller.signal,
          });
          if (controller.signal.aborted) return;
          if (!!target.archived_at !== archived) {
            setArchived(!!target.archived_at);
            return;
          }
          rows = [...rows, target];
        }
        setItems(rows);
        setSelected(
          (current) =>
            requestedEntry?.petId ??
            requestedProfile ??
            (rows.some((pet) => pet.id === current)
              ? current
              : (rows[0]?.id ?? "")),
        );
        if (requestedProfile) onProfileOpened();
      })
      .catch((error) => {
        if (controller.signal.aborted) return;
        setItems([]);
        setSelected("");
        setError(errorMessage(error));
        if (error instanceof ApiError && [401, 419].includes(error.status))
          onExpired();
        if (
          requestedEntry &&
          error instanceof ApiError &&
          [403, 404].includes(error.status)
        )
          onEntryOpened();
        if (requestedProfile) onProfileOpened();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, archived, revision]);
  const pet = items.find((pet) => pet.id === selected) ?? items[0];
  function updated(value: Pet) {
    setItems((items) =>
      items.map((pet) => (pet.id === value.id ? value : pet)),
    );
    setRevision((value) => value + 1);
    onChanged();
  }
  return (
    <section className="pets-page page-enter">
      <PageBackdrop image="pets">
        <div className="page-heading">
          <h1>Animale</h1>
          {canManage && (
            <button
              className="button primary"
              aria-label="Adaugă animal"
              onClick={() => setAdding(true)}
            >
              <Plus size={20} />
              <span>Adaugă animal</span>
            </button>
          )}
        </div>
      </PageBackdrop>
      <div className="pets-toolbar">
        {items.length > 0 && (
          <label className="field pets-picker">
            Animal
            <select
              value={pet?.id ?? ""}
              onChange={(event) => setSelected(event.target.value)}
            >
              {items.map((pet) => (
                <option key={pet.id} value={pet.id}>
                  {pet.name}
                </option>
              ))}
            </select>
          </label>
        )}
        <label className="field pets-status-filter">
          Listă
          <select
            value={archived ? "archived" : "active"}
            onChange={(event) => {
              setArchived(event.target.value === "archived");
              setItems([]);
              setSelected("");
            }}
          >
            <option value="active">Active</option>
            <option value="archived">Arhivate</option>
          </select>
        </label>
        <button
          className="icon-button"
          aria-label="Actualizează animalele"
          disabled={loading}
          onClick={() => setRevision((value) => value + 1)}
        >
          <RefreshCw size={18} />
        </button>
      </div>
      {error && (
        <div className="error" role="alert">
          {error}
          <button
            className="text-button"
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncarcă animalele
          </button>
        </div>
      )}
      {loading && !items.length ? (
        <p className="pets-empty" role="status">
          Se încarcă animalele…
        </p>
      ) : pet ? (
        <PetProfile
          key={pet.id}
          pet={pet}
          base={`${base}/${pet.id}`}
          familyId={family.id}
          userId={userId}
          requestedEntry={
            requestedEntry?.petId === pet.id ? requestedEntry : null
          }
          onEntryOpened={onEntryOpened}
          onUpdated={updated}
          onChanged={onChanged}
          onExpired={onExpired}
          renderDocumentCreation={renderDocumentCreation}
          renderDocumentDetail={renderDocumentDetail}
        />
      ) : (
        !error && (
          <div className="pets-empty">
            <PawPrint size={36} aria-hidden="true" />
            <p>
              {archived ? "Niciun animal arhivat." : "Niciun animal adăugat."}
            </p>
            {canManage && !archived && (
              <button
                className="button secondary"
                onClick={() => setAdding(true)}
              >
                Adaugă animal
              </button>
            )}
          </div>
        )
      )}
      {adding && (
        <PetForm
          pet={null}
          base={base}
          onClose={() => setAdding(false)}
          onSaved={(pet) => {
            setAdding(false);
            setSelected(pet.id);
            setArchived(false);
            setRevision((value) => value + 1);
            onChanged();
          }}
          onUnavailable={() => {
            setAdding(false);
            onExpired();
          }}
        />
      )}
    </section>
  );
}

function PetProfile({
  pet,
  base,
  familyId,
  userId,
  requestedEntry,
  onEntryOpened,
  onUpdated,
  onChanged,
  onExpired,
  renderDocumentCreation,
  renderDocumentDetail,
}: {
  pet: Pet;
  base: string;
  familyId: string;
  userId: number;
  requestedEntry: PetRequest | null;
  onEntryOpened: () => void;
  onUpdated: (pet: Pet) => void;
  onChanged: () => void;
  onExpired: () => void;
  renderDocumentCreation: DocumentCreationRender;
  renderDocumentDetail: DocumentDetailRender;
}) {
  const [tab, setTab] = useState<Tab>("overview");
  const [kind, setKind] = useState("");
  const [archivedEntries, setArchivedEntries] = useState(false);
  const [entries, setEntries] = useState<
    (PetEntryPage & { scope: string }) | null
  >(null);
  const [documents, setDocuments] = useState<PetDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [moreBusy, setMoreBusy] = useState(false);
  const [unlinking, setUnlinking] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [openedDocument, setOpenedDocument] = useState<string | null>(null);
  const [dialog, setDialog] = useState<
    "new" | "edit" | "photo" | "document" | PetEntry | null
  >(null);
  const sequence = useRef(0);
  const morePending = useRef(false);
  const canManage = pet.can_manage && !pet.archived_at;
  const endpoint =
    tab === "documents"
      ? `${base}/documents`
      : `${base}/entries?archived=${tab === "care" && archivedEntries ? 1 : 0}${tab === "care" && kind ? `&kind=${kind}` : ""}`;
  const current = entries?.scope === endpoint ? entries : null;
  useEffect(() => {
    const controller = new AbortController();
    const request = ++sequence.current;
    setLoading(true);
    setMoreBusy(false);
    morePending.current = false;
    setError("");
    if (tab === "documents") {
      setDocuments([]);
      api<PetDocument[]>(endpoint, { signal: controller.signal })
        .then((items) => {
          if (request === sequence.current) setDocuments(items);
        })
        .catch(failed)
        .finally(done);
    } else
      api<PetEntryPage>(endpoint, { signal: controller.signal })
        .then((items) => {
          if (request === sequence.current)
            setEntries({ ...items, scope: endpoint });
        })
        .catch(failed)
        .finally(done);
    function failed(error: unknown) {
      if (controller.signal.aborted || request !== sequence.current) return;
      setEntries(null);
      setError(errorMessage(error));
      if (
        error instanceof ApiError &&
        [401, 403, 404, 419].includes(error.status)
      )
        onExpired();
    }
    function done() {
      if (!controller.signal.aborted && request === sequence.current)
        setLoading(false);
    }
    return () => {
      controller.abort();
      sequence.current++;
    };
  }, [endpoint, revision]);
  useEffect(() => {
    if (!requestedEntry || loading || !current) return;
    const controller = new AbortController();
    function opened(entry: PetEntry) {
      setKind("");
      setArchivedEntries(!!entry.archived_at);
      setTab("care");
      setDialog(entry);
      onEntryOpened();
    }
    const existing = current.items.find(
      (entry) => entry.id === requestedEntry.entryId,
    );
    if (existing) opened(existing);
    else
      api<PetEntry>(`${base}/entries/${requestedEntry.entryId}`, {
        signal: controller.signal,
      })
        .then((entry) => {
          if (!controller.signal.aborted) opened(entry);
        })
        .catch((error) => {
          if (!controller.signal.aborted) {
            setError(errorMessage(error));
            onEntryOpened();
          }
        });
    return () => controller.abort();
  }, [requestedEntry, loading, current, base]);
  function selectTab(value: Tab) {
    setTab(value);
    setKind("");
    setArchivedEntries(false);
  }
  async function loadMore() {
    if (!current?.has_more || loading || morePending.current) return;
    const request = sequence.current;
    morePending.current = true;
    setMoreBusy(true);
    setError("");
    try {
      const page = await api<PetEntryPage>(
        `${endpoint}&page=${current.page + 1}`,
      );
      if (request === sequence.current)
        setEntries((previous) =>
          previous
            ? {
                ...page,
                scope: endpoint,
                items: [
                  ...previous.items,
                  ...page.items.filter(
                    (entry) =>
                      !previous.items.some((old) => old.id === entry.id),
                  ),
                ],
              }
            : { ...page, scope: endpoint },
        );
    } catch (error) {
      if (request === sequence.current) setError(errorMessage(error));
    } finally {
      if (request === sequence.current) {
        morePending.current = false;
        setMoreBusy(false);
      }
    }
  }
  function unavailable() {
    setDialog(null);
    setRevision((value) => value + 1);
    onExpired();
  }
  function saved(entry: PetEntry) {
    setDialog(null);
    setArchivedEntries(tab === "care" && !!entry.archived_at);
    if (kind && kind !== entry.kind) setKind(entry.kind);
    setRevision((value) => value + 1);
    onChanged();
  }
  async function unlink(document: PetDocument) {
    if (unlinking) return;
    const request = sequence.current;
    setUnlinking(document.id);
    setError("");
    try {
      await api(`${base}/documents/${document.id}`, { method: "DELETE" });
      if (request === sequence.current)
        setDocuments((items) =>
          items.filter((item) => item.id !== document.id),
        );
    } catch (error) {
      if (request === sequence.current) setError(errorMessage(error));
    } finally {
      setUnlinking(null);
    }
  }
  return (
    <>
      <section className="pet-hero" aria-label={`Animal: ${pet.name}`}>
        <div className="pet-photo-wrap">
          <PrivatePhoto
            className="pet-photo"
            status={pet.photo_status}
            src={`/api/v1${base}/photo?v=${pet.version}`}
            alt={pet.name}
            placeholder={<PawPrint size={56} strokeWidth={1.3} />}
          />
          {canManage && (
            <button
              className="icon-button pet-camera"
              aria-label="Schimbă fotografia animalului"
              onClick={() => setDialog("photo")}
            >
              <Camera size={19} />
            </button>
          )}
        </div>
        <div className="pet-identity">
          <div className="pet-name-row">
            <h2>{pet.name}</h2>
            {pet.can_manage && (
              <button
                className="icon-button"
                aria-label="Editează animalul"
                onClick={() => setDialog("edit")}
              >
                <Pencil size={18} />
              </button>
            )}
          </div>
          <p>
            {pet.species}
            {pet.breed ? ` · ${pet.breed}` : ""}
          </p>
          {pet.birth_date && (
            <p className="pet-birth">
              <CalendarDays size={16} aria-hidden="true" />
              {shortDate(pet.birth_date)}
            </p>
          )}
          {pet.archived_at && <span className="pet-archived">Arhivat</span>}
        </div>
      </section>
      <SectionTabs
        className="pet-tabs"
        label="Secțiuni Animale"
        value={tab}
        options={tabs}
        onChange={selectTab}
      />
      {error && (
        <div className="error" role="alert">
          {error}
          <button
            className="text-button"
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncarcă
          </button>
        </div>
      )}
      {tab === "documents" ? (
        <section aria-labelledby="pet-documents-title">
          <div className="section-header">
            <h2 id="pet-documents-title">Documente</h2>
            {canManage && (
              <button
                className="button secondary"
                onClick={() => setDialog("document")}
              >
                <Plus size={17} />
                <span>Adaugă document</span>
              </button>
            )}
          </div>
          {loading ? (
            <p className="pet-list-empty" role="status">
              Se încarcă documentele…
            </p>
          ) : documents.length ? (
            <div className="pet-list">
              {documents.map((document) => (
                <div className="pet-document" key={document.id}>
                  <span className="pet-entry-icon pet-tone-ochre">
                    <FileText size={22} aria-hidden="true" />
                  </span>
                  <div className="pet-row-copy">
                    <button
                      className="text-button"
                      onClick={() => setOpenedDocument(document.id)}
                    >
                      {document.title}
                    </button>
                    {document.status !== "ready" && (
                      <span>{documentFileState(document.status)}</span>
                    )}
                  </div>
                  {canManage && (
                    <button
                      className="icon-button"
                      aria-label={`Elimină asocierea: ${document.title}`}
                      disabled={!!unlinking}
                      onClick={() => void unlink(document)}
                    >
                      <Link2Off size={18} />
                    </button>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="pet-list-empty">Niciun document la care ai acces.</p>
          )}
        </section>
      ) : (
        <section aria-labelledby="pet-care-title">
          <div className="section-header">
            <h2 id="pet-care-title">
              {tab === "overview" ? "Îngrijire recentă" : "Îngrijire"}
            </h2>
            {tab === "overview" ? (
              <button className="text-button" onClick={() => selectTab("care")}>
                Vezi toate
                <ChevronRight size={16} />
              </button>
            ) : (
              canManage && (
                <button
                  className="button secondary"
                  aria-label="Adaugă înregistrare"
                  onClick={() => setDialog("new")}
                >
                  <Plus size={17} />
                  <span>Adaugă înregistrare</span>
                </button>
              )
            )}
          </div>
          {tab === "care" && (
            <div className="pet-care-filters">
              <label className="field">
                Tip
                <select
                  value={kind}
                  onChange={(event) => setKind(event.target.value)}
                >
                  <option value="">Toate</option>
                  {Object.entries(careKinds).map(([value, item]) => (
                    <option key={value} value={value}>
                      {item.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="pet-archive-check">
                <input
                  type="checkbox"
                  checked={archivedEntries}
                  onChange={(event) => setArchivedEntries(event.target.checked)}
                />
                Arhivate
              </label>
            </div>
          )}
          <EntryRows
            entries={
              tab === "overview"
                ? (current?.items.slice(0, 3) ?? [])
                : (current?.items ?? [])
            }
            loading={loading}
            onOpen={setDialog}
            empty={
              archivedEntries
                ? "Nicio înregistrare arhivată."
                : kind
                  ? "Nicio înregistrare de acest tip."
                  : "Nicio înregistrare de îngrijire."
            }
          />
          {tab === "overview" && canManage && (
            <button
              className="button secondary pet-add-care"
              onClick={() => setDialog("new")}
            >
              <Plus size={18} />
              Adaugă înregistrare
            </button>
          )}
          {tab === "care" && current?.has_more && (
            <button
              className="button secondary pets-more"
              disabled={moreBusy}
              onClick={() => void loadMore()}
            >
              {moreBusy ? "Se încarcă…" : "Mai multe înregistrări"}
            </button>
          )}
        </section>
      )}
      {dialog === "edit" && (
        <PetForm
          pet={pet}
          base={`/families/${familyId}/pets`}
          onClose={() => setDialog(null)}
          onSaved={(pet) => {
            setDialog(null);
            onUpdated(pet);
          }}
          onUnavailable={unavailable}
        />
      )}
      {dialog === "photo" && (
        <PetPhotoForm
          pet={pet}
          base={base}
          onClose={() => setDialog(null)}
          onSaved={(pet) => {
            setDialog(null);
            onUpdated(pet);
          }}
          onUnavailable={unavailable}
        />
      )}
      {dialog === "document" &&
        renderDocumentCreation({
          onClose: () => setDialog(null),
          onSaved: async (document) => {
            await api(`${base}/documents`, {
              method: "POST",
              body: JSON.stringify({ document_id: document.id }),
            });
            setDialog(null);
            setRevision((value) => value + 1);
            onChanged();
          },
        })}
      {openedDocument &&
        renderDocumentDetail({
          id: openedDocument,
          onClose: () => setOpenedDocument(null),
          onChanged: () => {
            setRevision((value) => value + 1);
            onChanged();
          },
        })}
      {dialog && (dialog === "new" || typeof dialog === "object") && (
        <PetEntryForm
          key={typeof dialog === "object" ? dialog.id : "new"}
          entry={typeof dialog === "object" ? dialog : null}
          base={base}
          familyId={familyId}
          userId={userId}
          canManage={!!canManage}
          onClose={() => setDialog(null)}
          onSaved={saved}
          onUnavailable={unavailable}
        />
      )}
    </>
  );
}

function EntryRows({
  entries,
  loading,
  empty,
  onOpen,
}: {
  entries: PetEntry[];
  loading: boolean;
  empty: string;
  onOpen: (entry: PetEntry) => void;
}) {
  if (loading)
    return (
      <p className="pet-list-empty" role="status">
        Se încarcă îngrijirea…
      </p>
    );
  if (!entries.length) return <p className="pet-list-empty">{empty}</p>;
  return (
    <div className="pet-list">
      {entries.map((entry) => {
        const kind = careKinds[entry.kind];
        const Icon = kind.icon;
        const overdue =
          entry.next_due_on &&
          !entry.next_due_completed_at &&
          entry.next_due_on < bucharestToday() &&
          !entry.archived_at;
        return (
          <button
            className="pet-entry-row"
            key={entry.id}
            onClick={() => onOpen(entry)}
            aria-label={`${entry.title} · ${kind.label} · ${shortDate(entry.event_date)}`}
          >
            <span className={`pet-entry-icon pet-tone-${kind.tone}`}>
              <Icon size={22} aria-hidden="true" />
            </span>
            <span className="pet-row-copy">
              <strong>{entry.title}</strong>
              <span>
                {kind.label} · {shortDate(entry.event_date)}
              </span>
              <span className="pet-entry-values">
                {entry.weight_kg !== null && (
                  <span>{weightFormat.format(Number(entry.weight_kg))} kg</span>
                )}
                {entry.cost !== null && <span>{money(entry.cost)}</span>}
              </span>
              {entry.next_due_on && (
                <span className={`pet-due${overdue ? " pet-due-overdue" : ""}`}>
                  <CalendarDays size={14} aria-hidden="true" />
                  {shortDate(entry.next_due_on)}
                  {entry.next_due_completed_at
                    ? " · Încheiată"
                    : overdue
                      ? " · Depășită"
                      : ""}
                </span>
              )}
            </span>
            <ChevronRight
              className="pet-entry-chevron"
              size={17}
              aria-hidden="true"
            />
          </button>
        );
      })}
    </div>
  );
}
