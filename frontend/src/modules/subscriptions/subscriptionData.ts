export type SubscriptionRecurrence = "daily" | "weekly" | "monthly" | "yearly";
export type SubscriptionCurrency = "RON" | "EUR" | "USD";
export type Subscription = {
  id: string;
  service_key: string;
  service_name: string;
  website_url: string | null;
  amount_minor: number | null;
  currency: SubscriptionCurrency | null;
  recurrence: SubscriptionRecurrence | null;
  next_due_on: string | null;
  note: string | null;
  owner_name: string;
  version: number;
  access: "owner" | "family" | "shared" | "invited";
  can_edit: boolean;
  has_credentials: boolean;
  invitation_expires_at?: string | null;
};
export type SubscriptionList = {
  items: Subscription[];
  next_page: number | null;
};
export type SubscriptionCredentials = {
  login: string | null;
  password: string | null;
  pin: string | null;
};
export type SubscriptionShare = {
  id: string;
  email: string;
  name: string;
  status: "pending" | "accepted";
  created_at: string;
  expires_at?: string | null;
};

export const subscriptionServices = [
  { key: "netflix", name: "Netflix", url: "https://www.netflix.com/ro/" },
  { key: "disney", name: "Disney+", url: "https://www.disneyplus.com/ro-ro" },
  { key: "hbo", name: "HBO Max", url: "https://www.hbomax.com/" },
  { key: "prime", name: "Prime Video", url: "https://www.primevideo.com/" },
  { key: "sky", name: "SkyShowtime", url: "https://www.skyshowtime.com/ro" },
  { key: "apple", name: "Apple TV", url: "https://tv.apple.com/ro" },
  {
    key: "digi",
    name: "Digi Online / Digi Sport",
    url: "https://www.digionline.ro/",
  },
  { key: "antena", name: "AntenaPLAY", url: "https://antenaplay.ro/" },
  { key: "voyo", name: "VOYO", url: "https://voyo.protv.ro/" },
  {
    key: "crunchyroll",
    name: "Crunchyroll",
    url: "https://www.crunchyroll.com/",
  },
  {
    key: "youtube",
    name: "YouTube Premium",
    url: "https://www.youtube.com/premium",
  },
  { key: "spotify", name: "Spotify", url: "https://www.spotify.com/ro/" },
] as const;

export const subscriptionRecurrences = [
  { value: "monthly", label: "Lunar" },
  { value: "yearly", label: "Anual" },
  { value: "weekly", label: "Săptămânal" },
  { value: "daily", label: "Zilnic" },
] as const;

export function subscriptionMoney(
  item: Pick<Subscription, "amount_minor" | "currency">,
) {
  return item.amount_minor === null || !item.currency
    ? null
    : new Intl.NumberFormat("ro-RO", {
        style: "currency",
        currency: item.currency,
      }).format(item.amount_minor / 100);
}

export function subscriptionAmountInput(amount: number | null) {
  return amount === null ? "" : (amount / 100).toFixed(2).replace(".", ",");
}

export function subscriptionFrequency(value: SubscriptionRecurrence | null) {
  return (
    subscriptionRecurrences.find((option) => option.value === value)?.label ??
    ""
  );
}

export function subscriptionInvitationExpiry(value: string) {
  return new Intl.DateTimeFormat("ro-RO", {
    timeZone: "Europe/Bucharest",
    day: "numeric",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}
