import { useEffect, useState, type ReactNode } from "react";

export type PhotoStatus = "ready" | "quarantine" | "rejected" | null;

export function PrivatePhoto({
  src,
  status,
  className,
  placeholder,
  alt = "",
}: {
  src: string;
  status: PhotoStatus;
  className: string;
  placeholder: ReactNode;
  alt?: string;
}) {
  const [failed, setFailed] = useState(false);
  useEffect(() => setFailed(false), [src, status]);
  return (
    <div className={className}>
      {status === "ready" && !failed ? (
        <img
          src={src}
          alt={alt}
          loading="lazy"
          onError={() => setFailed(true)}
        />
      ) : (
        <div className={`${className}-empty`}>
          {placeholder}
          <span>
            {status === "quarantine"
              ? "Fotografie în verificare"
              : status === "rejected"
                ? "Fotografie respinsă"
                : failed
                  ? "Fotografie indisponibilă"
                  : "Fără fotografie"}
          </span>
        </div>
      )}
    </div>
  );
}
