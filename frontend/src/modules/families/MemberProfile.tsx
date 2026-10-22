import { useEffect, useRef, useState } from "react";
import {
  ArrowLeft,
  FolderHeart,
  Plus,
  ChevronRight,
  Camera,
  Settings2,
  LockKeyhole,
  Users,
  RefreshCw,
} from "lucide-react";
import {
  api,
  ApiError,
  initials,
  visibilityLabel,
  type Member,
} from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import {
  categories,
  kinds,
  message,
  type Category,
  type Tab,
  type Profile,
  type Entry,
  type EntryList,
} from "./profileData";
import {
  ProfileSettings,
  ProfilePhoto,
  ProfileEntryModal,
} from "./ProfileForms";
import "./memberProfile.css";

export function MemberProfile({
  familyId,
  member,
  members,
  onBack,
  onDocuments,
  onExpired,
}: {
  familyId: string;
  member: Member;
  members: Member[];
  onBack: () => void;
  onDocuments: (member: Member) => void;
  onExpired: () => void;
}) {
  const base = `/families/${familyId}/members/${member.id}/profile`;
  const [profile, setProfile] = useState<Profile | null>(null);
  const [tab, setTab] = useState<Tab>("overview");
  const [items, setItems] = useState<Entry[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [archived, setArchived] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [metadataRevision, setMetadataRevision] = useState(0);
  const loadedPosition = useRef("");
  const [dialog, setDialog] = useState<
    "configure" | "photo" | "new" | Entry | null
  >(null);
  useEffect(() => {
    const controller = new AbortController();
    api<Profile>(base, { signal: controller.signal })
      .then((data) => {
        if (!controller.signal.aborted) setProfile(data);
      })
      .catch((e) => {
        if (controller.signal.aborted) return;
        setProfile(null);
        setError(message(e));
        if (e instanceof ApiError && [401, 404, 419].includes(e.status))
          onExpired();
      });
    return () => controller.abort();
  }, [base, metadataRevision]);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError("");
    const params = new URLSearchParams({
      page: String(page),
      archived: archived ? "1" : "0",
    });
    if (tab !== "overview") params.set("category", tab);
    const position = `${base}/entries?${params}`;
    if (loadedPosition.current !== position) setItems([]);
    api<EntryList>(position, { signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted) return;
        loadedPosition.current = position;
        setItems(data.items);
        setHasMore(data.has_more);
        setDialog((current) =>
          current && typeof current === "object" && !current.can_manage
            ? (data.items.find((item) => item.id === current.id) ?? null)
            : current,
        );
      })
      .catch((e) => {
        if (controller.signal.aborted) return;
        setItems([]);
        setHasMore(false);
        setDialog((current) =>
          current && typeof current === "object" && !current.can_manage
            ? null
            : current,
        );
        setError(message(e));
        if (e instanceof ApiError && [401, 404, 419].includes(e.status)) {
          setProfile(null);
          onExpired();
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, tab, page, archived, revision]);
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (
        document.visibilityState === "visible" &&
        (dialog === null || (typeof dialog === "object" && !dialog.can_manage))
      ) {
        setRevision((value) => value + 1);
        setMetadataRevision((value) => value + 1);
      }
    }, 60_000);
    return () => window.clearInterval(timer);
  }, [base, dialog]);
  function refresh() {
    setRevision((v) => v + 1);
    setMetadataRevision((v) => v + 1);
  }
  function selectTab(value: Tab) {
    setTab(value);
    setPage(1);
    setArchived(false);
  }
  return (
    <div className="member-profile-page">
      <div className="member-profile-back">
        <button className="text-button" onClick={onBack}>
          <ArrowLeft size={19} />
          Familia mea
        </button>
        {profile?.can_configure && (
          <button
            className="icon-button"
            aria-label="Data nașterii și părinți"
            onClick={() => setDialog("configure")}
          >
            <Settings2 size={19} />
          </button>
        )}
      </div>
      {error && (
        <div className="error" role="alert">
          {error}
          <button className="text-button" onClick={refresh}>
            Reîncarcă profilul
          </button>
        </div>
      )}
      {!profile ? (
        !error && <p role="status">Se încarcă profilul…</p>
      ) : (
        <>
          <section
            className="member-profile-hero"
            aria-label={`Profil ${profile.name}`}
          >
            <div className="member-profile-avatar-wrap">
              {profile.photo_status ? (
                <PrivatePhoto
                  src={`/api/v1${base}/photo?v=${profile.version}`}
                  status={profile.photo_status}
                  className="member-profile-photo"
                  placeholder={<Users size={30} />}
                  alt={`Fotografie: ${profile.name}`}
                />
              ) : (
                <div className="member-profile-initials" aria-hidden="true">
                  {initials(profile.name)}
                </div>
              )}
              {profile.can_write && (
                <button
                  className="member-profile-camera icon-button"
                  aria-label="Schimbă fotografia"
                  onClick={() => setDialog("photo")}
                >
                  <Camera size={17} />
                </button>
              )}
            </div>
            <div className="member-profile-identity">
              <h1>{profile.name}</h1>
              {profile.age !== null && (
                <span>
                  {profile.age} {profile.age === 1 ? "an" : "ani"}
                </span>
              )}
              <span className="member-profile-role">
                <Users size={14} />
                {profile.age !== null && profile.age < 18
                  ? "Copil"
                  : profile.role === "owner"
                    ? "Proprietar"
                    : profile.role === "limited"
                      ? "Membru cu acces limitat"
                      : "Membru adult"}
              </span>
              <button
                className="text-button member-profile-documents"
                onClick={() => onDocuments(member)}
              >
                <FolderHeart size={16} />
                Documente
              </button>
            </div>
          </section>
          {profile.age !== null && profile.age < 14 && (
            <p className="member-profile-rule">
              Până la 14 ani, datele sunt adăugate numai de părinții desemnați.
              {profile.parents.length > 0 &&
                ` ${profile.parents.map((parent) => parent.name).join(", ")}.`}
            </p>
          )}
          {profile.photo_status === "quarantine" && profile.can_write && (
            <button
              className="text-button"
              onClick={async () => {
                try {
                  setProfile(
                    await api<Profile>(`${base}/photo/scan`, {
                      method: "POST",
                      headers: { "If-Match": `"${profile.version}"` },
                    }),
                  );
                } catch (e) {
                  setError(message(e));
                }
              }}
            >
              Reverifică fotografia
            </button>
          )}
          <nav className="member-profile-tabs" aria-label="Secțiuni profil">
            <button
              aria-pressed={tab === "overview"}
              onClick={() => selectTab("overview")}
            >
              Prezentare
            </button>
            {(
              Object.entries(categories) as [
                Category,
                (typeof categories)[Category],
              ][]
            ).map(([key, value]) => (
              <button
                key={key}
                aria-pressed={tab === key}
                onClick={() => selectTab(key)}
              >
                {value.label}
              </button>
            ))}
          </nav>
          <section aria-labelledby="profile-entries-title">
            <div className="section-header">
              <h2 id="profile-entries-title">
                {tab === "overview"
                  ? "Însemnări recente"
                  : categories[tab].label}
              </h2>
              <div className="member-profile-actions">
                <button
                  className="icon-button"
                  aria-label="Actualizează profilul"
                  onClick={refresh}
                  disabled={loading}
                >
                  <RefreshCw size={17} />
                </button>
                {profile.can_write && (
                  <button
                    className="icon-button primary"
                    aria-label="Adaugă însemnare"
                    onClick={() => setDialog("new")}
                  >
                    <Plus size={20} />
                  </button>
                )}
              </div>
            </div>
            <label className="member-profile-archive">
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
            {loading && !items.length ? (
              <p role="status" className="member-profile-empty">
                Se încarcă însemnările…
              </p>
            ) : !error && !items.length ? (
              <div className="member-profile-empty">
                <FolderHeart size={28} />
                <p>
                  {archived ? "Nicio însemnare arhivată." : "Nicio însemnare."}
                </p>
                {profile.can_write && !archived && (
                  <button
                    className="button secondary"
                    onClick={() => setDialog("new")}
                  >
                    <Plus size={17} />
                    Adaugă însemnare
                  </button>
                )}
              </div>
            ) : (
              <div className="member-profile-entries">
                {items.map((entry) => {
                  const Icon = kinds[entry.kind].icon;
                  return (
                    <button
                      className={`profile-entry profile-entry--${entry.category}`}
                      key={entry.id}
                      aria-label={`Deschide ${entry.title}`}
                      onClick={() => setDialog(entry)}
                    >
                      <span className="profile-entry-icon">
                        <Icon size={22} />
                      </span>
                      <span className="profile-entry-body">
                        <strong>{entry.title}</strong>
                        <span>
                          {kinds[entry.kind].label} ·{" "}
                          {dateLabel(entry.occurred_on, {
                            day: "numeric",
                            month: "short",
                            year: "numeric",
                          })}
                        </span>
                        <span className="profile-entry-access">
                          {entry.visibility === "private" ? (
                            <LockKeyhole size={12} />
                          ) : (
                            <Users size={12} />
                          )}{" "}
                          {visibilityLabel[entry.visibility]}
                        </span>
                      </span>
                      <ChevronRight size={16} />
                    </button>
                  );
                })}
              </div>
            )}
            {(page > 1 || hasMore) && (
              <div className="member-profile-pagination">
                <button
                  className="button secondary"
                  disabled={page === 1 || loading}
                  onClick={() => setPage((v) => v - 1)}
                >
                  Înapoi
                </button>
                <span>Pagina {page}</span>
                <button
                  className="button secondary"
                  disabled={!hasMore || loading}
                  onClick={() => setPage((v) => v + 1)}
                >
                  Înainte
                </button>
              </div>
            )}
          </section>
          {dialog === "configure" && (
            <ProfileSettings
              profile={profile}
              members={members}
              base={base}
              onClose={() => setDialog(null)}
              onSaved={(updated) => {
                setProfile(updated);
                setDialog(null);
                setRevision((v) => v + 1);
              }}
              onReload={() => {
                setDialog(null);
                refresh();
              }}
            />
          )}
          {dialog === "photo" && (
            <ProfilePhoto
              profile={profile}
              base={base}
              onClose={() => setDialog(null)}
              onSaved={(updated) => {
                setProfile(updated);
                setDialog(null);
              }}
              onReload={() => {
                setDialog(null);
                refresh();
              }}
            />
          )}
          {(dialog === "new" || (dialog && typeof dialog === "object")) && (
            <ProfileEntryModal
              entry={typeof dialog === "object" ? dialog : null}
              defaultKind={
                tab === "learning"
                  ? "book"
                  : tab === "activities"
                    ? "activity"
                    : "visit"
              }
              base={base}
              onClose={() => setDialog(null)}
              onSaved={(saved) => {
                setDialog(null);
                setPage(1);
                if (tab !== "overview") setTab(saved.category);
                if (!saved.archived_at) setArchived(false);
                setRevision((v) => v + 1);
              }}
              onReload={() => {
                setDialog(null);
                refresh();
              }}
            />
          )}
        </>
      )}
    </div>
  );
}
