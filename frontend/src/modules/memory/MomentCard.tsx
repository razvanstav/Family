import { BookHeart, Image, LockKeyhole, StickyNote, Users } from "lucide-react";
import { visibilityLabel } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { momentKinds, type Moment } from "./memoryTypes";

export function MomentCard({
  base,
  moment,
  lead = false,
  anniversary,
  onOpen,
}: {
  base: string;
  moment: Moment;
  lead?: boolean;
  anniversary?: number;
  onOpen: (moment: Moment) => void;
}) {
  return (
    <button
      className={`moment-card${lead ? " moment-card--lead" : ""}`}
      aria-label={`Deschide amintirea ${moment.title}`}
      onClick={() => onOpen(moment)}
    >
      {moment.photo_status && (
        <PrivatePhoto
          src={`/api/v1${base}/${moment.id}/photo?v=${moment.version}`}
          status={moment.photo_status}
          className="moment-photo"
          placeholder={<Image size={28} aria-hidden="true" />}
        />
      )}
      <div className="moment-card-content">
        {anniversary !== undefined && (
          <span className="moment-anniversary">
            {anniversary === 1
              ? "Acum un an"
              : `Acum ${anniversary}${anniversary < 20 ? " ani" : " de ani"}`}
          </span>
        )}
        <span className="moment-card-top">
          <span className="moment-kind">
            {moment.kind === "moment" ? (
              <BookHeart size={17} aria-hidden="true" />
            ) : (
              <StickyNote size={17} aria-hidden="true" />
            )}
            {momentKinds[moment.kind]}
          </span>
          <time dateTime={moment.occurred_on}>
            {dateLabel(moment.occurred_on, {
              day: "numeric",
              month: "long",
              year: "numeric",
            })}
          </time>
        </span>
        <strong>{moment.title}</strong>
        {moment.body && <span className="moment-excerpt">{moment.body}</span>}
        <span className="moment-card-bottom">
          <span>
            {moment.visibility === "family" ? (
              <Users size={15} aria-hidden="true" />
            ) : (
              <LockKeyhole size={15} aria-hidden="true" />
            )}
            {visibilityLabel[moment.visibility]}
          </span>
          {moment.photos.length > 1 && (
            <span className="moment-photo-count">
              <Image size={14} aria-hidden="true" />
              {moment.photos.length} fotografii
            </span>
          )}
          <span className="moment-card-author">{moment.owner_name}</span>
        </span>
      </div>
    </button>
  );
}
