<?php
declare(strict_types=1);

// Contract design reference. Split declarations into PSR-4 files when implementing.
// Interfaces run inside one PHP process; no localhost HTTP or private Eloquent models.
namespace App\Platform\Contracts {
    final readonly class ActorContext {
        public function __construct(public string $userId, public string $familyId, public string $membershipId) {}
        // Construct only after checking active membership; recheck on sensitive operations.
    }
    final readonly class SystemContext {
        public function __construct(public string $familyId, public string $handlerKey, public string $eventId) {}
        // Internal factory only, never deserialize from an API request.
    }
    final readonly class ResourceRef {
        public function __construct(public string $familyId, public string $module, public string $type, public string $id) {}
    }
    final readonly class SubjectRef {
        public function __construct(public string $familyId, public string $id, public string $kind) {}
    }
    final readonly class Page {
        /** @param list<object> $items */
        public function __construct(public array $items, public ?string $nextCursor) {}
    }
    final readonly class VisibleCard {
        public function __construct(public ResourceRef $source, public string $title, public string $href, public int $version) {}
    }
    final readonly class ProjectionSnapshot {
        public function __construct(public ResourceRef $source, public int $version, public bool $active, public \DateTimeImmutable $occurredAt) {}
        // No labels, notes, filenames or health content.
    }
    final readonly class DeadlineSnapshot {
        public function __construct(public ResourceRef $source, public int $version, public bool $active, public ?string $dueOn, public string $timezone, public string $ownerMembershipId) {}
        // dueOn is YYYY-MM-DD, not a UTC timestamp. Caller verifies source + tenant.
    }
    interface Authorization {
        public function assertCan(ActorContext $actor, string $action, ResourceRef $resource): void;
        // Dispatch to typed owner policy. Active membership + owner visibility required.
        // owner role manages family; it never bypasses a private resource policy.
    }
    interface AccessRevisions {
        public function bumpForFamily(ActorContext|SystemContext $context): int;
        // Called in the same transaction as a visibility/grant/membership change.
        // Families owns the persisted counter; exports are guarded by this revision.
    }
    interface SubjectRegistry {
        public function reserve(SystemContext|ActorContext $context, string $kind, string $id): SubjectRef;
        // Must share the transaction with creation of the typed owner (same UUID).
        public function assertLinkable(ActorContext $actor, SubjectRef $subject): void;
    }
    interface ResourceProvider {
        public function viewFor(ActorContext $actor, ResourceRef $ref): ?VisibleCard;
        public function searchFor(ActorContext $actor, string $query, int $limit): Page;
        public function projection(SystemContext $system, ResourceRef $ref): ?ProjectionSnapshot;
        public function deadline(SystemContext $system, ResourceRef $ref): ?DeadlineSnapshot;
        // Null means unavailable/deleted/not applicable. No cross-family resolver fallback.
    }
    interface ResourceProviderRegistry {
        public function for(string $module, string $type): ResourceProvider;
        // Static DI registration in the composition root; no network-loaded plugins.
    }
    final readonly class UploadRequest {
        public function __construct(public string $mime, public int $bytes, public string $filename) {}
    }
    final readonly class UploadIntent {
        /** @param array<string,string> $requiredHeaders */
        public function __construct(public string $mediaId, public string $uploadUrl, public array $requiredHeaders, public \DateTimeImmutable $expiresAt) {}
    }
    interface Media {
        public function initiate(ActorContext $actor, UploadRequest $request): UploadIntent;
        public function complete(ActorContext $actor, string $mediaId): void;
        public function claim(ActorContext $actor, string $mediaId, ResourceRef $owner): void;
        // Atomic compare-and-set claim; ready only, same family, uploader/create rights.
        // One canonical owning root per asset; links cannot change/broaden its ACL.
        public function release(SystemContext $system, string $mediaId, ResourceRef $owner): void;
        public function assertDownloadable(ActorContext $actor, string $mediaId): ResourceRef;
    }
    final readonly class EventEnvelope {
        /** @param array<string,scalar|null> $data */
        public function __construct(public string $eventId, public string $name, public int $schemaVersion, public ResourceRef $aggregate, public int $aggregateVersion, public \DateTimeImmutable $occurredAt, public string $correlationId, public array $data = []) {}
    }
    interface Outbox {
        public function append(EventEnvelope $event): void;
        // Requires the caller's open database transaction. Appends a durable fact;
        // no email or other external side effect happens here.
    }
    interface Audit {
        /** @param array<string,scalar|null> $redactedMetadata */
        public function record(ActorContext $actor, string $action, ResourceRef $resource, array $redactedMetadata=[]): void;
    }
    interface Notifications {
        public function deliverDue(SystemContext $system, string $deliveryId): void;
        // Recheck active membership, source availability, current rule/source version
        // and source ACL immediately before sending; external delivery is at-least-once.
    }
}

