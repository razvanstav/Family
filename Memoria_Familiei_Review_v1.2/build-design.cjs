const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const dir = __dirname;
const write = (name, value) => fs.writeFileSync(path.join(dir, name), value, 'utf8');
const tables = [];
const relations = [];
let sql = `-- Memoria Familiei v1.2: proposed custom domain schema, PostgreSQL 18.
-- Design reference, not an installed application. IDs: application-generated UUIDv7.
-- Apply on an empty schema using a migration role. Runtime: non-owner, NOBYPASSRLS.
-- Laravel sessions/tokens/password resets/jobs/failed_jobs use framework migrations.
-- Tenant content operations MUST use a transaction and set_config('app.family_id', ..., true).
BEGIN;
`;
function table(name, fields, tenant = true) {
  if(fields.some(s=>s.startsWith('id uuid'))&&!fields.some(s=>s.startsWith('lock_version ')))fields.push('lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)');
  const columns=fields.map(s=>{const m=s.match(/^([a-z_]+)\s+(uuid|text|boolean|bigint|integer|smallint|numeric|date|time|timestamptz|jsonb|bytea|char)\b/);return m?{name:m[1],type:m[2],pk:s.includes('PRIMARY KEY'),fk:s.includes('REFERENCES')}:null}).filter(Boolean);
  tables.push({name,tenant,columns,identity:fields.some(s=>s.startsWith('id uuid'))&&fields.some(s=>s.startsWith('family_id uuid'))});
  for(const s of fields){const m=s.match(/REFERENCES\s+([a-z_]+)/);if(m)relations.push([m[1],name]);}
  sql += `\nCREATE TABLE ${name} (\n${fields.map(s=>'  '+s).join(',\n')}\n);\n`;
}
function fk(from, cols, to, target = ['family_id','id'], extra = '') {
  for(const c of tables.find(t=>t.name===from).columns)if(cols.includes(c.name))c.fk=true;
  sql += `ALTER TABLE ${from} ADD FOREIGN KEY (${cols.join(', ')}) REFERENCES ${to} (${target.join(', ')}) ${extra};\n`;
  let index = `${from}_${cols.join('_')}_fk_idx`;
  if(index.length>63)index=index.slice(0,51)+'_'+crypto.createHash('sha256').update(index).digest('hex').slice(0,10);
  sql += `CREATE INDEX ${index} ON ${from} (${cols.join(', ')});\n`;
  relations.push([to,from]);
}
const base = ['id uuid PRIMARY KEY','family_id uuid NOT NULL REFERENCES families(id)','UNIQUE (family_id, id)'];
const times = ['created_at timestamptz NOT NULL DEFAULT now()','updated_at timestamptz NOT NULL DEFAULT now()'];
const acl = ['owner_membership_id uuid NOT NULL',"visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected'))",'lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0)','archived_at timestamptz'];
function tenant(name, fields) { table(name,[...base,...fields,...times]); }
function root(name, fields) { tenant(name,[...acl,...fields]); fk(name,['family_id','owner_membership_id'],'family_memberships'); }
function child(name, fields) { tenant(name, fields); }
table('users', ['id uuid PRIMARY KEY','email text NOT NULL','password_hash text NOT NULL','locale text NOT NULL DEFAULT \'ro\'','timezone text NOT NULL DEFAULT \'Europe/Bucharest\'','email_verified_at timestamptz','disabled_at timestamptz',...times],false);
sql += 'CREATE UNIQUE INDEX users_email_uq ON users (lower(email));\n';
table('families',['id uuid PRIMARY KEY','name text NOT NULL','timezone text NOT NULL DEFAULT \'Europe/Bucharest\'','created_by uuid REFERENCES users(id)','authz_revision bigint NOT NULL DEFAULT 1 CHECK(authz_revision>0)','archived_at timestamptz',...times],false);
table('identity_request_keys',['user_id uuid NOT NULL REFERENCES users(id)','route_key text NOT NULL','request_key text NOT NULL','request_hash text NOT NULL','result_family_id uuid REFERENCES families(id)','expires_at timestamptz NOT NULL','PRIMARY KEY(user_id,route_key,request_key)'],false);
table('identity_privacy_requests',['id uuid PRIMARY KEY','user_id uuid NOT NULL REFERENCES users(id)','family_id uuid REFERENCES families(id)','request_type text NOT NULL CHECK(request_type IN (\'access\',\'export\',\'erasure\'))',"status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','review','done','failed'))",...times],false);
tenant('family_memberships',['user_id uuid NOT NULL REFERENCES users(id)',"role text NOT NULL CHECK (role IN ('owner','adult','limited'))","status text NOT NULL DEFAULT 'active' CHECK (status IN ('active','revoked'))",'joined_at timestamptz NOT NULL DEFAULT now()','UNIQUE(family_id,user_id)']);
// Membership discovery is actor-scoped before an active family exists. No tenant RLS here.
tables.find(t=>t.name==='family_memberships').tenant=false;
tenant('family_invitations',['email text NOT NULL','token_hash text NOT NULL UNIQUE',"role text NOT NULL CHECK(role IN ('adult','limited'))",'expires_at timestamptz NOT NULL','accepted_at timestamptz','invited_by_membership_id uuid NOT NULL']);
fk('family_invitations',['family_id','invited_by_membership_id'],'family_memberships');
tenant('family_modules',['module_id text NOT NULL','enabled boolean NOT NULL DEFAULT true','UNIQUE(family_id,module_id)']);
tenant('subjects',['kind text NOT NULL','archived_at timestamptz','UNIQUE(family_id,id,kind)']);
child('family_members',["subject_kind text NOT NULL DEFAULT 'person' CHECK(subject_kind='person')",'display_name text NOT NULL','birth_date date','linked_membership_id uuid','UNIQUE(family_id,linked_membership_id)']);
fk('family_members',['family_id','linked_membership_id'],'family_memberships');
root('documents',['title text NOT NULL','document_type text NOT NULL','issued_on date','expires_on date',"expiry_mode text NOT NULL DEFAULT 'independent' CHECK(expiry_mode IN ('independent','evidence'))","CHECK(expiry_mode='independent' OR expires_on IS NULL)",'UNIQUE(family_id,id,expiry_mode)','CHECK(expires_on IS NULL OR issued_on IS NULL OR expires_on >= issued_on)']);
tenant('media_assets',['uploader_membership_id uuid NOT NULL','object_key text NOT NULL UNIQUE','expected_bytes bigint NOT NULL CHECK(expected_bytes BETWEEN 1 AND 20971520)','actual_bytes bigint CHECK(actual_bytes > 0)','declared_mime text NOT NULL','detected_mime text','sha256 text',"status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','quarantine','ready','rejected','deleting'))",'owner_module text','owner_type text','owner_id uuid','intent_expires_at timestamptz NOT NULL','CHECK(num_nonnulls(owner_module,owner_type,owner_id) IN (0,3))']);
fk('media_assets',['family_id','uploader_membership_id'],'family_memberships');
root('recipes',['title text NOT NULL']);
child('recipe_versions',['recipe_id uuid NOT NULL','version_no integer NOT NULL CHECK(version_no>0)','title text NOT NULL','ingredients jsonb NOT NULL CHECK(jsonb_typeof(ingredients)=\'array\')','instructions text NOT NULL','UNIQUE(family_id,recipe_id,version_no)','UNIQUE(family_id,recipe_id,id)']);
fk('recipe_versions',['family_id','recipe_id'],'recipes');
root('cooking_sessions',['recipe_id uuid','recipe_version_id uuid','dish_title_snapshot text NOT NULL','cooked_at timestamptz NOT NULL','cooked_by_member_id uuid','notes text','CHECK((recipe_id IS NULL) = (recipe_version_id IS NULL))']);
fk('cooking_sessions',['family_id','recipe_id','recipe_version_id'],'recipe_versions',['family_id','recipe_id','id']);
fk('cooking_sessions',['family_id','cooked_by_member_id'],'family_members');
child('cooking_participants',['session_id uuid NOT NULL','member_id uuid NOT NULL','UNIQUE(family_id,session_id,member_id)']);
fk('cooking_participants',['family_id','session_id'],'cooking_sessions');fk('cooking_participants',['family_id','member_id'],'family_members');
child('meal_ratings',['session_id uuid NOT NULL','member_id uuid NOT NULL','recorded_by_membership_id uuid NOT NULL','score smallint NOT NULL CHECK(score BETWEEN 1 AND 5)','notes text','UNIQUE(family_id,session_id,member_id)']);
fk('meal_ratings',['family_id','session_id','member_id'],'cooking_participants',['family_id','session_id','member_id']);fk('meal_ratings',['family_id','recorded_by_membership_id'],'family_memberships');
root('pets',["subject_kind text NOT NULL DEFAULT 'pet' CHECK(subject_kind='pet')",'name text NOT NULL','species text NOT NULL','breed text','birth_date date']);
child('pet_weight_logs',['pet_id uuid NOT NULL','measured_at timestamptz NOT NULL','weight_kg numeric(7,3) NOT NULL CHECK(weight_kg>0)','note text','lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)']);fk('pet_weight_logs',['family_id','pet_id'],'pets');
child('pet_records',['pet_id uuid NOT NULL',"category text NOT NULL CHECK(category IN ('health','care'))",'record_type text NOT NULL','occurred_on date NOT NULL','next_due_on date','provider text','notes text','evidence_document_id uuid','cost_minor bigint CHECK(cost_minor>=0)','currency char(3) NOT NULL DEFAULT \'RON\' CHECK(currency ~ \'^[A-Z]{3}$\')','lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)','archived_at timestamptz']);
sql+="ALTER TABLE pet_records ADD COLUMN evidence_mode text NOT NULL DEFAULT 'evidence' CHECK(evidence_mode='evidence');\n";
tables.find(t=>t.name==='pet_records').columns.push({name:'evidence_mode',type:'text',fk:true});
fk('pet_records',['family_id','pet_id'],'pets');fk('pet_records',['family_id','evidence_document_id','evidence_mode'],'documents',['family_id','id','expiry_mode']);
root('vehicles',["subject_kind text NOT NULL DEFAULT 'vehicle' CHECK(subject_kind='vehicle')",'label text NOT NULL','make text NOT NULL','model text NOT NULL','year smallint CHECK(year BETWEEN 1886 AND 2200)','fuel_type text',"status text NOT NULL DEFAULT 'active' CHECK(status IN ('active','sold','archived'))"]);
child('vehicle_identifiers',['vehicle_id uuid NOT NULL','kind text NOT NULL','ciphertext bytea NOT NULL','key_version integer NOT NULL','display_mask text NOT NULL','UNIQUE(family_id,vehicle_id,kind)']);fk('vehicle_identifiers',['family_id','vehicle_id'],'vehicles');
child('vehicle_odometer',['vehicle_id uuid NOT NULL','reading_km integer NOT NULL CHECK(reading_km>=0)','recorded_at timestamptz NOT NULL','note text','lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)']);fk('vehicle_odometer',['family_id','vehicle_id'],'vehicles');
child('vehicle_service_logs',['vehicle_id uuid NOT NULL','serviced_on date NOT NULL','service_type text NOT NULL','odometer_km integer CHECK(odometer_km>=0)','provider text','cost_minor bigint CHECK(cost_minor>=0)','currency char(3) NOT NULL DEFAULT \'RON\' CHECK(currency ~ \'^[A-Z]{3}$\')','notes text','evidence_document_id uuid',"evidence_mode text NOT NULL DEFAULT 'evidence' CHECK(evidence_mode='evidence')",'lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)']);fk('vehicle_service_logs',['family_id','vehicle_id'],'vehicles');fk('vehicle_service_logs',['family_id','evidence_document_id','evidence_mode'],'documents',['family_id','id','expiry_mode']);
child('vehicle_fuel_logs',['vehicle_id uuid NOT NULL','fueled_at timestamptz NOT NULL','liters numeric(9,3) NOT NULL CHECK(liters>0)','total_minor bigint CHECK(total_minor>=0)','currency char(3) NOT NULL DEFAULT \'RON\' CHECK(currency ~ \'^[A-Z]{3}$\')','odometer_km integer CHECK(odometer_km>=0)','lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)']);fk('vehicle_fuel_logs',['family_id','vehicle_id'],'vehicles');
child('vehicle_obligations',['vehicle_id uuid NOT NULL','obligation_key text NOT NULL',"kind text NOT NULL CHECK(kind IN ('itp','rca','rovinieta','maintenance','other'))",'due_on date NOT NULL','evidence_document_id uuid',"evidence_mode text NOT NULL DEFAULT 'evidence' CHECK(evidence_mode='evidence')",'notes text','lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)','closed_at timestamptz']);fk('vehicle_obligations',['family_id','vehicle_id'],'vehicles');fk('vehicle_obligations',['family_id','evidence_document_id','evidence_mode'],'documents',['family_id','id','expiry_mode']);
sql += 'CREATE UNIQUE INDEX vehicle_obligation_active_uq ON vehicle_obligations(family_id,vehicle_id,obligation_key) WHERE closed_at IS NULL;\n';
root('moments',["kind text NOT NULL DEFAULT 'memory' CHECK(kind IN ('memory','note'))",'title text NOT NULL','body text','occurred_at timestamptz NOT NULL']);
root('calendar_items',["origin text NOT NULL CHECK(origin IN ('manual','source'))","item_type text NOT NULL CHECK(item_type IN ('task','event','deadline'))",'title text','starts_at timestamptz','ends_at timestamptz','due_on date','timezone text NOT NULL DEFAULT \'Europe/Bucharest\'','assignee_member_id uuid',"status text NOT NULL DEFAULT 'open' CHECK(status IN ('open','done','cancelled'))",'source_module text','source_type text','source_id uuid','source_version bigint','UNIQUE(family_id,source_module,source_type,source_id)',"CHECK((origin='manual' AND title IS NOT NULL AND num_nonnulls(source_module,source_type,source_id,source_version)=0) OR (origin='source' AND title IS NULL AND num_nonnulls(source_module,source_type,source_id,source_version)=4))",'CHECK(ends_at IS NULL OR (starts_at IS NOT NULL AND ends_at>=starts_at))',"CHECK((item_type='event' AND starts_at IS NOT NULL AND due_on IS NULL) OR (item_type='deadline' AND due_on IS NOT NULL AND starts_at IS NULL) OR (item_type='task' AND starts_at IS NULL))"]);fk('calendar_items',['family_id','assignee_member_id'],'family_members');
for(const [name,parent,col] of [['document_subjects','documents','document_id'],['moment_subjects','moments','moment_id'],['calendar_subjects','calendar_items','calendar_item_id']]) {
  child(name,[`${col} uuid NOT NULL`,'subject_id uuid NOT NULL',`UNIQUE(family_id,${col},subject_id)`]);fk(name,['family_id',col],parent);fk(name,['family_id','subject_id'],'subjects');
}
for(const [name,parent,col] of [['document_media','documents','document_id'],['moment_media','moments','moment_id'],['cooking_session_media','cooking_sessions','session_id']]) {
  child(name,[`${col} uuid NOT NULL`,'media_id uuid NOT NULL','sort_order integer NOT NULL DEFAULT 0 CHECK(sort_order>=0)','UNIQUE(family_id,media_id)']);fk(name,['family_id',col],parent);fk(name,['family_id','media_id'],'media_assets');
}
child('calendar_attendees',['calendar_item_id uuid NOT NULL','member_id uuid NOT NULL','UNIQUE(family_id,calendar_item_id,member_id)']);fk('calendar_attendees',['family_id','calendar_item_id'],'calendar_items');fk('calendar_attendees',['family_id','member_id'],'family_members');
for(const [name,col] of [['recipes','recipe_id'],['cooking_sessions','session_id'],['pets','pet_id'],['vehicles','vehicle_id'],['documents','document_id'],['moments','moment_id'],['calendar_items','calendar_item_id']]) {
  child(name+'_grants',[`${col} uuid NOT NULL`,'membership_id uuid NOT NULL',"permission text NOT NULL CHECK(permission IN ('view','edit'))",'expires_at timestamptz',`UNIQUE(family_id,${col},membership_id,permission)`]);fk(name+'_grants',['family_id',col],name);fk(name+'_grants',['family_id','membership_id'],'family_memberships');
}
child('reminder_rules',['calendar_item_id uuid NOT NULL','recipient_membership_id uuid NOT NULL','offset_days integer NOT NULL CHECK(offset_days BETWEEN 0 AND 3650)','local_time time NOT NULL DEFAULT \'09:00\'',"channel text NOT NULL CHECK(channel IN ('in_app','email'))",'enabled boolean NOT NULL DEFAULT true','lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)','UNIQUE(family_id,calendar_item_id,recipient_membership_id,offset_days,channel)']);fk('reminder_rules',['family_id','calendar_item_id'],'calendar_items');fk('reminder_rules',['family_id','recipient_membership_id'],'family_memberships');
child('notification_deliveries',['rule_id uuid NOT NULL','recipient_membership_id uuid NOT NULL','occurrence_key text NOT NULL','scheduled_at timestamptz NOT NULL','source_version bigint NOT NULL','rule_version bigint NOT NULL',"channel text NOT NULL CHECK(channel IN ('in_app','email'))","status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','leased','sent','cancelled','failed','unknown'))",'lease_until timestamptz','attempts integer NOT NULL DEFAULT 0 CHECK(attempts>=0)','provider_id text','UNIQUE(family_id,rule_id,recipient_membership_id,channel,occurrence_key)']);fk('notification_deliveries',['family_id','rule_id'],'reminder_rules');fk('notification_deliveries',['family_id','recipient_membership_id'],'family_memberships');
tenant('timeline_items',['source_module text NOT NULL','source_type text NOT NULL','source_id uuid NOT NULL','source_version bigint NOT NULL','occurred_at timestamptz NOT NULL','UNIQUE(family_id,source_module,source_type,source_id)']);
table('outbox_messages',['id uuid PRIMARY KEY','family_id uuid NOT NULL REFERENCES families(id)','event_name text NOT NULL','event_version integer NOT NULL CHECK(event_version>0)','aggregate_type text NOT NULL','aggregate_id uuid NOT NULL','aggregate_version bigint NOT NULL','payload jsonb NOT NULL DEFAULT \'{}\'::jsonb','occurred_at timestamptz NOT NULL DEFAULT now()','correlation_id text NOT NULL','fanout_at timestamptz'],false);
table('outbox_deliveries',['event_id uuid NOT NULL REFERENCES outbox_messages(id) ON DELETE CASCADE','handler_key text NOT NULL',"status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','leased','done','failed'))",'available_at timestamptz NOT NULL DEFAULT now()','lease_until timestamptz','attempts integer NOT NULL DEFAULT 0 CHECK(attempts>=0)','last_error_code text','PRIMARY KEY(event_id,handler_key)'],false);
tenant('request_idempotency',['membership_id uuid NOT NULL','request_key text NOT NULL','method text NOT NULL','route_key text NOT NULL','request_hash text NOT NULL','resource_module text','resource_type text','resource_id uuid','response_status integer','expires_at timestamptz NOT NULL','UNIQUE(family_id,membership_id,method,route_key,request_key)']);fk('request_idempotency',['family_id','membership_id'],'family_memberships');
tenant('activity_audit',['actor_user_id uuid REFERENCES users(id)','action text NOT NULL','resource_module text NOT NULL','resource_type text NOT NULL','resource_id uuid','metadata_redacted jsonb NOT NULL DEFAULT \'{}\'::jsonb','occurred_at timestamptz NOT NULL DEFAULT now()']);
tenant('export_requests',['requested_by_membership_id uuid NOT NULL',"status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','running','ready','expired','failed'))",'guard_authz_revision bigint','expires_at timestamptz','output_storage_key text']);fk('export_requests',['family_id','requested_by_membership_id'],'family_memberships');
tenant('erasure_requests',['requested_by_user_id uuid REFERENCES users(id)','scope text NOT NULL','target_id uuid',"status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','review','running','done','failed'))",'completed_at timestamptz']);
// Tombstones must also be copied into a separate encrypted operations backup before production restore.
table('erasure_tombstones',['id uuid PRIMARY KEY','family_id uuid NOT NULL','resource_module text NOT NULL','resource_type text NOT NULL','resource_id uuid NOT NULL','erased_at timestamptz NOT NULL DEFAULT now()','UNIQUE(family_id,resource_module,resource_type,resource_id)'],false);
for(const [owner,kind] of [['family_members','person'],['pets','pet'],['vehicles','vehicle']]) {
  fk(owner,['family_id','id','subject_kind'],'subjects',['family_id','id','kind'],'DEFERRABLE INITIALLY DEFERRED');
}
sql += `
-- Subject identity is shared with its typed owner. Registry has no display label or ACL shortcut.
CREATE FUNCTION mf_immutable_identity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.id IS DISTINCT FROM OLD.id OR NEW.family_id IS DISTINCT FROM OLD.family_id THEN
    RAISE EXCEPTION 'Immutable tenant identity' USING ERRCODE='23514';
  END IF;
  RETURN NEW;
END $$;
CREATE FUNCTION mf_immutable_subject_kind() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.kind IS DISTINCT FROM OLD.kind THEN
    RAISE EXCEPTION 'Immutable subject kind' USING ERRCODE='23514';
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER subjects_kind_immutable BEFORE UPDATE ON subjects
FOR EACH ROW EXECUTE FUNCTION mf_immutable_subject_kind();

-- Each owning module installs these deferred triggers in its migration.
-- TG_ARGV values are trusted migration literals; never supplied by HTTP input.
CREATE FUNCTION mf_subject_owner_required() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_id uuid; target_family uuid; live_kind text; owner_exists boolean;
BEGIN
  IF TG_OP='DELETE' THEN target_id:=OLD.id; target_family:=OLD.family_id;
  ELSE target_id:=NEW.id; target_family:=NEW.family_id; END IF;
  SELECT kind INTO live_kind FROM subjects WHERE id=target_id AND family_id=target_family;
  IF NOT FOUND OR live_kind<>TG_ARGV[1] THEN RETURN NULL; END IF;
  EXECUTE format('SELECT EXISTS(SELECT 1 FROM %s WHERE id=$1 AND family_id=$2)',TG_ARGV[0]::regclass)
    INTO owner_exists USING target_id,target_family;
  IF NOT owner_exists THEN RAISE EXCEPTION 'Subject owner missing' USING ERRCODE='23514'; END IF;
  RETURN NULL;
END $$;
`;
for(const [owner,kind] of [['family_members','person'],['pets','pet'],['vehicles','vehicle']]) {
  sql += `CREATE CONSTRAINT TRIGGER ${owner}_subject_owner AFTER INSERT OR UPDATE ON subjects DEFERRABLE INITIALLY DEFERRED FOR EACH ROW WHEN (NEW.kind='${kind}') EXECUTE FUNCTION mf_subject_owner_required('${owner}','${kind}');\n`;
  sql += `CREATE CONSTRAINT TRIGGER ${owner}_owner_deleted AFTER DELETE OR UPDATE ON ${owner} DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION mf_subject_owner_required('${owner}','${kind}');\n`;
}
// Reject unknown kinds via a deployment-managed allowlist, extended by a new module migration.
table('subject_kinds',['kind text PRIMARY KEY'],false);
relations.push(['subject_kinds','subjects']);tables.find(t=>t.name==='subjects').columns.find(c=>c.name==='kind').fk=true;
sql += `INSERT INTO subject_kinds VALUES ('person'),('pet'),('vehicle');
ALTER TABLE subjects ADD FOREIGN KEY(kind) REFERENCES subject_kinds(kind);
-- Owner modules also install a trigger preventing UPDATE/DELETE of immutable recipe versions.
CREATE FUNCTION mf_recipe_version_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Recipe versions are immutable; use audited erasure workflow for physical deletion' USING ERRCODE='23514'; END $$;
CREATE TRIGGER recipe_version_no_update BEFORE UPDATE ON recipe_versions FOR EACH ROW EXECUTE FUNCTION mf_recipe_version_immutable();
`;
for(const t of tables.filter(t=>t.identity && !['erasure_tombstones','identity_privacy_requests'].includes(t.name))) {
  // Control-plane outbox has family/id too; immutability is useful without enabling tenant RLS.
  sql+=`CREATE TRIGGER ${t.name}_identity_immutable BEFORE UPDATE ON ${t.name} FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();\n`;
}
for(const t of tables.filter(t=>t.tenant)) {
  sql += `ALTER TABLE ${t.name} ENABLE ROW LEVEL SECURITY;\nALTER TABLE ${t.name} FORCE ROW LEVEL SECURITY;\nCREATE POLICY tenant_scope ON ${t.name} USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);\n`;
}
sql += `
CREATE INDEX timeline_cursor_idx ON timeline_items(family_id,occurred_at DESC,id DESC);
CREATE INDEX calendar_due_idx ON calendar_items(family_id,due_on,id) WHERE status='open';
CREATE INDEX calendar_start_idx ON calendar_items(family_id,starts_at,id) WHERE status='open';
CREATE INDEX notification_ready_idx ON notification_deliveries(family_id,scheduled_at,id) WHERE status='pending';
CREATE INDEX outbox_unfanned_idx ON outbox_messages(occurred_at,id) WHERE fanout_at IS NULL;
CREATE INDEX outbox_delivery_ready_idx ON outbox_deliveries(available_at,event_id) WHERE status IN ('pending','leased');
CREATE INDEX vehicle_odometer_history_idx ON vehicle_odometer(family_id,vehicle_id,recorded_at DESC,id DESC);
CREATE INDEX pet_weights_history_idx ON pet_weight_logs(family_id,pet_id,measured_at DESC,id DESC);
CREATE INDEX audit_time_idx ON activity_audit(family_id,occurred_at DESC,id DESC);
CREATE INDEX idempotency_expiry_idx ON request_idempotency(expires_at);
`;
for(const [name,col] of [['recipes','title'],['documents','title'],['moments','title'],['pets','name'],['vehicles','label']]) {
  sql+=`CREATE INDEX ${name}_fts_idx ON ${name} USING gin(to_tsvector('simple',${col}));\n`;
}
sql+='COMMIT;\n';
write('schema.postgresql.sql',sql);
write('erd.mmd','erDiagram\n'+tables.map(t=>`  ${t.name} {\n${t.columns.map(c=>`    ${c.type} ${c.name}${c.pk?' PK':c.fk?' FK':''}`).join('\n')}\n  }`).join('\n')+'\n'+[...new Set(relations.map(([p,c])=>`  ${p} ||--o{ ${c} : references`))].join('\n')+'\n');
write('schema.catalog.json',JSON.stringify(tables,null,2));

