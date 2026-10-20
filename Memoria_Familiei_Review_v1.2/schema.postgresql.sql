-- Memoria Familiei v1.2: proposed custom domain schema, PostgreSQL 18.
-- Design reference, not an installed application. IDs: application-generated UUIDv7.
-- Apply on an empty schema using a migration role. Runtime: non-owner, NOBYPASSRLS.
-- Laravel sessions/tokens/password resets/jobs/failed_jobs use framework migrations.
-- Tenant content operations MUST use a transaction and set_config('app.family_id', ..., true).
BEGIN;

CREATE TABLE users (
  id uuid PRIMARY KEY,
  email text NOT NULL,
  password_hash text NOT NULL,
  locale text NOT NULL DEFAULT 'ro',
  timezone text NOT NULL DEFAULT 'Europe/Bucharest',
  email_verified_at timestamptz,
  disabled_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
CREATE UNIQUE INDEX users_email_uq ON users (lower(email));

CREATE TABLE families (
  id uuid PRIMARY KEY,
  name text NOT NULL,
  timezone text NOT NULL DEFAULT 'Europe/Bucharest',
  created_by uuid REFERENCES users(id),
  authz_revision bigint NOT NULL DEFAULT 1 CHECK(authz_revision>0),
  archived_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE identity_request_keys (
  user_id uuid NOT NULL REFERENCES users(id),
  route_key text NOT NULL,
  request_key text NOT NULL,
  request_hash text NOT NULL,
  result_family_id uuid REFERENCES families(id),
  expires_at timestamptz NOT NULL,
  PRIMARY KEY(user_id,route_key,request_key)
);

CREATE TABLE identity_privacy_requests (
  id uuid PRIMARY KEY,
  user_id uuid NOT NULL REFERENCES users(id),
  family_id uuid REFERENCES families(id),
  request_type text NOT NULL CHECK(request_type IN ('access','export','erasure')),
  status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','review','done','failed')),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE family_memberships (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  user_id uuid NOT NULL REFERENCES users(id),
  role text NOT NULL CHECK (role IN ('owner','adult','limited')),
  status text NOT NULL DEFAULT 'active' CHECK (status IN ('active','revoked')),
  joined_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE(family_id,user_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE family_invitations (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  email text NOT NULL,
  token_hash text NOT NULL UNIQUE,
  role text NOT NULL CHECK(role IN ('adult','limited')),
  expires_at timestamptz NOT NULL,
  accepted_at timestamptz,
  invited_by_membership_id uuid NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE family_invitations ADD FOREIGN KEY (family_id, invited_by_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX family_invitations_family_id_invited_by_membership_id_fk_idx ON family_invitations (family_id, invited_by_membership_id);

CREATE TABLE family_modules (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  module_id text NOT NULL,
  enabled boolean NOT NULL DEFAULT true,
  UNIQUE(family_id,module_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE subjects (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  kind text NOT NULL,
  archived_at timestamptz,
  UNIQUE(family_id,id,kind),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE family_members (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  subject_kind text NOT NULL DEFAULT 'person' CHECK(subject_kind='person'),
  display_name text NOT NULL,
  birth_date date,
  linked_membership_id uuid,
  UNIQUE(family_id,linked_membership_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE family_members ADD FOREIGN KEY (family_id, linked_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX family_members_family_id_linked_membership_id_fk_idx ON family_members (family_id, linked_membership_id);

CREATE TABLE documents (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  title text NOT NULL,
  document_type text NOT NULL,
  issued_on date,
  expires_on date,
  expiry_mode text NOT NULL DEFAULT 'independent' CHECK(expiry_mode IN ('independent','evidence')),
  CHECK(expiry_mode='independent' OR expires_on IS NULL),
  UNIQUE(family_id,id,expiry_mode),
  CHECK(expires_on IS NULL OR issued_on IS NULL OR expires_on >= issued_on),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE documents ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX documents_family_id_owner_membership_id_fk_idx ON documents (family_id, owner_membership_id);

CREATE TABLE media_assets (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  uploader_membership_id uuid NOT NULL,
  object_key text NOT NULL UNIQUE,
  expected_bytes bigint NOT NULL CHECK(expected_bytes BETWEEN 1 AND 20971520),
  actual_bytes bigint CHECK(actual_bytes > 0),
  declared_mime text NOT NULL,
  detected_mime text,
  sha256 text,
  status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','quarantine','ready','rejected','deleting')),
  owner_module text,
  owner_type text,
  owner_id uuid,
  intent_expires_at timestamptz NOT NULL,
  CHECK(num_nonnulls(owner_module,owner_type,owner_id) IN (0,3)),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE media_assets ADD FOREIGN KEY (family_id, uploader_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX media_assets_family_id_uploader_membership_id_fk_idx ON media_assets (family_id, uploader_membership_id);

CREATE TABLE recipes (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  title text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE recipes ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX recipes_family_id_owner_membership_id_fk_idx ON recipes (family_id, owner_membership_id);

CREATE TABLE recipe_versions (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  recipe_id uuid NOT NULL,
  version_no integer NOT NULL CHECK(version_no>0),
  title text NOT NULL,
  ingredients jsonb NOT NULL CHECK(jsonb_typeof(ingredients)='array'),
  instructions text NOT NULL,
  UNIQUE(family_id,recipe_id,version_no),
  UNIQUE(family_id,recipe_id,id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE recipe_versions ADD FOREIGN KEY (family_id, recipe_id) REFERENCES recipes (family_id, id) ;
CREATE INDEX recipe_versions_family_id_recipe_id_fk_idx ON recipe_versions (family_id, recipe_id);

CREATE TABLE cooking_sessions (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  recipe_id uuid,
  recipe_version_id uuid,
  dish_title_snapshot text NOT NULL,
  cooked_at timestamptz NOT NULL,
  cooked_by_member_id uuid,
  notes text,
  CHECK((recipe_id IS NULL) = (recipe_version_id IS NULL)),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE cooking_sessions ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX cooking_sessions_family_id_owner_membership_id_fk_idx ON cooking_sessions (family_id, owner_membership_id);
ALTER TABLE cooking_sessions ADD FOREIGN KEY (family_id, recipe_id, recipe_version_id) REFERENCES recipe_versions (family_id, recipe_id, id) ;
CREATE INDEX cooking_sessions_family_id_recipe_id_recipe_version_id_fk_idx ON cooking_sessions (family_id, recipe_id, recipe_version_id);
ALTER TABLE cooking_sessions ADD FOREIGN KEY (family_id, cooked_by_member_id) REFERENCES family_members (family_id, id) ;
CREATE INDEX cooking_sessions_family_id_cooked_by_member_id_fk_idx ON cooking_sessions (family_id, cooked_by_member_id);

CREATE TABLE cooking_participants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  session_id uuid NOT NULL,
  member_id uuid NOT NULL,
  UNIQUE(family_id,session_id,member_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE cooking_participants ADD FOREIGN KEY (family_id, session_id) REFERENCES cooking_sessions (family_id, id) ;
CREATE INDEX cooking_participants_family_id_session_id_fk_idx ON cooking_participants (family_id, session_id);
ALTER TABLE cooking_participants ADD FOREIGN KEY (family_id, member_id) REFERENCES family_members (family_id, id) ;
CREATE INDEX cooking_participants_family_id_member_id_fk_idx ON cooking_participants (family_id, member_id);

CREATE TABLE meal_ratings (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  session_id uuid NOT NULL,
  member_id uuid NOT NULL,
  recorded_by_membership_id uuid NOT NULL,
  score smallint NOT NULL CHECK(score BETWEEN 1 AND 5),
  notes text,
  UNIQUE(family_id,session_id,member_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE meal_ratings ADD FOREIGN KEY (family_id, session_id, member_id) REFERENCES cooking_participants (family_id, session_id, member_id) ;
CREATE INDEX meal_ratings_family_id_session_id_member_id_fk_idx ON meal_ratings (family_id, session_id, member_id);
ALTER TABLE meal_ratings ADD FOREIGN KEY (family_id, recorded_by_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX meal_ratings_family_id_recorded_by_membership_id_fk_idx ON meal_ratings (family_id, recorded_by_membership_id);

CREATE TABLE pets (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  subject_kind text NOT NULL DEFAULT 'pet' CHECK(subject_kind='pet'),
  name text NOT NULL,
  species text NOT NULL,
  breed text,
  birth_date date,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE pets ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX pets_family_id_owner_membership_id_fk_idx ON pets (family_id, owner_membership_id);

CREATE TABLE pet_weight_logs (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  pet_id uuid NOT NULL,
  measured_at timestamptz NOT NULL,
  weight_kg numeric(7,3) NOT NULL CHECK(weight_kg>0),
  note text,
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE pet_weight_logs ADD FOREIGN KEY (family_id, pet_id) REFERENCES pets (family_id, id) ;
CREATE INDEX pet_weight_logs_family_id_pet_id_fk_idx ON pet_weight_logs (family_id, pet_id);

CREATE TABLE pet_records (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  pet_id uuid NOT NULL,
  category text NOT NULL CHECK(category IN ('health','care')),
  record_type text NOT NULL,
  occurred_on date NOT NULL,
  next_due_on date,
  provider text,
  notes text,
  evidence_document_id uuid,
  cost_minor bigint CHECK(cost_minor>=0),
  currency char(3) NOT NULL DEFAULT 'RON' CHECK(currency ~ '^[A-Z]{3}$'),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  archived_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE pet_records ADD COLUMN evidence_mode text NOT NULL DEFAULT 'evidence' CHECK(evidence_mode='evidence');
ALTER TABLE pet_records ADD FOREIGN KEY (family_id, pet_id) REFERENCES pets (family_id, id) ;
CREATE INDEX pet_records_family_id_pet_id_fk_idx ON pet_records (family_id, pet_id);
ALTER TABLE pet_records ADD FOREIGN KEY (family_id, evidence_document_id, evidence_mode) REFERENCES documents (family_id, id, expiry_mode) ;
CREATE INDEX pet_records_family_id_evidence_document_id_evidence_mode_fk_idx ON pet_records (family_id, evidence_document_id, evidence_mode);

CREATE TABLE vehicles (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  subject_kind text NOT NULL DEFAULT 'vehicle' CHECK(subject_kind='vehicle'),
  label text NOT NULL,
  make text NOT NULL,
  model text NOT NULL,
  year smallint CHECK(year BETWEEN 1886 AND 2200),
  fuel_type text,
  status text NOT NULL DEFAULT 'active' CHECK(status IN ('active','sold','archived')),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE vehicles ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX vehicles_family_id_owner_membership_id_fk_idx ON vehicles (family_id, owner_membership_id);

CREATE TABLE vehicle_identifiers (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  vehicle_id uuid NOT NULL,
  kind text NOT NULL,
  ciphertext bytea NOT NULL,
  key_version integer NOT NULL,
  display_mask text NOT NULL,
  UNIQUE(family_id,vehicle_id,kind),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE vehicle_identifiers ADD FOREIGN KEY (family_id, vehicle_id) REFERENCES vehicles (family_id, id) ;
CREATE INDEX vehicle_identifiers_family_id_vehicle_id_fk_idx ON vehicle_identifiers (family_id, vehicle_id);

CREATE TABLE vehicle_odometer (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  vehicle_id uuid NOT NULL,
  reading_km integer NOT NULL CHECK(reading_km>=0),
  recorded_at timestamptz NOT NULL,
  note text,
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE vehicle_odometer ADD FOREIGN KEY (family_id, vehicle_id) REFERENCES vehicles (family_id, id) ;
CREATE INDEX vehicle_odometer_family_id_vehicle_id_fk_idx ON vehicle_odometer (family_id, vehicle_id);

CREATE TABLE vehicle_service_logs (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  vehicle_id uuid NOT NULL,
  serviced_on date NOT NULL,
  service_type text NOT NULL,
  odometer_km integer CHECK(odometer_km>=0),
  provider text,
  cost_minor bigint CHECK(cost_minor>=0),
  currency char(3) NOT NULL DEFAULT 'RON' CHECK(currency ~ '^[A-Z]{3}$'),
  notes text,
  evidence_document_id uuid,
  evidence_mode text NOT NULL DEFAULT 'evidence' CHECK(evidence_mode='evidence'),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE vehicle_service_logs ADD FOREIGN KEY (family_id, vehicle_id) REFERENCES vehicles (family_id, id) ;
CREATE INDEX vehicle_service_logs_family_id_vehicle_id_fk_idx ON vehicle_service_logs (family_id, vehicle_id);
ALTER TABLE vehicle_service_logs ADD FOREIGN KEY (family_id, evidence_document_id, evidence_mode) REFERENCES documents (family_id, id, expiry_mode) ;
CREATE INDEX vehicle_service_logs_family_id_evidence_document_id_53b6585583 ON vehicle_service_logs (family_id, evidence_document_id, evidence_mode);

CREATE TABLE vehicle_fuel_logs (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  vehicle_id uuid NOT NULL,
  fueled_at timestamptz NOT NULL,
  liters numeric(9,3) NOT NULL CHECK(liters>0),
  total_minor bigint CHECK(total_minor>=0),
  currency char(3) NOT NULL DEFAULT 'RON' CHECK(currency ~ '^[A-Z]{3}$'),
  odometer_km integer CHECK(odometer_km>=0),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE vehicle_fuel_logs ADD FOREIGN KEY (family_id, vehicle_id) REFERENCES vehicles (family_id, id) ;
CREATE INDEX vehicle_fuel_logs_family_id_vehicle_id_fk_idx ON vehicle_fuel_logs (family_id, vehicle_id);

CREATE TABLE vehicle_obligations (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  vehicle_id uuid NOT NULL,
  obligation_key text NOT NULL,
  kind text NOT NULL CHECK(kind IN ('itp','rca','rovinieta','maintenance','other')),
  due_on date NOT NULL,
  evidence_document_id uuid,
  evidence_mode text NOT NULL DEFAULT 'evidence' CHECK(evidence_mode='evidence'),
  notes text,
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  closed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE vehicle_obligations ADD FOREIGN KEY (family_id, vehicle_id) REFERENCES vehicles (family_id, id) ;
CREATE INDEX vehicle_obligations_family_id_vehicle_id_fk_idx ON vehicle_obligations (family_id, vehicle_id);
ALTER TABLE vehicle_obligations ADD FOREIGN KEY (family_id, evidence_document_id, evidence_mode) REFERENCES documents (family_id, id, expiry_mode) ;
CREATE INDEX vehicle_obligations_family_id_evidence_document_id__2016d8a45a ON vehicle_obligations (family_id, evidence_document_id, evidence_mode);
CREATE UNIQUE INDEX vehicle_obligation_active_uq ON vehicle_obligations(family_id,vehicle_id,obligation_key) WHERE closed_at IS NULL;

CREATE TABLE moments (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  kind text NOT NULL DEFAULT 'memory' CHECK(kind IN ('memory','note')),
  title text NOT NULL,
  body text,
  occurred_at timestamptz NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE moments ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX moments_family_id_owner_membership_id_fk_idx ON moments (family_id, owner_membership_id);

CREATE TABLE calendar_items (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  owner_membership_id uuid NOT NULL,
  visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','family','selected')),
  lock_version bigint NOT NULL DEFAULT 1 CHECK (lock_version > 0),
  archived_at timestamptz,
  origin text NOT NULL CHECK(origin IN ('manual','source')),
  item_type text NOT NULL CHECK(item_type IN ('task','event','deadline')),
  title text,
  starts_at timestamptz,
  ends_at timestamptz,
  due_on date,
  timezone text NOT NULL DEFAULT 'Europe/Bucharest',
  assignee_member_id uuid,
  status text NOT NULL DEFAULT 'open' CHECK(status IN ('open','done','cancelled')),
  source_module text,
  source_type text,
  source_id uuid,
  source_version bigint,
  UNIQUE(family_id,source_module,source_type,source_id),
  CHECK((origin='manual' AND title IS NOT NULL AND num_nonnulls(source_module,source_type,source_id,source_version)=0) OR (origin='source' AND title IS NULL AND num_nonnulls(source_module,source_type,source_id,source_version)=4)),
  CHECK(ends_at IS NULL OR (starts_at IS NOT NULL AND ends_at>=starts_at)),
  CHECK((item_type='event' AND starts_at IS NOT NULL AND due_on IS NULL) OR (item_type='deadline' AND due_on IS NOT NULL AND starts_at IS NULL) OR (item_type='task' AND starts_at IS NULL)),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE calendar_items ADD FOREIGN KEY (family_id, owner_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX calendar_items_family_id_owner_membership_id_fk_idx ON calendar_items (family_id, owner_membership_id);
ALTER TABLE calendar_items ADD FOREIGN KEY (family_id, assignee_member_id) REFERENCES family_members (family_id, id) ;
CREATE INDEX calendar_items_family_id_assignee_member_id_fk_idx ON calendar_items (family_id, assignee_member_id);

CREATE TABLE document_subjects (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  document_id uuid NOT NULL,
  subject_id uuid NOT NULL,
  UNIQUE(family_id,document_id,subject_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE document_subjects ADD FOREIGN KEY (family_id, document_id) REFERENCES documents (family_id, id) ;
CREATE INDEX document_subjects_family_id_document_id_fk_idx ON document_subjects (family_id, document_id);
ALTER TABLE document_subjects ADD FOREIGN KEY (family_id, subject_id) REFERENCES subjects (family_id, id) ;
CREATE INDEX document_subjects_family_id_subject_id_fk_idx ON document_subjects (family_id, subject_id);

CREATE TABLE moment_subjects (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  moment_id uuid NOT NULL,
  subject_id uuid NOT NULL,
  UNIQUE(family_id,moment_id,subject_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE moment_subjects ADD FOREIGN KEY (family_id, moment_id) REFERENCES moments (family_id, id) ;
CREATE INDEX moment_subjects_family_id_moment_id_fk_idx ON moment_subjects (family_id, moment_id);
ALTER TABLE moment_subjects ADD FOREIGN KEY (family_id, subject_id) REFERENCES subjects (family_id, id) ;
CREATE INDEX moment_subjects_family_id_subject_id_fk_idx ON moment_subjects (family_id, subject_id);

CREATE TABLE calendar_subjects (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  calendar_item_id uuid NOT NULL,
  subject_id uuid NOT NULL,
  UNIQUE(family_id,calendar_item_id,subject_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE calendar_subjects ADD FOREIGN KEY (family_id, calendar_item_id) REFERENCES calendar_items (family_id, id) ;
CREATE INDEX calendar_subjects_family_id_calendar_item_id_fk_idx ON calendar_subjects (family_id, calendar_item_id);
ALTER TABLE calendar_subjects ADD FOREIGN KEY (family_id, subject_id) REFERENCES subjects (family_id, id) ;
CREATE INDEX calendar_subjects_family_id_subject_id_fk_idx ON calendar_subjects (family_id, subject_id);

CREATE TABLE document_media (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  document_id uuid NOT NULL,
  media_id uuid NOT NULL,
  sort_order integer NOT NULL DEFAULT 0 CHECK(sort_order>=0),
  UNIQUE(family_id,media_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE document_media ADD FOREIGN KEY (family_id, document_id) REFERENCES documents (family_id, id) ;
CREATE INDEX document_media_family_id_document_id_fk_idx ON document_media (family_id, document_id);
ALTER TABLE document_media ADD FOREIGN KEY (family_id, media_id) REFERENCES media_assets (family_id, id) ;
CREATE INDEX document_media_family_id_media_id_fk_idx ON document_media (family_id, media_id);

CREATE TABLE moment_media (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  moment_id uuid NOT NULL,
  media_id uuid NOT NULL,
  sort_order integer NOT NULL DEFAULT 0 CHECK(sort_order>=0),
  UNIQUE(family_id,media_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE moment_media ADD FOREIGN KEY (family_id, moment_id) REFERENCES moments (family_id, id) ;
CREATE INDEX moment_media_family_id_moment_id_fk_idx ON moment_media (family_id, moment_id);
ALTER TABLE moment_media ADD FOREIGN KEY (family_id, media_id) REFERENCES media_assets (family_id, id) ;
CREATE INDEX moment_media_family_id_media_id_fk_idx ON moment_media (family_id, media_id);

CREATE TABLE cooking_session_media (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  session_id uuid NOT NULL,
  media_id uuid NOT NULL,
  sort_order integer NOT NULL DEFAULT 0 CHECK(sort_order>=0),
  UNIQUE(family_id,media_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE cooking_session_media ADD FOREIGN KEY (family_id, session_id) REFERENCES cooking_sessions (family_id, id) ;
CREATE INDEX cooking_session_media_family_id_session_id_fk_idx ON cooking_session_media (family_id, session_id);
ALTER TABLE cooking_session_media ADD FOREIGN KEY (family_id, media_id) REFERENCES media_assets (family_id, id) ;
CREATE INDEX cooking_session_media_family_id_media_id_fk_idx ON cooking_session_media (family_id, media_id);

CREATE TABLE calendar_attendees (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  calendar_item_id uuid NOT NULL,
  member_id uuid NOT NULL,
  UNIQUE(family_id,calendar_item_id,member_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE calendar_attendees ADD FOREIGN KEY (family_id, calendar_item_id) REFERENCES calendar_items (family_id, id) ;
CREATE INDEX calendar_attendees_family_id_calendar_item_id_fk_idx ON calendar_attendees (family_id, calendar_item_id);
ALTER TABLE calendar_attendees ADD FOREIGN KEY (family_id, member_id) REFERENCES family_members (family_id, id) ;
CREATE INDEX calendar_attendees_family_id_member_id_fk_idx ON calendar_attendees (family_id, member_id);

CREATE TABLE recipes_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  recipe_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,recipe_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE recipes_grants ADD FOREIGN KEY (family_id, recipe_id) REFERENCES recipes (family_id, id) ;
CREATE INDEX recipes_grants_family_id_recipe_id_fk_idx ON recipes_grants (family_id, recipe_id);
ALTER TABLE recipes_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX recipes_grants_family_id_membership_id_fk_idx ON recipes_grants (family_id, membership_id);

CREATE TABLE cooking_sessions_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  session_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,session_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE cooking_sessions_grants ADD FOREIGN KEY (family_id, session_id) REFERENCES cooking_sessions (family_id, id) ;
CREATE INDEX cooking_sessions_grants_family_id_session_id_fk_idx ON cooking_sessions_grants (family_id, session_id);
ALTER TABLE cooking_sessions_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX cooking_sessions_grants_family_id_membership_id_fk_idx ON cooking_sessions_grants (family_id, membership_id);

CREATE TABLE pets_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  pet_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,pet_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE pets_grants ADD FOREIGN KEY (family_id, pet_id) REFERENCES pets (family_id, id) ;
CREATE INDEX pets_grants_family_id_pet_id_fk_idx ON pets_grants (family_id, pet_id);
ALTER TABLE pets_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX pets_grants_family_id_membership_id_fk_idx ON pets_grants (family_id, membership_id);

CREATE TABLE vehicles_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  vehicle_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,vehicle_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE vehicles_grants ADD FOREIGN KEY (family_id, vehicle_id) REFERENCES vehicles (family_id, id) ;
CREATE INDEX vehicles_grants_family_id_vehicle_id_fk_idx ON vehicles_grants (family_id, vehicle_id);
ALTER TABLE vehicles_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX vehicles_grants_family_id_membership_id_fk_idx ON vehicles_grants (family_id, membership_id);

CREATE TABLE documents_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  document_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,document_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE documents_grants ADD FOREIGN KEY (family_id, document_id) REFERENCES documents (family_id, id) ;
CREATE INDEX documents_grants_family_id_document_id_fk_idx ON documents_grants (family_id, document_id);
ALTER TABLE documents_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX documents_grants_family_id_membership_id_fk_idx ON documents_grants (family_id, membership_id);

CREATE TABLE moments_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  moment_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,moment_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE moments_grants ADD FOREIGN KEY (family_id, moment_id) REFERENCES moments (family_id, id) ;
CREATE INDEX moments_grants_family_id_moment_id_fk_idx ON moments_grants (family_id, moment_id);
ALTER TABLE moments_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX moments_grants_family_id_membership_id_fk_idx ON moments_grants (family_id, membership_id);

CREATE TABLE calendar_items_grants (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  calendar_item_id uuid NOT NULL,
  membership_id uuid NOT NULL,
  permission text NOT NULL CHECK(permission IN ('view','edit')),
  expires_at timestamptz,
  UNIQUE(family_id,calendar_item_id,membership_id,permission),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE calendar_items_grants ADD FOREIGN KEY (family_id, calendar_item_id) REFERENCES calendar_items (family_id, id) ;
CREATE INDEX calendar_items_grants_family_id_calendar_item_id_fk_idx ON calendar_items_grants (family_id, calendar_item_id);
ALTER TABLE calendar_items_grants ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX calendar_items_grants_family_id_membership_id_fk_idx ON calendar_items_grants (family_id, membership_id);

CREATE TABLE reminder_rules (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  calendar_item_id uuid NOT NULL,
  recipient_membership_id uuid NOT NULL,
  offset_days integer NOT NULL CHECK(offset_days BETWEEN 0 AND 3650),
  local_time time NOT NULL DEFAULT '09:00',
  channel text NOT NULL CHECK(channel IN ('in_app','email')),
  enabled boolean NOT NULL DEFAULT true,
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0),
  UNIQUE(family_id,calendar_item_id,recipient_membership_id,offset_days,channel),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE reminder_rules ADD FOREIGN KEY (family_id, calendar_item_id) REFERENCES calendar_items (family_id, id) ;
CREATE INDEX reminder_rules_family_id_calendar_item_id_fk_idx ON reminder_rules (family_id, calendar_item_id);
ALTER TABLE reminder_rules ADD FOREIGN KEY (family_id, recipient_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX reminder_rules_family_id_recipient_membership_id_fk_idx ON reminder_rules (family_id, recipient_membership_id);

CREATE TABLE notification_deliveries (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  rule_id uuid NOT NULL,
  recipient_membership_id uuid NOT NULL,
  occurrence_key text NOT NULL,
  scheduled_at timestamptz NOT NULL,
  source_version bigint NOT NULL,
  rule_version bigint NOT NULL,
  channel text NOT NULL CHECK(channel IN ('in_app','email')),
  status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','leased','sent','cancelled','failed','unknown')),
  lease_until timestamptz,
  attempts integer NOT NULL DEFAULT 0 CHECK(attempts>=0),
  provider_id text,
  UNIQUE(family_id,rule_id,recipient_membership_id,channel,occurrence_key),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE notification_deliveries ADD FOREIGN KEY (family_id, rule_id) REFERENCES reminder_rules (family_id, id) ;
CREATE INDEX notification_deliveries_family_id_rule_id_fk_idx ON notification_deliveries (family_id, rule_id);
ALTER TABLE notification_deliveries ADD FOREIGN KEY (family_id, recipient_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX notification_deliveries_family_id_recipient_members_fc79ec0205 ON notification_deliveries (family_id, recipient_membership_id);

CREATE TABLE timeline_items (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  source_module text NOT NULL,
  source_type text NOT NULL,
  source_id uuid NOT NULL,
  source_version bigint NOT NULL,
  occurred_at timestamptz NOT NULL,
  UNIQUE(family_id,source_module,source_type,source_id),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE outbox_messages (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  event_name text NOT NULL,
  event_version integer NOT NULL CHECK(event_version>0),
  aggregate_type text NOT NULL,
  aggregate_id uuid NOT NULL,
  aggregate_version bigint NOT NULL,
  payload jsonb NOT NULL DEFAULT '{}'::jsonb,
  occurred_at timestamptz NOT NULL DEFAULT now(),
  correlation_id text NOT NULL,
  fanout_at timestamptz,
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE outbox_deliveries (
  event_id uuid NOT NULL REFERENCES outbox_messages(id) ON DELETE CASCADE,
  handler_key text NOT NULL,
  status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','leased','done','failed')),
  available_at timestamptz NOT NULL DEFAULT now(),
  lease_until timestamptz,
  attempts integer NOT NULL DEFAULT 0 CHECK(attempts>=0),
  last_error_code text,
  PRIMARY KEY(event_id,handler_key)
);

CREATE TABLE request_idempotency (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  membership_id uuid NOT NULL,
  request_key text NOT NULL,
  method text NOT NULL,
  route_key text NOT NULL,
  request_hash text NOT NULL,
  resource_module text,
  resource_type text,
  resource_id uuid,
  response_status integer,
  expires_at timestamptz NOT NULL,
  UNIQUE(family_id,membership_id,method,route_key,request_key),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE request_idempotency ADD FOREIGN KEY (family_id, membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX request_idempotency_family_id_membership_id_fk_idx ON request_idempotency (family_id, membership_id);

CREATE TABLE activity_audit (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  actor_user_id uuid REFERENCES users(id),
  action text NOT NULL,
  resource_module text NOT NULL,
  resource_type text NOT NULL,
  resource_id uuid,
  metadata_redacted jsonb NOT NULL DEFAULT '{}'::jsonb,
  occurred_at timestamptz NOT NULL DEFAULT now(),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE export_requests (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  requested_by_membership_id uuid NOT NULL,
  status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','running','ready','expired','failed')),
  guard_authz_revision bigint,
  expires_at timestamptz,
  output_storage_key text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE export_requests ADD FOREIGN KEY (family_id, requested_by_membership_id) REFERENCES family_memberships (family_id, id) ;
CREATE INDEX export_requests_family_id_requested_by_membership_id_fk_idx ON export_requests (family_id, requested_by_membership_id);

CREATE TABLE erasure_requests (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL REFERENCES families(id),
  UNIQUE (family_id, id),
  requested_by_user_id uuid REFERENCES users(id),
  scope text NOT NULL,
  target_id uuid,
  status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','review','running','done','failed')),
  completed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);

CREATE TABLE erasure_tombstones (
  id uuid PRIMARY KEY,
  family_id uuid NOT NULL,
  resource_module text NOT NULL,
  resource_type text NOT NULL,
  resource_id uuid NOT NULL,
  erased_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE(family_id,resource_module,resource_type,resource_id),
  lock_version bigint NOT NULL DEFAULT 1 CHECK(lock_version>0)
);
ALTER TABLE family_members ADD FOREIGN KEY (family_id, id, subject_kind) REFERENCES subjects (family_id, id, kind) DEFERRABLE INITIALLY DEFERRED;
CREATE INDEX family_members_family_id_id_subject_kind_fk_idx ON family_members (family_id, id, subject_kind);
ALTER TABLE pets ADD FOREIGN KEY (family_id, id, subject_kind) REFERENCES subjects (family_id, id, kind) DEFERRABLE INITIALLY DEFERRED;
CREATE INDEX pets_family_id_id_subject_kind_fk_idx ON pets (family_id, id, subject_kind);
ALTER TABLE vehicles ADD FOREIGN KEY (family_id, id, subject_kind) REFERENCES subjects (family_id, id, kind) DEFERRABLE INITIALLY DEFERRED;
CREATE INDEX vehicles_family_id_id_subject_kind_fk_idx ON vehicles (family_id, id, subject_kind);

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
CREATE CONSTRAINT TRIGGER family_members_subject_owner AFTER INSERT OR UPDATE ON subjects DEFERRABLE INITIALLY DEFERRED FOR EACH ROW WHEN (NEW.kind='person') EXECUTE FUNCTION mf_subject_owner_required('family_members','person');
CREATE CONSTRAINT TRIGGER family_members_owner_deleted AFTER DELETE OR UPDATE ON family_members DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION mf_subject_owner_required('family_members','person');
CREATE CONSTRAINT TRIGGER pets_subject_owner AFTER INSERT OR UPDATE ON subjects DEFERRABLE INITIALLY DEFERRED FOR EACH ROW WHEN (NEW.kind='pet') EXECUTE FUNCTION mf_subject_owner_required('pets','pet');
CREATE CONSTRAINT TRIGGER pets_owner_deleted AFTER DELETE OR UPDATE ON pets DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION mf_subject_owner_required('pets','pet');
CREATE CONSTRAINT TRIGGER vehicles_subject_owner AFTER INSERT OR UPDATE ON subjects DEFERRABLE INITIALLY DEFERRED FOR EACH ROW WHEN (NEW.kind='vehicle') EXECUTE FUNCTION mf_subject_owner_required('vehicles','vehicle');
CREATE CONSTRAINT TRIGGER vehicles_owner_deleted AFTER DELETE OR UPDATE ON vehicles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION mf_subject_owner_required('vehicles','vehicle');

CREATE TABLE subject_kinds (
  kind text PRIMARY KEY
);
INSERT INTO subject_kinds VALUES ('person'),('pet'),('vehicle');
ALTER TABLE subjects ADD FOREIGN KEY(kind) REFERENCES subject_kinds(kind);
-- Owner modules also install a trigger preventing UPDATE/DELETE of immutable recipe versions.
CREATE FUNCTION mf_recipe_version_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Recipe versions are immutable; use audited erasure workflow for physical deletion' USING ERRCODE='23514'; END $$;
CREATE TRIGGER recipe_version_no_update BEFORE UPDATE ON recipe_versions FOR EACH ROW EXECUTE FUNCTION mf_recipe_version_immutable();
CREATE TRIGGER family_memberships_identity_immutable BEFORE UPDATE ON family_memberships FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER family_invitations_identity_immutable BEFORE UPDATE ON family_invitations FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER family_modules_identity_immutable BEFORE UPDATE ON family_modules FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER subjects_identity_immutable BEFORE UPDATE ON subjects FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER family_members_identity_immutable BEFORE UPDATE ON family_members FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER documents_identity_immutable BEFORE UPDATE ON documents FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER media_assets_identity_immutable BEFORE UPDATE ON media_assets FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER recipes_identity_immutable BEFORE UPDATE ON recipes FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER recipe_versions_identity_immutable BEFORE UPDATE ON recipe_versions FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER cooking_sessions_identity_immutable BEFORE UPDATE ON cooking_sessions FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER cooking_participants_identity_immutable BEFORE UPDATE ON cooking_participants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER meal_ratings_identity_immutable BEFORE UPDATE ON meal_ratings FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER pets_identity_immutable BEFORE UPDATE ON pets FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER pet_weight_logs_identity_immutable BEFORE UPDATE ON pet_weight_logs FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER pet_records_identity_immutable BEFORE UPDATE ON pet_records FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicles_identity_immutable BEFORE UPDATE ON vehicles FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicle_identifiers_identity_immutable BEFORE UPDATE ON vehicle_identifiers FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicle_odometer_identity_immutable BEFORE UPDATE ON vehicle_odometer FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicle_service_logs_identity_immutable BEFORE UPDATE ON vehicle_service_logs FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicle_fuel_logs_identity_immutable BEFORE UPDATE ON vehicle_fuel_logs FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicle_obligations_identity_immutable BEFORE UPDATE ON vehicle_obligations FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER moments_identity_immutable BEFORE UPDATE ON moments FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER calendar_items_identity_immutable BEFORE UPDATE ON calendar_items FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER document_subjects_identity_immutable BEFORE UPDATE ON document_subjects FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER moment_subjects_identity_immutable BEFORE UPDATE ON moment_subjects FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER calendar_subjects_identity_immutable BEFORE UPDATE ON calendar_subjects FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER document_media_identity_immutable BEFORE UPDATE ON document_media FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER moment_media_identity_immutable BEFORE UPDATE ON moment_media FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER cooking_session_media_identity_immutable BEFORE UPDATE ON cooking_session_media FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER calendar_attendees_identity_immutable BEFORE UPDATE ON calendar_attendees FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER recipes_grants_identity_immutable BEFORE UPDATE ON recipes_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER cooking_sessions_grants_identity_immutable BEFORE UPDATE ON cooking_sessions_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER pets_grants_identity_immutable BEFORE UPDATE ON pets_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER vehicles_grants_identity_immutable BEFORE UPDATE ON vehicles_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER documents_grants_identity_immutable BEFORE UPDATE ON documents_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER moments_grants_identity_immutable BEFORE UPDATE ON moments_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER calendar_items_grants_identity_immutable BEFORE UPDATE ON calendar_items_grants FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER reminder_rules_identity_immutable BEFORE UPDATE ON reminder_rules FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER notification_deliveries_identity_immutable BEFORE UPDATE ON notification_deliveries FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER timeline_items_identity_immutable BEFORE UPDATE ON timeline_items FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER outbox_messages_identity_immutable BEFORE UPDATE ON outbox_messages FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER request_idempotency_identity_immutable BEFORE UPDATE ON request_idempotency FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER activity_audit_identity_immutable BEFORE UPDATE ON activity_audit FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER export_requests_identity_immutable BEFORE UPDATE ON export_requests FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
CREATE TRIGGER erasure_requests_identity_immutable BEFORE UPDATE ON erasure_requests FOR EACH ROW EXECUTE FUNCTION mf_immutable_identity();
ALTER TABLE family_invitations ENABLE ROW LEVEL SECURITY;
ALTER TABLE family_invitations FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON family_invitations USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE family_modules ENABLE ROW LEVEL SECURITY;
ALTER TABLE family_modules FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON family_modules USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE subjects ENABLE ROW LEVEL SECURITY;
ALTER TABLE subjects FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON subjects USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE family_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE family_members FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON family_members USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE documents FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON documents USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE media_assets ENABLE ROW LEVEL SECURITY;
ALTER TABLE media_assets FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON media_assets USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE recipes ENABLE ROW LEVEL SECURITY;
ALTER TABLE recipes FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON recipes USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE recipe_versions ENABLE ROW LEVEL SECURITY;
ALTER TABLE recipe_versions FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON recipe_versions USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE cooking_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE cooking_sessions FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON cooking_sessions USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE cooking_participants ENABLE ROW LEVEL SECURITY;
ALTER TABLE cooking_participants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON cooking_participants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE meal_ratings ENABLE ROW LEVEL SECURITY;
ALTER TABLE meal_ratings FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON meal_ratings USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE pets ENABLE ROW LEVEL SECURITY;
ALTER TABLE pets FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON pets USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE pet_weight_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE pet_weight_logs FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON pet_weight_logs USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE pet_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE pet_records FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON pet_records USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicles ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicles FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicles USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicle_identifiers ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicle_identifiers FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicle_identifiers USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicle_odometer ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicle_odometer FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicle_odometer USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicle_service_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicle_service_logs FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicle_service_logs USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicle_fuel_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicle_fuel_logs FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicle_fuel_logs USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicle_obligations ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicle_obligations FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicle_obligations USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE moments ENABLE ROW LEVEL SECURITY;
ALTER TABLE moments FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON moments USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE calendar_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE calendar_items FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON calendar_items USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE document_subjects ENABLE ROW LEVEL SECURITY;
ALTER TABLE document_subjects FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON document_subjects USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE moment_subjects ENABLE ROW LEVEL SECURITY;
ALTER TABLE moment_subjects FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON moment_subjects USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE calendar_subjects ENABLE ROW LEVEL SECURITY;
ALTER TABLE calendar_subjects FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON calendar_subjects USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE document_media ENABLE ROW LEVEL SECURITY;
ALTER TABLE document_media FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON document_media USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE moment_media ENABLE ROW LEVEL SECURITY;
ALTER TABLE moment_media FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON moment_media USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE cooking_session_media ENABLE ROW LEVEL SECURITY;
ALTER TABLE cooking_session_media FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON cooking_session_media USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE calendar_attendees ENABLE ROW LEVEL SECURITY;
ALTER TABLE calendar_attendees FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON calendar_attendees USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE recipes_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE recipes_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON recipes_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE cooking_sessions_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE cooking_sessions_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON cooking_sessions_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE pets_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE pets_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON pets_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE vehicles_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicles_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON vehicles_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE documents_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE documents_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON documents_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE moments_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE moments_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON moments_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE calendar_items_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE calendar_items_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON calendar_items_grants USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE reminder_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE reminder_rules FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON reminder_rules USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE notification_deliveries ENABLE ROW LEVEL SECURITY;
ALTER TABLE notification_deliveries FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON notification_deliveries USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE timeline_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE timeline_items FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON timeline_items USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE request_idempotency ENABLE ROW LEVEL SECURITY;
ALTER TABLE request_idempotency FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON request_idempotency USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE activity_audit ENABLE ROW LEVEL SECURITY;
ALTER TABLE activity_audit FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON activity_audit USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE export_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE export_requests FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON export_requests USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);
ALTER TABLE erasure_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE erasure_requests FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_scope ON erasure_requests USING (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid) WITH CHECK (family_id=NULLIF(current_setting('app.family_id',true),'')::uuid);

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
CREATE INDEX recipes_fts_idx ON recipes USING gin(to_tsvector('simple',title));
CREATE INDEX documents_fts_idx ON documents USING gin(to_tsvector('simple',title));
CREATE INDEX moments_fts_idx ON moments USING gin(to_tsvector('simple',title));
CREATE INDEX pets_fts_idx ON pets USING gin(to_tsvector('simple',name));
CREATE INDEX vehicles_fts_idx ON vehicles USING gin(to_tsvector('simple',label));
COMMIT;
