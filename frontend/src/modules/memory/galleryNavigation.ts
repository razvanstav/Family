export function swipeStep(dx: number, dy: number): -1 | 0 | 1 {
  if (Math.abs(dx) < 45 || Math.abs(dx) <= Math.abs(dy) * 1.5) return 0;
  return dx < 0 ? 1 : -1;
}

export function galleryIndex(
  index: number,
  step: number,
  count: number,
): number {
  return Math.max(0, Math.min(count - 1, index + step));
}