namespace App\Modules\Kitchen\Contracts {
    use App\Platform\Contracts\{ActorContext, ResourceRef};
    final readonly class RecordCookingSession {
        /** @param list<string> $participantIds */
        public function __construct(public ?string $recipeVersionId, public ?string $freeDishTitle, public \DateTimeImmutable $cookedAt, public array $participantIds, public string $visibility='private') {}
    }
    interface KitchenCommands {
        public function recordSession(ActorContext $actor, RecordCookingSession $command): ResourceRef;
        public function rate(ActorContext $actor, string $sessionId, string $memberId, int $score, ?string $note): void;
    }
}
namespace App\Modules\Pets\Contracts {
    use App\Platform\Contracts\{ActorContext, ResourceRef};
    final readonly class RecordPetCare {
        public function __construct(public string $petId, public string $category, public string $type, public string $occurredOn, public ?string $nextDueOn, public ?string $documentId) {}
    }
    interface PetsCommands {
        public function record(ActorContext $actor, RecordPetCare $command): ResourceRef;
        public function changeNextDue(ActorContext $actor, string $recordId, ?string $dueOn, int $expectedVersion): void;
    }
}
namespace App\Modules\Vehicles\Contracts {
    use App\Platform\Contracts\{ActorContext, ResourceRef};
    final readonly class SaveObligation {
        public function __construct(public string $vehicleId, public string $key, public string $kind, public string $dueOn, public ?string $evidenceDocumentId) {}
    }
    interface VehiclesCommands {
        public function createObligation(ActorContext $actor, SaveObligation $command): ResourceRef;
        public function changeDueOn(ActorContext $actor, string $obligationId, string $dueOn, int $expectedVersion): void;
        public function closeObligation(ActorContext $actor, string $obligationId, int $expectedVersion): void;
    }
}
namespace App\Modules\Documents\Contracts {
    use App\Platform\Contracts\{ActorContext, ResourceRef};
    interface DocumentsReader {
        public function assertReadable(ActorContext $actor, string $documentId): void;
        public function assertEvidenceHasNoIndependentExpiry(ActorContext $actor, string $documentId): void;
        // Owner policy is always evaluated; existence and same family alone are insufficient.
    }
    final readonly class CreateDocument {
        /** @param list<string> $mediaIds @param list<string> $subjectIds */
        public function __construct(public string $title, public string $type, public array $mediaIds, public array $subjectIds, public ?string $expiresOn, public string $visibility='private') {}
    }
    interface DocumentsCommands {
        public function create(ActorContext $actor, CreateDocument $command): ResourceRef;
        public function claimAsEvidence(ActorContext $actor, string $documentId): void;
        // Locks document, checks rights and expires_on IS NULL, then sets expiry_mode=evidence.
        // The mode CHECK and typed FK prevent later expiry edits and binding races.
    }
}
namespace App\Modules\Calendar\Contracts {
    use App\Platform\Contracts\{ActorContext, SystemContext, ResourceRef, DeadlineSnapshot};
    interface CalendarCommands {
        public function reconcileSource(SystemContext $system, DeadlineSnapshot $snapshot): void;
        // Upsert by source ref, only when source_version is newer. Cancel old deliveries.
        // Re-read canonical source; never assume event delivery is in aggregate order.
        public function completeManualTask(ActorContext $actor, string $itemId, int $expectedVersion): void;
    }
}
namespace App\Modules\Memory\Contracts {
    use App\Platform\Contracts\{SystemContext, ProjectionSnapshot, ActorContext, Page};
    interface TimelineWriter {
        public function reconcile(SystemContext $system, ProjectionSnapshot $snapshot): void;
    }
    interface TimelineReader {
        public function forActor(ActorContext $actor, ?string $cursor, int $limit): Page;
        // Batch-resolve authorized source cards. Overfetch within a bounded budget;
        // next cursor points to last scanned row, not merely the last visible row.
    }
}
namespace App\Integrations\Events {
    use App\Platform\Contracts\{EventEnvelope, ResourceRef};
    final readonly class DeadlineChangedV1 {
        public function __construct(public ResourceRef $source, public int $sourceVersion) {}
        public function envelope(string $eventId, string $correlationId): EventEnvelope {
            return new EventEnvelope($eventId, $this->source->module.'.deadline.changed', 1, $this->source, $this->sourceVersion, new \DateTimeImmutable('now',new \DateTimeZone('UTC')), $correlationId);
        }
    }
    // Event catalog, schema v1: kitchen.session.recorded; memory.moment.changed;
    // pets.deadline.changed; vehicles.deadline.changed; documents.deadline.changed;
    // {module}.resource.archived; {module}.visibility.changed; families.membership.revoked.
    // payload has opaque IDs, version and safe flags only. Canonical data is read by a provider.
}
