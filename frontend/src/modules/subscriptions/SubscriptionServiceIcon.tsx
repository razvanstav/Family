import { Tv } from "lucide-react";

const serviceIcons: Record<string, string> = {
  netflix: new URL(
    "../../assets/subscriptions/netflix.png?no-inline",
    import.meta.url,
  ).href,
  disney: new URL(
    "../../assets/subscriptions/disney.png?no-inline",
    import.meta.url,
  ).href,
  hbo: new URL("../../assets/subscriptions/hbo.png?no-inline", import.meta.url)
    .href,
  prime: new URL(
    "../../assets/subscriptions/prime.png?no-inline",
    import.meta.url,
  ).href,
  sky: new URL("../../assets/subscriptions/sky.png?no-inline", import.meta.url)
    .href,
  apple: new URL(
    "../../assets/subscriptions/apple.png?no-inline",
    import.meta.url,
  ).href,
  digi: new URL(
    "../../assets/subscriptions/digi.png?no-inline",
    import.meta.url,
  ).href,
  antena: new URL(
    "../../assets/subscriptions/antena.png?no-inline",
    import.meta.url,
  ).href,
  voyo: new URL(
    "../../assets/subscriptions/voyo.png?no-inline",
    import.meta.url,
  ).href,
  crunchyroll: new URL(
    "../../assets/subscriptions/crunchyroll.png?no-inline",
    import.meta.url,
  ).href,
  youtube: new URL(
    "../../assets/subscriptions/youtube.png?no-inline",
    import.meta.url,
  ).href,
  spotify: new URL(
    "../../assets/subscriptions/spotify.png?no-inline",
    import.meta.url,
  ).href,
};

export function SubscriptionServiceIcon({
  serviceKey,
}: {
  serviceKey: string;
}) {
  const icon = Object.hasOwn(serviceIcons, serviceKey)
    ? serviceIcons[serviceKey]
    : null;
  return (
    <span className="subscription-service-icon" aria-hidden="true">
      {icon ? (
        <img
          src={icon}
          width={28}
          height={28}
          alt=""
          aria-hidden="true"
          decoding="async"
          draggable={false}
        />
      ) : (
        <Tv size={23} />
      )}
    </span>
  );
}
