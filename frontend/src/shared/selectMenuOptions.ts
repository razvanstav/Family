export type SelectOption = {
  value: string;
  label: string;
  disabled?: boolean;
};

export function getEnabledOptionIndex(
  options: readonly SelectOption[],
  index: number,
  direction: "next" | "previous" | "first" | "last",
): number {
  const length = options.length;
  const step = direction === "previous" || direction === "last" ? -1 : 1;
  const hasCurrent = Number.isInteger(index) && index >= 0 && index < length;
  let candidate =
    direction === "first" || (!hasCurrent && step === 1)
      ? 0
      : direction === "last" || !hasCurrent
        ? length - 1
        : (index + step + length) % length;

  for (let checked = 0; checked < length; checked += 1) {
    if (!options[candidate].disabled) return candidate;
    candidate = (candidate + step + length) % length;
  }
  return -1;
}

function normalizeLabel(value: string): string {
  return value.normalize("NFD").replace(/\p{M}/gu, "").toLocaleLowerCase("ro");
}

export function findSelectOption(
  options: readonly SelectOption[],
  query: string,
  startIndex = 0,
): number {
  const prefix = normalizeLabel(query.trim());
  if (!options.length || !prefix) return -1;
  const start = Number.isFinite(startIndex)
    ? ((Math.trunc(startIndex) % options.length) + options.length) %
      options.length
    : 0;

  for (let checked = 0; checked < options.length; checked += 1) {
    const index = (start + checked) % options.length;
    const option = options[index];
    if (!option.disabled && normalizeLabel(option.label).startsWith(prefix)) {
      return index;
    }
  }
  return -1;
}
