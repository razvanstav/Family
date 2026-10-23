import { useEffect, useState } from "react";
import {
  CarFront,
  Plus,
  Search,
  RefreshCw,
  Pencil,
  Camera,
  ChevronRight,
  Wrench,
  FileText,
  Link2Off,
} from "lucide-react";
import { api, ApiError, type Family } from "../../shared/api";
import { SectionTabs } from "../../shared/SectionTabs";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { dateLabel } from "../../shared/calendarDates";
import {
  entryKinds,
  dueLabel,
  money,
  type Vehicle,
  type VehicleEntry,
  type EntryPage,
  type VehicleDocument,
} from "./vehicleData";
import {
  VehicleForm,
  VehiclePhotoForm,
  VehicleEntryForm,
  VehicleDocumentForm,
} from "./VehicleForms";
import "./vehicles.css";

type Tab = "overview" | "maintenance" | "documents" | "costs";
const tabs = [
  { value: "overview", label: "Prezentare" },
  { value: "maintenance", label: "Revizii" },
  { value: "documents", label: "Documente" },
  { value: "costs", label: "Costuri" },
] as const;
const shortDate = (date: string) =>
  dateLabel(date, { day: "numeric", month: "long", year: "numeric" });

export function Vehicles({
  family,
  onExpired,
}: {
  family: Family;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/vehicles`;
  const [items, setItems] = useState<Vehicle[]>([]);
  const [selected, setSelected] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [searchOpen, setSearchOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [archived, setArchived] = useState(false);
  const [newVehicle, setNewVehicle] = useState(false);
  const canManage = family.role !== "limited";
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError("");
    api<Vehicle[]>(`${base}?archived=${archived ? 1 : 0}`, {
      signal: controller.signal,
    })
      .then((rows) => {
        if (controller.signal.aborted) return;
        setItems(rows);
        setSelected((id) =>
          rows.some((v) => v.id === id) ? id : (rows[0]?.id ?? ""),
        );
      })
      .catch((e) => {
        if (controller.signal.aborted) return;
        setItems([]);
        setSelected("");
        setError(
          e instanceof Error ? e.message : "Nu am putut încărca mașinile.",
        );
        if (e instanceof ApiError && [401, 404, 419].includes(e.status))
          onExpired();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, archived, revision]);
  const filtered = items.filter((v) =>
    `${v.name} ${v.make} ${v.model} ${v.registration ?? ""}`
      .toLocaleLowerCase("ro-RO")
      .includes(search.toLocaleLowerCase("ro-RO")),
  );
  const vehicle = filtered.find((v) => v.id === selected) ?? filtered[0];
  function updated(v: Vehicle) {
    setItems((rows) => rows.map((row) => (row.id === v.id ? v : row)));
    setRevision((n) => n + 1);
  }
  return (
    <div className="vehicles-page">
      <PageBackdrop image="vehicles">
        <div className="page-heading">
          <h1>Auto</h1>
          <div className="page-heading-actions">
            <button
              className="icon-button"
              aria-label="Caută mașini"
              aria-expanded={searchOpen}
              onClick={() => {
                if (searchOpen) {
                  setSearch("");
                  setArchived(false);
                }
                setSearchOpen((v) => !v);
              }}
            >
              <Search size={21} />
            </button>
            {canManage && (
              <button
                className="button primary"
                aria-label="Adaugă mașină"
                onClick={() => setNewVehicle(true)}
              >
                <Plus size={20} />
                <span>Adaugă mașină</span>
              </button>
            )}
          </div>
        </div>
      </PageBackdrop>
      {searchOpen && (
        <label className="search vehicle-search">
          <Search size={18} />
          <input
            aria-label="Caută mașini"
            placeholder="Caută mașini"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
      )}
      {(searchOpen || items.length > 1 || archived) && (
        <div className="vehicle-picker-row">
          {items.length > 1 && (
            <label className="vehicle-picker">
              Mașină
              <select
                value={vehicle?.id ?? ""}
                onChange={(e) => setSelected(e.target.value)}
              >
                {filtered.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name}
                  </option>
                ))}
              </select>
            </label>
          )}
          {searchOpen && (
            <label className="vehicle-archive-filter">
              <input
                type="checkbox"
                checked={archived}
                onChange={(e) => {
                  setArchived(e.target.checked);
                  setItems([]);
                  setSelected("");
                }}
              />
              Arhivate
            </label>
          )}
          {searchOpen && (
            <button
              className="icon-button"
              aria-label="Actualizează mașinile"
              disabled={loading}
              onClick={() => setRevision((v) => v + 1)}
            >
              <RefreshCw size={17} />
            </button>
          )}
        </div>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
          <button
            className="text-button"
            onClick={() => setRevision((v) => v + 1)}
          >
            Reîncarcă mașinile
          </button>
        </p>
      )}
      {loading && !items.length ? (
        <p className="vehicle-empty" role="status">
          Se încarcă mașinile…
        </p>
      ) : vehicle ? (
        <VehicleProfile
          key={vehicle.id}
          vehicle={vehicle}
          familyId={family.id}
          base={`${base}/${vehicle.id}`}
          onUpdated={updated}
          onExpired={onExpired}
        />
      ) : (
        !error && (
          <div className="vehicle-empty">
            <CarFront size={42} />
            <p>
              {search
                ? "Nicio mașină găsită."
                : archived
                  ? "Nicio mașină arhivată."
                  : "Nicio mașină adăugată."}
            </p>
            {canManage && !archived && !search && (
              <button
                className="button secondary"
                onClick={() => setNewVehicle(true)}
              >
                <Plus size={18} />
                Adaugă mașină
              </button>
            )}
          </div>
        )
      )}
      {newVehicle && (
        <VehicleForm
          vehicle={null}
          base={base}
          onClose={() => setNewVehicle(false)}
          onSaved={(v) => {
            setNewVehicle(false);
            setArchived(false);
            setSearch("");
            setSelected(v.id);
            setRevision((n) => n + 1);
          }}
        />
      )}
    </div>
  );
}

function VehicleProfile({
  vehicle,
  familyId,
  base,
  onUpdated,
  onExpired,
}: {
  vehicle: Vehicle;
  familyId: string;
  base: string;
  onUpdated: (v: Vehicle) => void;
  onExpired: () => void;
}) {
  const [tab, setTab] = useState<Tab>("overview");
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [planned, setPlanned] = useState<EntryPage | null>(null);
  const [history, setHistory] = useState<EntryPage | null>(null);
  const [documents, setDocuments] = useState<VehicleDocument[]>([]);
  const [page, setPage] = useState(1);
  const [archived, setArchived] = useState(false);
  const [state, setState] = useState("all");
  const [dialog, setDialog] = useState<
    "edit" | "photo" | "new" | "link" | VehicleEntry | null
  >(null);
  const canManage = vehicle.can_manage && !vehicle.archived_at;
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError("");
    setPlanned(null);
    setHistory(null);
    setDocuments([]);
    async function load() {
      const options = { signal: controller.signal };
      if (tab === "documents") {
        const rows = await api<VehicleDocument[]>(`${base}/documents`, options);
        if (!controller.signal.aborted) setDocuments(rows);
      } else if (tab === "overview") {
        const rows = await Promise.all([
          api<EntryPage>(`${base}/entries?state=planned`, options),
          api<EntryPage>(`${base}/entries?state=completed`, options),
        ]);
        if (!controller.signal.aborted) {
          setPlanned(rows[0]);
          setHistory(rows[1]);
        }
      } else {
        const params = new URLSearchParams({
          group: tab === "costs" ? "costs" : "maintenance",
          page: String(page),
          archived: archived ? "1" : "0",
        });
        if (tab === "maintenance" && state !== "all")
          params.set("state", state);
        const rows = await api<EntryPage>(`${base}/entries?${params}`, options);
        if (!controller.signal.aborted) setHistory(rows);
      }
    }
    void load()
      .catch((e) => {
        if (controller.signal.aborted) return;
        setError(
          e instanceof Error
            ? e.message
            : "Nu am putut încărca datele mașinii.",
        );
        if (e instanceof ApiError && [401, 404, 419].includes(e.status)) {
          setDialog(null);
          onExpired();
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, tab, revision, page, archived, state]);
  function selectTab(value: Tab) {
    setTab(value);
    setPage(1);
    setArchived(false);
    setState("all");
  }
  function saved() {
    setDialog(null);
    setRevision((v) => v + 1);
  }
  async function unlink(document: VehicleDocument) {
    try {
      await api(`${base}/documents/${document.id}`, { method: "DELETE" });
      setRevision((v) => v + 1);
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Nu am putut elimina asocierea.",
      );
    }
  }
  return (
    <>
      <section className="vehicle-hero" aria-label={`Mașină: ${vehicle.name}`}>
        <div className="vehicle-photo-wrap">
          <PrivatePhoto
            className="vehicle-photo"
            status={vehicle.photo_status}
            src={`/api/v1${base}/photo?v=${vehicle.version}`}
            alt={`${vehicle.make} ${vehicle.model}`}
            placeholder={<CarFront size={62} strokeWidth={1.2} />}
          />
          {canManage && (
            <button
              className="vehicle-camera icon-button"
              aria-label="Schimbă fotografia mașinii"
              onClick={() => setDialog("photo")}
            >
              <Camera size={17} />
            </button>
          )}
        </div>
        <div className="vehicle-identity">
          <div className="vehicle-name">
            <h2 aria-label={vehicle.name}>
              {vehicle.can_manage ? (
                <button
                  className="vehicle-edit-name"
                  aria-label="Editează mașina"
                  onClick={() => setDialog("edit")}
                >
                  {vehicle.name}
                  <Pencil size={17} />
                </button>
              ) : (
                vehicle.name
              )}
            </h2>
          </div>
          <p>
            {vehicle.make} {vehicle.model}
            {vehicle.year ? ` · ${vehicle.year}` : ""}
          </p>
          {vehicle.registration && (
            <span className="vehicle-registration">{vehicle.registration}</span>
          )}
          {vehicle.archived_at && (
            <span className="vehicle-access-note">Arhivată</span>
          )}
        </div>
      </section>
      <SectionTabs
        className="vehicle-tabs section-tabs--scroll"
        label="Secțiuni Auto"
        value={tab}
        options={tabs}
        onChange={selectTab}
      />
      {error && (
        <div className="error" role="alert">
          {error}
          <button
            className="text-button"
            onClick={() => setRevision((v) => v + 1)}
          >
            Reîncarcă
          </button>
        </div>
      )}
      {tab === "overview" ? (
        <div className="vehicle-overview">
          <section aria-labelledby="vehicle-upcoming">
            <div className="section-header">
              <h2 id="vehicle-upcoming">Următoarele evenimente</h2>
              <button
                className="text-button"
                aria-label="Vezi toate evenimentele auto"
                onClick={() => {
                  selectTab("maintenance");
                  setState("planned");
                }}
              >
                <span>Vezi toate</span>
                <ChevronRight size={15} />
              </button>
            </div>
            <EntryRows
              entries={planned?.items.slice(0, 3) ?? []}
              loading={loading}
              empty="Nicio scadență adăugată."
              onOpen={setDialog}
            />
          </section>
          <section aria-labelledby="vehicle-history">
            <div className="section-header">
              <h2 id="vehicle-history">Istoric și costuri</h2>
              <button
                className="text-button"
                aria-label="Vezi tot istoricul auto"
                onClick={() => selectTab("costs")}
              >
                <span>Vezi toate</span>
                <ChevronRight size={15} />
              </button>
            </div>
            <EntryRows
              entries={history?.items.slice(0, 3) ?? []}
              loading={loading}
              empty="Nicio înregistrare în istoric."
              onOpen={setDialog}
            />
          </section>
          {canManage && (
            <button
              className="vehicle-add-entry"
              onClick={() => setDialog("new")}
            >
              <span className="vehicle-entry-icon vehicle-tone-green">
                <Wrench size={23} />
              </span>
              <strong>Adaugă un cost sau o revizie</strong>
              <ChevronRight size={18} />
            </button>
          )}
        </div>
      ) : tab === "documents" ? (
        <section aria-labelledby="vehicle-documents">
          <div className="section-header">
            <h2 id="vehicle-documents">Documente</h2>
            {canManage && (
              <button
                className="icon-button primary"
                aria-label="Asociază document"
                onClick={() => setDialog("link")}
              >
                <Plus size={20} />
              </button>
            )}
          </div>
          {loading ? (
            <p role="status" className="vehicle-list-empty">
              Se încarcă documentele…
            </p>
          ) : !documents.length ? (
            <p className="vehicle-list-empty">
              Niciun document asociat la care ai acces.
            </p>
          ) : (
            <div className="vehicle-list">
              {documents.map((doc) => (
                <div className="vehicle-document" key={doc.id}>
                  <span className="vehicle-entry-icon vehicle-tone-ochre">
                    <FileText size={21} />
                  </span>
                  <div className="vehicle-row-body">
                    {doc.status === "ready" ? (
                      <a
                        target="_blank"
                        rel="noreferrer"
                        href={`/api/v1/families/${familyId}/documents/${doc.id}/content`}
                      >
                        {doc.title}
                      </a>
                    ) : (
                      <strong>{doc.title}</strong>
                    )}
                    {doc.status !== "ready" && (
                      <span>
                        {doc.status === "quarantine"
                          ? "În verificare"
                          : "Respins"}
                      </span>
                    )}
                  </div>
                  {canManage && (
                    <button
                      className="icon-button"
                      aria-label={`Elimină asocierea: ${doc.title}`}
                      onClick={() => void unlink(doc)}
                    >
                      <Link2Off size={17} />
                    </button>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>
      ) : (
        <section aria-labelledby="vehicle-records">
          <div className="section-header">
            <h2 id="vehicle-records">
              {tab === "maintenance" ? "Revizii și scadențe" : "Costuri"}
            </h2>
            {canManage && (
              <button
                className="icon-button primary"
                aria-label="Adaugă cost sau revizie"
                onClick={() => setDialog("new")}
              >
                <Plus size={20} />
              </button>
            )}
          </div>
          <div className="vehicle-record-filters">
            {tab === "maintenance" && (
              <label className="vehicle-state-filter">
                Stare
                <select
                  value={state}
                  onChange={(e) => {
                    setState(e.target.value);
                    setPage(1);
                  }}
                >
                  <option value="all">Toate</option>
                  <option value="planned">Planificate</option>
                  <option value="completed">Efectuate</option>
                </select>
              </label>
            )}
            <label className="vehicle-archive-filter">
              <input
                type="checkbox"
                checked={archived}
                onChange={(e) => {
                  setArchived(e.target.checked);
                  setPage(1);
                }}
              />
              Arhivate
            </label>
          </div>
          <EntryRows
            entries={history?.items ?? []}
            loading={loading}
            empty={
              tab === "costs"
                ? "Niciun cost înregistrat."
                : "Nicio revizie înregistrată."
            }
            onOpen={setDialog}
          />
          {(page > 1 || history?.has_more) && (
            <div className="vehicle-pagination">
              <button
                className="button secondary"
                disabled={loading || page === 1}
                onClick={() => setPage((v) => v - 1)}
              >
                Înapoi
              </button>
              <span>Pagina {page}</span>
              <button
                className="button secondary"
                disabled={loading || !history?.has_more}
                onClick={() => setPage((v) => v + 1)}
              >
                Înainte
              </button>
            </div>
          )}
        </section>
      )}
      {dialog === "edit" && (
        <VehicleForm
          vehicle={vehicle}
          base={`/families/${familyId}/vehicles`}
          onClose={() => setDialog(null)}
          onSaved={(v) => {
            setDialog(null);
            onUpdated(v);
          }}
        />
      )}
      {dialog === "photo" && (
        <VehiclePhotoForm
          vehicle={vehicle}
          base={`/families/${familyId}/vehicles`}
          onClose={() => setDialog(null)}
          onSaved={(v) => {
            setDialog(null);
            onUpdated(v);
          }}
        />
      )}
      {(dialog === "new" || (dialog && typeof dialog === "object")) && (
        <VehicleEntryForm
          entry={typeof dialog === "object" ? dialog : null}
          base={base}
          canManage={!!canManage}
          defaultState={tab === "maintenance" ? "planned" : "completed"}
          onClose={() => setDialog(null)}
          onSaved={saved}
        />
      )}
      {dialog === "link" && (
        <VehicleDocumentForm
          base={base}
          familyId={familyId}
          linked={documents.map((d) => d.id)}
          onClose={() => setDialog(null)}
          onSaved={saved}
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
  entries: VehicleEntry[];
  loading: boolean;
  empty: string;
  onOpen: (e: VehicleEntry) => void;
}) {
  if (loading)
    return (
      <p className="vehicle-list-empty" role="status">
        Se încarcă înregistrările…
      </p>
    );
  if (!entries.length) return <p className="vehicle-list-empty">{empty}</p>;
  return (
    <div className="vehicle-list">
      {entries.map((entry) => {
        const kind = entryKinds[entry.kind];
        const Icon = kind.icon;
        const due = dueLabel(entry.event_date);
        return (
          <button
            key={entry.id}
            className="vehicle-entry"
            onClick={() => onOpen(entry)}
          >
            <span className={`vehicle-entry-icon vehicle-tone-${kind.color}`}>
              <Icon size={21} />
            </span>
            <span className="vehicle-row-body">
              <strong>{entry.title}</strong>
              <span>
                {shortDate(entry.event_date)}
                {entry.odometer_km !== null
                  ? ` · ${new Intl.NumberFormat("ro-RO").format(entry.odometer_km)} km`
                  : ""}
              </span>
            </span>
            {entry.state === "planned" ? (
              <span
                className={`vehicle-due ${due.overdue ? "vehicle-due-overdue" : `vehicle-tone-${kind.color}`}`}
              >
                {due.text}
              </span>
            ) : (
              entry.cost !== null && (
                <span className="vehicle-cost">{money(entry.cost)}</span>
              )
            )}
            <ChevronRight size={15} />
          </button>
        );
      })}
    </div>
  );
}
