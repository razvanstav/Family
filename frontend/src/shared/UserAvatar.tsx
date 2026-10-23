import { useEffect, useState } from "react";
import { initials } from "./api";

export function UserAvatar({
  name,
  photo,
  className = "avatar",
}: {
  name: string;
  photo?: string;
  className?: string;
}) {
  const [failed, setFailed] = useState(false);
  useEffect(() => setFailed(false), [photo]);
  return (
    <span className={className} aria-hidden="true">
      {photo && !failed ? (
        <img src={photo} alt="" onError={() => setFailed(true)} />
      ) : (
        initials(name)
      )}
    </span>
  );
}
