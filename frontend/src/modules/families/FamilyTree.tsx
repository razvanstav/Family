import { SelectField } from "../../shared/SelectField";
import { useEffect, useState, type FormEvent } from "react";
import {
  ArrowUpLeft,
  GitBranch,
  Plus,
  Pencil,
  Unlink,
  ChevronRight,
} from "lucide-react";
import { api, ApiError, type Member } from "../../shared/api";
import { UserAvatar } from "../../shared/UserAvatar";
import { Modal } from "../../shared/Modal";
import { message } from "./profileData";

type Person = {
  id: string;
  membership_id: string | null;
  name: string;
  version: number;
};
type Edge = {
  id: string;
  from_id: string;
  to_id: string;
  kind: "parent" | "partner" | "sibling";
  version: number;
};
type Tree = { nodes: Person[]; edges: Edge[]; can_manage: boolean };

export function FamilyTree({
  base,
  familyId,
  memberId,
  members,
  revision,
  onMember,
  onUnavailable,
}: {
  base: string;
  familyId: string;
  memberId: string;
  members: Member[];
  revision: number;
  onMember: (id: string) => void;
  onUnavailable: () => void;
}) {
  const [data, setData] = useState<Tree | null>(null);
  const [focus, setFocus] = useState(memberId);
  const [error, setError] = useState("");
  const [reload, setReload] = useState(0);
  const [editing, setEditing] = useState<"add" | "rename" | "links" | null>(
    null,
  );
  const [relation, setRelation] = useState("parent");
  const [target, setTarget] = useState("");
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [stale, setStale] = useState(false);
  const [editingVersion, setEditingVersion] = useState(0);
  const [formError, setFormError] = useState("");
  const endpoint = `${base}/relations`;
  useEffect(() => {
    const controller = new AbortController();
    setError("");
    api<Tree>(endpoint, { signal: controller.signal })
      .then((result) => {
        if (!controller.signal.aborted) setData(result);
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setData(null);
        setError(message(e));
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
          setEditing(null);
          onUnavailable();
        }
      });
    return () => controller.abort();
  }, [endpoint, revision, reload]);
  useEffect(() => {
    if (data && !data.nodes.some((node) => node.id === focus)) {
      setFocus(memberId);
      setEditing(null);
    }
  }, [data, focus, memberId]);
  const selected = data?.nodes.find((node) => node.id === focus);
  const edges =
    data?.edges.filter(
      (edge) => edge.from_id === focus || edge.to_id === focus,
    ) ?? [];
  const parents = edges
    .filter((edge) => edge.kind === "parent" && edge.to_id === focus)
    .map((edge) => edge.from_id);
  const children = edges
    .filter((edge) => edge.kind === "parent" && edge.from_id === focus)
    .map((edge) => edge.to_id);
  const partners = edges
    .filter((edge) => edge.kind === "partner")
    .map((edge) => (edge.from_id === focus ? edge.to_id : edge.from_id));
  const siblings = edges
    .filter((edge) => edge.kind === "sibling")
    .map((edge) => (edge.from_id === focus ? edge.to_id : edge.from_id));
  function photo(person: Person) {
    const member = members.find((item) => item.id === person.membership_id);
    return member?.photo_status === "ready"
      ? `/api/v1/families/${familyId}/members/${member.id}/profile/photo?v=${member.photo_version}`
      : undefined;
  }
  function group(ids: string[], label: string) {
    if (!ids.length) return null;
    return (
      <div className="genealogy-generation">
        <h3>{label}</h3>
        <div className="genealogy-nodes">
          {ids.map((id) => {
            const person = data!.nodes.find((node) => node.id === id)!;
            return (
              <button
                key={id}
                className="genealogy-person"
                onClick={() => setFocus(id)}
                aria-label={`Vezi legăturile lui ${person.name}`}
              >
                <UserAvatar
                  name={person.name}
                  photo={photo(person)}
                  className="genealogy-avatar"
                />
                <span>{person.name}</span>
              </button>
            );
          })}
        </div>
      </div>
    );
  }
  function open(mode: "add" | "rename" | "links") {
    setEditingVersion(selected?.version ?? 0);
    setFormError("");
    setStale(false);
    setName(mode === "rename" ? (selected?.name ?? "") : "");
    setTarget("");
    setRelation("parent");
    setEditing(mode);
  }
  async function mutate(
    path: string,
    method: string,
    body?: object,
    version?: number,
  ) {
    if (busy) return;
    setBusy(true);
    setFormError("");
    try {
      const result = await api<Tree>(path, {
        method,
        ...(body ? { body: JSON.stringify(body) } : {}),
        ...(version === undefined
          ? {}
          : { headers: { "If-Match": `"${version}"` } }),
      });
      setData(result);
      setEditing(null);
    } catch (e) {
      setFormError(message(e));
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
        setData(null);
        setEditing(null);
        onUnavailable();
      } else if (e instanceof ApiError && e.status === 412) setStale(true);
    } finally {
      setBusy(false);
    }
  }
  function save(event: FormEvent) {
    event.preventDefault();
    if (editing === "rename")
      void mutate(
        `${endpoint}/people/${focus}`,
        "PATCH",
        { name },
        editingVersion,
      );
    else
      void mutate(endpoint, "POST", {
        from_id: focus,
        relation,
        ...(target ? { target_id: target } : { name }),
      });
  }
  function edgeLabel(edge: Edge) {
    const other = data!.nodes.find(
      (node) =>
        node.id === (edge.from_id === focus ? edge.to_id : edge.from_id),
    )!;
    const label =
      edge.kind === "parent"
        ? edge.to_id === focus
          ? "Părinte"
          : "Copil"
        : edge.kind === "partner"
          ? "Partener"
          : "Frate sau soră";
    return `${other.name} · ${label}`;
  }
  return (
    <section className="profile-tree" aria-labelledby="genealogy-title">
      <div className="section-header">
        <h2 id="genealogy-title">
          <GitBranch size={19} aria-hidden="true" />
          Arbore genealogic
        </h2>
      </div>
      {error ? (
        <div>
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="text-button"
            onClick={() => setReload((v) => v + 1)}
          >
            Reîncearcă
          </button>
        </div>
      ) : !selected ? (
        <p role="status">Se încarcă arborele…</p>
      ) : (
        <>
          {focus !== memberId && (
            <button
              className="text-button genealogy-back"
              onClick={() => setFocus(memberId)}
            >
              <ArrowUpLeft size={16} />
              Înapoi la profil
            </button>
          )}
          {group(parents, "Părinți")}
          <div
            className={`genealogy-focus${parents.length ? " genealogy-focus--parents" : ""}${children.length ? " genealogy-focus--children" : ""}`}
          >
            <UserAvatar
              name={selected.name}
              photo={photo(selected)}
              className="genealogy-avatar genealogy-avatar--focus"
            />
            <strong>{selected.name}</strong>
            {!selected.membership_id && <span>Rudă fără cont</span>}
            {selected.membership_id &&
              selected.id !== memberId &&
              members.some(
                (member) => member.id === selected.membership_id,
              ) && (
                <button
                  className="text-button"
                  onClick={() => onMember(selected.membership_id!)}
                >
                  Deschide profilul
                  <ChevronRight size={15} />
                </button>
              )}
          </div>
          {group(children, "Copii")}
          {group(partners, "Parteneri")}
          {group(siblings, "Frați și surori")}
          {!edges.length && (
            <p className="genealogy-empty">
              Nicio relație genealogică înregistrată.
            </p>
          )}
          {edges.length > 0 && (
            <p className="genealogy-hint">
              Selectează o persoană pentru a vedea legăturile ei.
            </p>
          )}
          {data?.can_manage && (
            <div className="genealogy-actions">
              <button className="text-button" onClick={() => open("add")}>
                <Plus size={16} />
                Adaugă rudă
              </button>
              {!selected.membership_id && (
                <button
                  className="icon-button"
                  aria-label={`Editează numele ${selected.name}`}
                  onClick={() => open("rename")}
                >
                  <Pencil size={16} />
                </button>
              )}
              {edges.length > 0 && (
                <button
                  className="icon-button"
                  aria-label="Gestionează legăturile"
                  onClick={() => open("links")}
                >
                  <GitBranch size={16} />
                </button>
              )}
            </div>
          )}
        </>
      )}
      {editing && selected && (
        <Modal
          title={
            editing === "add"
              ? `Adaugă rudă pentru ${selected.name}`
              : editing === "rename"
                ? "Editează numele"
                : `Legăturile lui ${selected.name}`
          }
          onClose={() => {
            if (!busy) setEditing(null);
          }}
        >
          {editing === "links" ? (
            <div className="genealogy-link-list">
              {edges.map((edge) => (
                <div key={edge.id}>
                  <span>{edgeLabel(edge)}</span>
                  <button
                    className="icon-button"
                    aria-label={`Elimină legătura ${edgeLabel(edge)}`}
                    disabled={busy || stale}
                    onClick={() =>
                      void mutate(
                        `${endpoint}/${edge.id}`,
                        "DELETE",
                        undefined,
                        edge.version,
                      )
                    }
                  >
                    <Unlink size={17} />
                  </button>
                </div>
              ))}
              <p className="fine-print">
                Eliminarea legăturii păstrează persoanele în arbore. O poți
                adăuga din nou alegând persoana existentă.
              </p>
            </div>
          ) : (
            <form onSubmit={save}>
              <fieldset
                className="profile-form-fields"
                disabled={busy || stale}
              >
                {editing === "add" && (
                  <>
                    <label className="field">
                      Relația cu {selected.name}
                      <SelectField
                        value={relation}
                        onChange={(e) => setRelation(e.target.value)}
                      >
                        <option value="parent">Părinte</option>
                        <option value="child">Copil</option>
                        <option value="partner">Partener</option>
                        <option value="sibling">Frate sau soră</option>
                      </SelectField>
                    </label>
                    <label className="field">
                      Persoană
                      <SelectField
                        value={target}
                        onChange={(e) => setTarget(e.target.value)}
                      >
                        <option value="">Rudă nouă, fără cont</option>
                        {data?.nodes
                          .filter((node) => node.id !== focus)
                          .map((node) => (
                            <option value={node.id} key={node.id}>
                              {node.name}
                            </option>
                          ))}
                      </SelectField>
                    </label>
                  </>
                )}
                {(!target || editing === "rename") && (
                  <label className="field">
                    Nume
                    <input
                      required
                      maxLength={100}
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      autoFocus={editing === "rename"}
                    />
                  </label>
                )}
                {editing === "add" && (
                  <p className="genealogy-permission">
                    Rudele fără cont sunt adăugate numai în arbore. Relațiile
                    sunt vizibile familiei și nu acordă acces la documente sau
                    drepturi de părinte desemnat.
                  </p>
                )}
                <button className="button primary full" disabled={busy}>
                  {busy ? "Se salvează…" : "Salvează"}
                </button>
              </fieldset>
            </form>
          )}
          {formError && (
            <p className="error" role="alert">
              {formError}
            </p>
          )}
          {stale && (
            <button
              className="text-button"
              onClick={() => {
                setEditing(null);
                setReload((v) => v + 1);
              }}
            >
              Reîncarcă arborele
            </button>
          )}
        </Modal>
      )}
    </section>
  );
}
