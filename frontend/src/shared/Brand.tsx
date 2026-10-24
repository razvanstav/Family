const logo = new URL("../assets/brand/amora-logo.png", import.meta.url).href;

export function Brand({ compact = false }: { compact?: boolean }) {
  return (
    <span className={`brand${compact ? " brand-compact" : ""}`}>
      <img
        src={logo}
        alt="Amora"
        width={1254}
        height={1254}
        draggable={false}
      />
    </span>
  );
}