// OpenAPI is JSON (also valid YAML 1.2), with typed bodies and responses.
const S = {};
const ref = n => ({$ref:'#/components/schemas/'+n});
const str = (extra={}) => ({type:'string',...extra});
const uuid = () => str({format:'uuid'});
const date = () => str({format:'date'});
const dt = () => str({format:'date-time'});
const integer = (min=0) => ({type:'integer',minimum:min});
const array = items => ({type:'array',items,maxItems:100});
const obj = (properties,required=[]) => ({type:'object',additionalProperties:false,properties,...(required.length?{required}: {})});
const vis = str({enum:['private','family','selected'],default:'private'});
const texts = names => Object.fromEntries(names.map(n=>[n,str({maxLength:10000})]));
S.Meta=obj({request_id:str(),next_cursor:{type:['string','null']}},['request_id']);
S.Problem=obj({type:str(),title:str(),status:integer(400),detail:str(),code:str(),request_id:str(),errors:{type:'object',additionalProperties:array(str())}},['type','title','status','code','request_id']);
S.Source=obj({module:str(),type:str(),id:uuid(),version:integer(1),edit_href:str()},['module','type','id','version']);
S.Ingredient=obj({name:str({minLength:1,maxLength:200}),quantity:{type:'number',exclusiveMinimum:0},unit:str({maxLength:40})},['name']);
function model(name,props,required=[],opts={}) {
  S[name+'Create']=obj(props,required);
  const patchProps=structuredClone(props);for(const key of (opts.immutable||[])) delete patchProps[key];
  S[name+'Patch']={...obj(patchProps),minProperties:1};
  S[name]=obj({id:uuid(),family_id:uuid(),...structuredClone(props),...(opts.response||{}),lock_version:integer(1),created_at:dt(),updated_at:dt()},['id','family_id',...required,'lock_version']);
  S[name+'Result']=obj({data:ref(name),meta:ref('Meta')},['data','meta']);
  S[name+'Page']=obj({data:array(ref(name)),meta:ref('Meta')},['data','meta']);
}
model('Family',{name:str({minLength:1,maxLength:160}),timezone:str({default:'Europe/Bucharest'})},['name']);
delete S.Family.properties.family_id;S.Family.required=S.Family.required.filter(k=>k!=='family_id');
model('Member',{display_name:str({minLength:1,maxLength:160}),birth_date:date()},['display_name'],{response:{linked_membership_id:uuid()}});
model('Invitation',{email:str({format:'email'}),role:str({enum:['adult','limited']})},['email','role'],{response:{expires_at:dt()}});
model('Membership',{role:str({enum:['owner','adult','limited']})},['role'],{response:{user_id:uuid(),status:str({enum:['active','revoked']})}});
const aclProps={visibility:vis};
model('Recipe',{title:str({minLength:1,maxLength:240}),ingredients:array(ref('Ingredient')),instructions:str({minLength:1,maxLength:40000}),...aclProps},['title','ingredients','instructions'],{response:{latest_version_id:uuid()},immutable:['ingredients','instructions']});
model('RecipeVersion',{title:str({minLength:1,maxLength:240}),ingredients:array(ref('Ingredient')),instructions:str({minLength:1,maxLength:40000})},['title','ingredients','instructions'],{response:{recipe_id:uuid(),version_no:integer(1)}});
model('CookingSession',{recipe_version_id:uuid(),dish_title_snapshot:str({minLength:1,maxLength:240}),cooked_at:dt(),cooked_by_member_id:uuid(),participant_ids:array(uuid()),notes:str({maxLength:10000}),...aclProps},['cooked_at','participant_ids'],{response:{recipe_id:uuid()},immutable:['recipe_version_id']});
S.CookingSessionCreate.description='Choose recipe_version_id or dish_title_snapshot. A supplied version must belong to the path recipe when using the nested route. Snapshot is resolved by server for a version.';
S.CookingSessionCreate.anyOf=[{required:['recipe_version_id']},{required:['dish_title_snapshot']}];
model('Rating',{score:{type:'integer',minimum:1,maximum:5},notes:str({maxLength:2000})},['score'],{response:{member_id:uuid(),recorded_by_membership_id:uuid()}});
model('Pet',{name:str({minLength:1,maxLength:160}),species:str({maxLength:80}),breed:str({maxLength:120}),birth_date:date(),...aclProps},['name','species']);
model('PetRecord',{category:str({enum:['health','care']}),record_type:str({maxLength:120}),occurred_on:date(),next_due_on:date(),provider:str({maxLength:200}),notes:str({maxLength:10000}),evidence_document_id:uuid(),cost_minor:integer(),currency:str({pattern:'^[A-Z]{3}$',default:'RON'})},['category','record_type','occurred_on'],{response:{pet_id:uuid()}});
model('PetWeight',{measured_at:dt(),weight_kg:{type:'number',exclusiveMinimum:0,maximum:9999.999},note:str({maxLength:2000})},['measured_at','weight_kg'],{response:{pet_id:uuid()}});
model('Vehicle',{label:str({minLength:1,maxLength:160}),make:str({maxLength:100}),model:str({maxLength:100}),year:{type:'integer',minimum:1886,maximum:2200},fuel_type:str({maxLength:40}),...aclProps},['label','make','model'],{response:{status:str({enum:['active','sold','archived']})}});
model('VehicleIdentifier',{kind:str({enum:['vin','registration']}),value:str({minLength:1,maxLength:80})},['kind','value']);
S.VehicleIdentifier=obj({id:uuid(),family_id:uuid(),kind:str(),display_mask:str(),lock_version:integer(1)},['id','kind','display_mask']);
model('Odometer',{reading_km:integer(),recorded_at:dt(),note:str({maxLength:2000})},['reading_km','recorded_at']);
model('ServiceLog',{serviced_on:date(),service_type:str({maxLength:120}),odometer_km:integer(),provider:str({maxLength:200}),cost_minor:integer(),currency:str({pattern:'^[A-Z]{3}$',default:'RON'}),notes:str({maxLength:10000}),evidence_document_id:uuid()},['serviced_on','service_type']);
model('FuelLog',{fueled_at:dt(),liters:{type:'number',exclusiveMinimum:0},total_minor:integer(),currency:str({pattern:'^[A-Z]{3}$',default:'RON'}),odometer_km:integer()},['fueled_at','liters']);
model('Obligation',{obligation_key:str({minLength:1,maxLength:100}),kind:str({enum:['itp','rca','rovinieta','maintenance','other']}),due_on:date(),evidence_document_id:uuid(),notes:str({maxLength:10000})},['obligation_key','kind','due_on'],{response:{vehicle_id:uuid(),closed_at:dt()},immutable:['obligation_key','kind']});
model('Document',{title:str({minLength:1,maxLength:240}),document_type:str({maxLength:100}),issued_on:date(),expires_on:date(),subject_ids:array(uuid()),media_ids:array(uuid()),...aclProps},['title','document_type','media_ids']);
S.DocumentCreate.properties.media_ids.minItems=1;
S.DocumentPatch.properties.media_ids.minItems=1;S.Document.properties.media_ids.minItems=1;S.Document.properties.expiry_mode=str({enum:['independent','evidence']});
model('Moment',{kind:str({enum:['memory','note'],default:'memory'}),title:str({minLength:1,maxLength:240}),body:str({maxLength:40000}),occurred_at:dt(),subject_ids:array(uuid()),media_ids:array(uuid()),...aclProps},['title','occurred_at']);
model('CalendarItem',{item_type:str({enum:['task','event']}),title:str({minLength:1,maxLength:240}),starts_at:dt(),ends_at:dt(),due_on:date(),timezone:str({default:'Europe/Bucharest'}),assignee_member_id:uuid(),attendee_member_ids:array(uuid()),subject_ids:array(uuid()),status:str({enum:['open','done','cancelled'],default:'open'}),...aclProps},['item_type','title']);
S.CalendarItemCreate.allOf=[{if:{properties:{item_type:{const:'event'}}},then:{required:['starts_at'],not:{required:['due_on']}}}];
S.CalendarItem.properties.item_type=str({enum:['task','event','deadline']});
S.CalendarItem.properties.origin=str({enum:['manual','source']});S.CalendarItem.properties.source=ref('Source');S.CalendarItem.properties.editable={type:'boolean'};
model('Reminder',{recipient_membership_id:uuid(),offset_days:{type:'integer',minimum:0,maximum:3650},local_time:str({pattern:'^([01][0-9]|2[0-3]):[0-5][0-9]$',default:'09:00'}),channel:str({enum:['in_app','email']}),enabled:{type:'boolean',default:true}},['recipient_membership_id','offset_days','channel']);
model('Grant',{membership_id:uuid(),permission:str({enum:['view','edit']}),expires_at:dt()},['membership_id','permission']);
model('UploadIntent',{filename:str({maxLength:240}),declared_mime:str({enum:['image/jpeg','image/png','image/webp','application/pdf']}),expected_bytes:{type:'integer',minimum:1,maximum:20971520}},['filename','declared_mime','expected_bytes'],{response:{upload_url:str({format:'uri'}),required_headers:{type:'object',additionalProperties:str()},expires_at:dt()},immutable:['filename','declared_mime','expected_bytes']});
model('Media',{},[],{response:{status:str({enum:['pending','quarantine','ready','rejected','deleting']}),detected_mime:str(),actual_bytes:integer()}});
model('Export',{},[],{response:{status:str({enum:['pending','running','ready','expired','failed']}),expires_at:dt()}});
model('Erasure',{scope:str({enum:['own_account','resource','family']}),target_id:uuid()},['scope'],{response:{status:str({enum:['pending','review','running','done','failed']})}});
model('PrivacyRequest',{request_type:str({enum:['access','export','erasure']}),family_id:uuid()},['request_type'],{response:{status:str({enum:['pending','review','done','failed']})}});
S.PrivacyRequest.required=S.PrivacyRequest.required.filter(k=>k!=='family_id');
model('Module',{enabled:{type:'boolean'}},['enabled'],{response:{module_id:str()}});
S.TimelineCard=obj({id:uuid(),occurred_at:dt(),source:ref('Source'),title:str(),href:str()},['id','occurred_at','source','title','href']);
S.TimelinePage=obj({data:array(ref('TimelineCard')),meta:ref('Meta')},['data','meta']);
S.SearchHit=obj({source:ref('Source'),title:str(),href:str()},['source','title','href']);S.SearchPage=obj({data:array(ref('SearchHit')),meta:ref('Meta')},['data','meta']);
S.Credentials=obj({email:str({format:'email'}),password:str({minLength:1,maxLength:1024})},['email','password']);
S.MobileCredentials=obj({...S.Credentials.properties,device_name:str({maxLength:100})},['email','password','device_name']);
S.MobileToken=obj({token:str(),expires_at:dt()},['token','expires_at']);
S.Me=obj({id:uuid(),email:str({format:'email'}),locale:str(),timezone:str()},['id','email']);
S.AcceptInvitation=obj({token:str({minLength:20,maxLength:512})},['token']);
const spec={openapi:'3.1.0',info:{title:'Memoria Familiei API',version:'1.2.0-design',description:'MVP contract proposal. Server /api/v1. Cookie SPA and revocable mobile bearer tokens. Cookie mutations require CSRF. Lists are authorization-filtered; cursors use authenticated encryption and are scoped to actor/family/filters. Changes use strong ETags and If-Match. Omitted optional patch fields are unchanged; null clears an optional field where allowed. Source Calendar items are read-only; edit the canonical owner.'},servers:[{url:'/api/v1'}],security:[{cookieAuth:[]},{bearerAuth:[]}],paths:{},components:{schemas:S,securitySchemes:{cookieAuth:{type:'apiKey',in:'cookie',name:'mf_session'},bearerAuth:{type:'http',scheme:'bearer'}},parameters:{IfMatch:{name:'If-Match',in:'header',required:true,description:'Strong ETag returned on the resource, e.g. "7". A stale version yields 412.',schema:str()},IdempotencyKey:{name:'Idempotency-Key',in:'header',required:true,description:'Required on creation/commands. Scoped to actor, family, method and route; TTL 24h. Changed payload under the same key yields 409. Replay reauthorizes the canonical resource.',schema:str({minLength:16,maxLength:128})}}}};
const errCodes=['400','401','403','404','409','412','422','429','503'];
const errorResponses=()=>Object.fromEntries(errCodes.map(c=>[c,{description:({404:'Resource unavailable or outside authorized scope',409:'Conflict, source_controlled, expiry_owner_conflict or idempotency_conflict',412:'Stale If-Match version',422:'Invalid input or unavailable linked resource'})[c]||'Request failed',content:{'application/problem+json':{schema:ref('Problem')}}}]));
function op(p,m,id,response,body,opts={}){
 const params=[...p.matchAll(/\{([^}]+)\}/g)].map(x=>({name:x[1],in:'path',required:true,schema:x[1]==='moduleId'?str():uuid()}));
 if(m==='get' && response?.endsWith('Page')) params.push({name:'cursor',in:'query',schema:str()},{name:'limit',in:'query',schema:{type:'integer',minimum:1,maximum:100,default:25}});
 if(opts.version) params.push({$ref:'#/components/parameters/IfMatch'});
 if(m==='post'&&!opts.noIdempotency)params.push({$ref:'#/components/parameters/IdempotencyKey'});
 const code=opts.code|| (m==='post'?'201':response?'200':'204');
 const success={description:opts.description||'Success',...(response?{headers:{ETag:{schema:str(),description:'Current strong version when the result represents a versioned resource'}},content:{'application/json':{schema:ref(response),...(opts.example?{example:opts.example}:{})}}}:{})};
 const operation={operationId:id,tags:[opts.tag||id.split('_')[0]],summary:opts.summary||id.replaceAll('_',' '),parameters:params,responses:{[code]:success,...errorResponses()}};
 if(body)operation.requestBody={required:true,content:{'application/json':{schema:ref(body)}}};
 if(opts.public)operation.security=[];
 if(opts.description)operation.description=opts.description;
 (spec.paths[p]||={})[m]=operation;
}
const F='/families/{familyId}';
op('/auth/csrf','get','Auth_csrf',null,null,{public:true,code:'204',description:'Set XSRF-TOKEN cookie before SPA login. Cookie requests send X-XSRF-TOKEN and credentials; same-origin deployment.'});
op('/auth/login','post','Auth_login','Me','Credentials',{public:true,noIdempotency:true,code:'200',description:'Browser session cookie, rotated at login. Rate limited; uses CSRF protection.'});
op('/auth/logout','post','Auth_logout',null,null,{noIdempotency:true,code:'204'});
op('/auth/mobile-tokens','post','Auth_mobileToken','MobileToken','MobileCredentials',{public:true,noIdempotency:true,description:'Native release only. Token hashed server-side, absolute expiry 7 days; no implicit refresh. Store with OS-backed secure storage; reauthenticate on expiry. Device revoke on logout.'});
op('/me','get','Identity_me','Me');
op('/privacy/requests','post','Privacy_identityRequest','PrivacyRequestResult','PrivacyRequestCreate',{code:'202',noIdempotency:true,description:'Identity-scoped rights request, available after family membership revocation. Does not expose family content. Verification and review determine the lawful scope.'});
op('/privacy/requests/{requestId}','get','Privacy_identityRequestStatus','PrivacyRequestResult');
op('/families','get','Families_list','FamilyPage');op('/families','post','Families_create','FamilyResult','FamilyCreate');
op(F,'get','Families_get','FamilyResult');op(F,'patch','Families_update','FamilyResult','FamilyPatch',{version:true});
op(F+'/members','get','Families_members','MemberPage');op(F+'/members','post','Families_createMember','MemberResult','MemberCreate');op(F+'/members/{memberId}','patch','Families_updateMember','MemberResult','MemberPatch',{version:true});
op(F+'/invitations','post','Families_invite','InvitationResult','InvitationCreate');op('/invitations/accept','post','Families_acceptInvitation','MembershipResult','AcceptInvitation');
op(F+'/memberships','get','Families_memberships','MembershipPage');op(F+'/memberships/{membershipId}','patch','Families_changeRole','MembershipResult','MembershipPatch',{version:true});op(F+'/memberships/{membershipId}','delete','Families_revoke',null,null,{version:true,description:'Revokes membership, grants, queued notifications and sessions for this family. Last owner cannot be removed.'});
function crud(url,name,tag,opts={}){
 op(url,'get',tag+'_list'+name,name+'Page');op(url,'post',tag+'_create'+name,name+'Result',name+'Create');
 op(url+'/{'+(opts.param||'id')+'}','get',tag+'_get'+name,name+'Result');
 if(!opts.readonly)op(url+'/{'+(opts.param||'id')+'}','patch',tag+'_update'+name,name+'Result',name+'Patch',{version:true});
}
crud(F+'/recipes','Recipe','Kitchen',{param:'recipeId'});
op(F+'/recipes/{recipeId}/versions','get','Kitchen_listVersions','RecipeVersionPage');op(F+'/recipes/{recipeId}/versions','post','Kitchen_createVersion','RecipeVersionResult','RecipeVersionCreate',{version:true});
op(F+'/recipes/{recipeId}/versions/{versionId}','get','Kitchen_getVersion','RecipeVersionResult');
crud(F+'/cooking-sessions','CookingSession','Kitchen',{param:'sessionId'});
op(F+'/recipes/{recipeId}/sessions','post','Kitchen_recordRecipeSession','CookingSessionResult','CookingSessionCreate');
op(F+'/cooking-sessions/{sessionId}/ratings','get','Kitchen_listRatings','RatingPage');
op(F+'/cooking-sessions/{sessionId}/ratings/{memberId}','put','Kitchen_rateMember','RatingResult','RatingCreate',{description:'Unique rating per participant. Actor may rate self; adult may record a child profile rating, with actor audit. Does not impersonate the child.'});
crud(F+'/pets','Pet','Pets',{param:'petId'});
crud(F+'/pets/{petId}/records','PetRecord','Pets',{param:'recordId'});
op(F+'/pets/{petId}/records/{recordId}/archive','post','Pets_archiveRecord','PetRecordResult',null,{version:true,code:'200'});
crud(F+'/pets/{petId}/weight-logs','PetWeight','Pets',{param:'weightId'});
crud(F+'/vehicles','Vehicle','Vehicles',{param:'vehicleId'});
for(const [route,name,param]of [['odometer','Odometer','readingId'],['service-logs','ServiceLog','serviceId'],['fuel-logs','FuelLog','fuelId'],['obligations','Obligation','obligationId']])crud(F+'/vehicles/{vehicleId}/'+route,name,'Vehicles',{param});
op(F+'/vehicles/{vehicleId}/identifiers','get','Vehicles_identifiers','VehicleIdentifierPage');op(F+'/vehicles/{vehicleId}/identifiers','post','Vehicles_storeIdentifier','VehicleIdentifierResult','VehicleIdentifierCreate');
delete S.VehicleIdentifierPatch.properties.kind;
op(F+'/vehicles/{vehicleId}/identifiers/{identifierId}','patch','Vehicles_updateIdentifier','VehicleIdentifierResult','VehicleIdentifierPatch',{version:true});
op(F+'/vehicles/{vehicleId}/obligations/{obligationId}/close','post','Vehicles_closeObligation','ObligationResult',null,{version:true,code:'200'});
crud(F+'/documents','Document','Documents',{param:'documentId'});crud(F+'/moments','Moment','Memory',{param:'momentId'});
crud(F+'/calendar-items','CalendarItem','Calendar',{param:'calendarItemId'});
op(F+'/calendar-items/{calendarItemId}/reminders','get','Calendar_listReminders','ReminderPage');op(F+'/calendar-items/{calendarItemId}/reminders','post','Calendar_createReminder','ReminderResult','ReminderCreate');op(F+'/calendar-items/{calendarItemId}/reminders/{reminderId}','patch','Calendar_updateReminder','ReminderResult','ReminderPatch',{version:true});op(F+'/calendar-items/{calendarItemId}/reminders/{reminderId}','delete','Calendar_deleteReminder',null,null,{version:true});
for(const [route,param,name]of [['recipes','recipeId','Recipe'],['cooking-sessions','sessionId','CookingSession'],['pets','petId','Pet'],['vehicles','vehicleId','Vehicle'],['documents','documentId','Document'],['moments','momentId','Moment'],['calendar-items','calendarItemId','CalendarItem']]){
 const u=F+'/'+route+'/{'+param+'}';
 op(u+'/archive','post',name+'_archive',name+'Result',null,{version:true,code:'200',description:'Archive root; cancel sourced reminders by durable event. Manual Calendar only; sourced rows change through owner.'});
 op(u+'/grants','get',name+'_listGrants','GrantPage');op(u+'/grants','post',name+'_createGrant','GrantResult','GrantCreate',{version:true});op(u+'/grants/{grantId}','delete',name+'_revokeGrant',null,null,{version:true});
}
op(F+'/media/upload-intents','post','Media_uploadIntent','UploadIntentResult','UploadIntentCreate');op(F+'/media/{mediaId}/complete','post','Media_complete','MediaResult',null,{code:'202',description:'HEAD actual object, verify size/checksum, quarantine, scan and normalize. Never trust declared MIME. Poll until ready. Idempotent.'});op(F+'/media/{mediaId}','get','Media_status','MediaResult');
op(F+'/media/{mediaId}/content','get','Media_content',null,null,{code:'200',description:'Authorize the bound canonical resource on every request, then stream private bytes. Pending asset accessible only to its uploader. Cache-Control private,no-store. No bearer tokens in URL.'});
spec.paths[F+'/media/{mediaId}/content'].get.responses['200'].content={'application/octet-stream':{schema:{type:'string',format:'binary'}}};
op(F+'/timeline','get','Memory_timeline','TimelinePage');op(F+'/search','get','Search_query','SearchPage');spec.paths[F+'/search'].get.parameters.push({name:'q',in:'query',required:true,schema:str({minLength:2,maxLength:120})});
for(const p of [F+'/timeline',F+'/calendar-items'])spec.paths[p].get.parameters.push({name:'from',in:'query',schema:date()},{name:'to',in:'query',schema:date()},{name:'subject_id',in:'query',schema:uuid()});
op(F+'/modules','get','Settings_modules','ModulePage');op(F+'/modules/{moduleId}','patch','Settings_toggleModule','ModuleResult','ModulePatch',{version:true,description:'Disabling stops new domain notifications and hides routes; retains data and authorized export. Domain source adapters remain resolvable for cleanup.'});
op(F+'/exports','post','Privacy_export','ExportResult','ExportCreate',{code:'202'});op(F+'/exports/{exportId}','get','Privacy_exportStatus','ExportResult');op(F+'/erasure-requests','post','Privacy_erase','ErasureResult','ErasureCreate',{code:'202'});
op(F+'/exports/{exportId}/content','get','Privacy_downloadExport',null,null,{code:'200',description:'Reauthenticate, verify request owner AND current source access. A revoked permission invalidates ready exports; regenerate. Stream temporary encrypted archive, no shared signed GET.'});
spec.paths[F+'/exports/{exportId}/content'].get.responses['200'].content={'application/zip':{schema:{type:'string',format:'binary'}}};
// Optional PATCH fields may be explicitly cleared. Immutable IDs and required fields cannot be cleared.
const clearable=new Set(['birth_date','breed','next_due_on','provider','notes','note','body','evidence_document_id','cost_minor','total_minor','odometer_km','year','fuel_type','issued_on','expires_on','ends_at','due_on','assignee_member_id','cooked_by_member_id']);
for(const [n,s]of Object.entries(S).filter(([n])=>n.endsWith('Patch'))){const create=S[n.replace(/Patch$/,'Create')];for(const [k,p]of Object.entries(s.properties||{})){if(clearable.has(k)&&!(create?.required||[]).includes(k)&&p.type&&p.type!=='array')p.type=[p.type,'null'];}}
spec.paths[F+'/vehicles'].post.requestBody.content['application/json'].example={label:'Mașina familiei',make:'Toyota',model:'Corolla',year:2022,fuel_type:'hybrid',visibility:'family'};
spec.paths[F+'/vehicles'].post.responses['201'].content['application/json'].example={data:{id:'019a0150-0000-7000-8000-000000000001',family_id:'019a0150-0000-7000-8000-000000000002',label:'Mașina familiei',make:'Toyota',model:'Corolla',year:2022,fuel_type:'hybrid',visibility:'family',status:'active',lock_version:1},meta:{request_id:'req_vehicle_example'}};
spec.paths[F+'/vehicles/{vehicleId}/obligations'].post.responses['409'].content['application/problem+json'].example={type:'urn:mf:problem:expiry-owner-conflict',title:'Scadența are deja o sursă',status:409,code:'expiry_owner_conflict',detail:'Documentul justificativ trebuie să aibă expires_on gol; termenul este administrat de obligația auto.',request_id:'req_example'};
write('openapi.v1.json',JSON.stringify(spec,null,2)+'\n');
write('api.inventory.txt',Object.entries(spec.paths).flatMap(([p,ops])=>Object.entries(ops).map(([m,o])=>`${m.toUpperCase().padEnd(6)} /api/v1${p}  ${o.operationId}`)).join('\n')+'\n');
