import { useEffect, useRef, useState, type ReactNode } from "react";
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
  type Family,
} from "../../shared/api";
import { ProfilePhotoEditor } from "../../shared/ProfilePhotoEditor";
import { SectionTabs } from "../../shared/SectionTabs";
import { PageBackdrop } from "../../shared/PageBackdrop";
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
import { ProfileSettings, ProfileEntryModal } from "./ProfileForms";
import { ProfileStory } from "./ProfileStory";
import { FamilyTree } from "./FamilyTree";
import "./memberProfile.css";

export type ProfileDocumentsSlot = (props: {
  member: Member;
  canCreate: boolean;
  requestedDocument: string | null;
  onDocumentOpened: () => void;
  onExpired: () => void;
}) => ReactNode;

export function MemberProfile({
  family,
  member,
  members,
  onBack,
  onExpired,
  onMember,
  onMemory,
  requestedDocument,
  onDocumentOpened,
  renderDocuments,
}: {
  family: Family;
  member: Member;
  members: Member[];
  onBack: () => void;
  onExpired: () => void;
  onMember: (id: string) => void;
  onMemory: (id: string) => void;
  requestedDocument: string | null;
  onDocumentOpened: () => void;
  renderDocuments: ProfileDocumentsSlot;
}) {
  const base = `/families/${family.id}/members/${member.id}/profile`;
  const [profile, setProfile] = useState<Profile | null>(null);
  const [tab, setTab] = useState<Tab>(
    requestedDocument ? "documents" : "overview",
  );
  useEffect(() => {
    if (requestedDocument) setTab("documents");
  }, [requestedDocument]);
  const [items, setItems] = useState<Entry[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [metadataRevision, setMetadataRevision] = useState(0);
  const [storyReset, setStoryReset] = useState(0);
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
    if (tab === "documents" || tab === "overview") return;
    const controller = new AbortController();
    setLoading(true);
    setError("");
    const params = new URLSearchParams({
      page: String(page),
    });
    params.set("category", tab);
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
  }, [base, tab, page, revision]);
  useEffect(() => {
    if (tab === "documents") return;
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
  }, [base, dialog, tab]);
  function refresh() {
    setRevision((v) => v + 1);
    setMetadataRevision((v) => v + 1);
  }
  function selectTab(value: Tab) {
    setTab(value);
    setPage(1);
  }
  function unavailable() {
    setProfile(null);
    setItems([]);
    setDialog(null);
    setError("Profilul nu mai este disponibil sau accesul s-a schimbat.");
    onExpired();
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
          <PageBackdrop image="family" className="member-profile-backdrop">
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
              </div>
            </section>
          </PageBackdrop>
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
          <SectionTabs<Tab>
            className="member-profile-tabs section-tabs--scroll"
            label="Secțiuni profil"
            value={tab}
            options={[
              { value: "overview", label: "Prezentare" },
              ...(
                Object.entries(categories) as [Category, { label: string }][]
              ).map(([value, category]) => ({ value, label: category.label })),
              { value: "documents", label: "Documente" },
            ]}
            onChange={selectTab}
          />
          {tab === "documents" ? (
            renderDocuments({
              member,
              canCreate: profile.can_write,
              requestedDocument,
              onDocumentOpened,
              onExpired,
            })
          ) : tab === "overview" ? (
            <div className="profile-overview-layout">
              <ProfileStory
                key={storyReset}
                base={base}
                familyId={family.id}
                revision={revision}
                canWrite={profile.can_write}
                onEntry={setDialog}
                onMoment={onMemory}
                onAdd={() => setDialog("new")}
                onRefresh={refresh}
                onUnavailable={unavailable}
                onEntries={(entries) =>
                  setDialog((current) =>
                    current &&
                    typeof current === "object" &&
                    !current.can_manage
                      ? (entries.find((entry) => entry.id === current.id) ??
                        null)
                      : current,
                  )
                }
              />
              <FamilyTree
                base={base}
                familyId={family.id}
                memberId={member.id}
                members={members.map((relative) =>
                  relative.id === profile.id
                    ? {
                        ...relative,
                        photo_status: profile.photo_status,
                        photo_version: profile.version,
                      }
                    : relative,
                )}
                revision={metadataRevision}
                onMember={onMember}
                onUnavailable={unavailable}
              />
            </div>
          ) : (
            <section aria-labelledby="profile-entries-title">
              <div className="section-header">
                <h2 id="profile-entries-title">{categories[tab].label}</h2>
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
              {loading && !items.length ? (
                <p role="status" className="member-profile-empty">
                  Se încarcă însemnările…
                </p>
              ) : !error && !items.length ? (
                <div className="member-profile-empty">
                  <FolderHeart size={28} />
                  <p>Nicio însemnare.</p>
                  {profile.can_write && (
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
          )}
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
            <ProfilePhotoEditor
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
                setStoryReset((value) => value + 1);
                if (tab !== "overview") setTab(saved.category);
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
