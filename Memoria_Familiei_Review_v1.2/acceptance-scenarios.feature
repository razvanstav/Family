# Specification examples. These are not executed application tests.
Feature: Tenant isolation and resource privacy
  Scenario Outline: Foreign family identifiers do not reveal data
    Given actor A has an active membership only in family F1
    And resource R and all its children belong to family F2
    When A requests <surface> using the identifier of R
    Then the response is 404 without resource names, filenames or counts
    And no mutation, queued job or object-storage access occurs
    Examples:
      | surface |
      | resource detail and PATCH |
      | nested route with a mismatched parent |
      | grants and attachments |
      | media content |
      | timeline and search |
      | export download |

  Scenario: Composite foreign keys reject cross-family children
    Given an application bug attempts an F1 service log with an F2 vehicle
    When the transaction is committed under the runtime database role
    Then a foreign key violation occurs
    And a direct query for F2 rows in F1's RLS transaction returns no rows

  Scenario: Family owner cannot bypass an adult's private document
    Given B owns a private document D in the same family as owner A
    When A reads D, its media, timeline, search or exports
    Then D is absent from every surface

  Scenario: Revocation invalidates already queued work
    Given A can read a private source through an explicit grant
    And a reminder and an export have already been prepared for A
    When the grant is revoked
    Then subsequent reads and downloads are denied
    And the notification worker rechecks the source and cancels the delivery
    And the prepared export is invalidated before download

  Scenario: A media link never broadens its owning resource's access
    Given asset X is claimed by a private document D
    When a different root attempts to claim X
    Then the atomic claim fails with conflict
    And X can be downloaded only through D's current policy

Feature: Canonical dates and event reliability
  Scenario: ITP has a single editable expiry
    Given an ITP obligation O has due_on 2027-10-20 and evidence document D
    Then D has no independent expires_on value
    And Calendar displays a sourced read-only item for O
    When O changes to 2027-11-01 with If-Match for its current version
    Then one source item is reconciled
    And deliveries for the old source version are cancelled
    And the worker will not send a reminder for 2027-10-20

  Scenario: An independently expiring document cannot silently become duplicate evidence
    Given D has an independent expiry and active reminder
    When D is attached as expiry evidence to a vehicle obligation
    Then the command fails with expiry_owner_conflict
    And a deliberate ownership transfer must clear the old source atomically

  Scenario: Crash after domain commit does not lose a reminder
    Given a transaction commits a pet record and an outbox event
    When the process crashes before queue publication
    Then the poller finds the durable event after restart
    And retry creates one Calendar item keyed by the canonical source

  Scenario: Receipt and projection change commit together
    Given the handler is processing the same event for the second time
    When the projection transaction commits
    Then the handler receipt and projection effect are atomic
    And at most one source projection exists

  Scenario: Reordered events cannot restore an old date
    Given source version 8 changed after source version 7
    When the version 7 event arrives after version 8
    Then the handler re-reads the current source
    And no projection or delivery is downgraded

  Scenario: An uncertain email timeout is not falsely called exactly once
    Given the provider accepted a message but its response timed out
    When retry is considered
    Then a supported provider idempotency key is reused
    Or an unknown state is reconciled before retry according to delivery policy

  Scenario Outline: Date-only obligations keep their meaning across DST
    Given due_on is <date> in Europe/Bucharest
    And the reminder is due at local 09:00
    When the delivery UTC timestamp is computed
    Then the user's displayed due_on remains <date>
    And only one occurrence key is scheduled
    Examples:
      | date |
      | 2027-03-28 |
      | 2027-10-31 |

Feature: Domain integrity and end-to-end utility
  Scenario: Recipe history is immutable
    Given a cooking session refers to recipe version 1
    When version 2 is created
    Then the session still resolves version 1
    And an attempted UPDATE of version 1 is rejected
    And a participant rating is unique per session and member

  Scenario: Cooking to Memory
    When an authorized actor saves a recipe, a session and a rating
    Then Kitchen immediately shows the source
    And the durable handler adds one timeline source reference
    And the visible card is resolved through the current Kitchen policy

  Scenario: A shared cooking session does not reveal a private recipe
    Given a family-visible session references a recipe private to its author
    When another adult reads the session or timeline card
    Then the session's deliberately shared dish caption may be shown
    And private recipe instructions, ingredients and its link remain inaccessible

  Scenario: Pet to Calendar
    When a vaccination with a next_due_on and ready private evidence is saved
    Then Pets owns the record, Documents owns the evidence and Calendar projects the date
    And changing or archiving the source cancels obsolete deliveries

  Scenario: Vehicle to Documents to Calendar
    When a clean upload becomes a private evidence document linked to a vehicle
    And an authorized actor creates its ITP obligation
    Then one canonical document and one canonical obligation exist
    And the document visibility is checked independently from vehicle visibility

  Scenario: Child ratings retain the real actor
    Given a child profile has no login
    When an adult records that child's meal rating
    Then recorded_by_membership_id is the adult
    And a limited user cannot submit a rating for an unrelated member

  Scenario: Simultaneous edits cannot overwrite silently
    Given A and B read lock_version 5
    When A commits an edit and B submits If-Match "5"
    Then B receives 412 and can compare with version 6

  Scenario: Upload declarations are untrusted
    Given a pending upload declares image/jpeg
    When actual bytes are a forbidden type, oversized, malicious or incomplete
    Then the asset never becomes ready or downloadable
    And quota reservation is eventually released

  Scenario: Subject owner is required at commit
    When a pet Subject is inserted without its pets row
    Then the deferred constraint fails at commit
    When both are created atomically with the same family and UUID
    Then the transaction succeeds

  Scenario: Adding Home does not change existing module internals
    When Home registers its Contracts, ResourceProvider, subject kind and own migration
    And adds one home, one document and one maintenance deadline
    Then Memory, Calendar, Search and Media work through existing ports
    And only composition registration and Home-owned files change
    And CI rejects imports of any other module's Infrastructure

  Scenario: Restore does not resurrect erased data
    Given resources were erased after the snapshot used for restore
    When the restored system is still isolated from user traffic
    Then separately retained erasure tombstones are replayed
    And erased objects and projections are absent before traffic is enabled

  Scenario: Revoked membership does not remove access to a rights request
    Given A no longer has an active family membership
    When A submits an identity-scoped privacy request
    Then A can track that request without receiving family data
