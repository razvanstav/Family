const lei = new Intl.NumberFormat("ro-RO", {
  style: "currency",
  currency: "RON",
  maximumFractionDigits: 2,
});

export const money = (value: string) => lei.format(Number(value));
