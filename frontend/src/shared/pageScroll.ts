type ScrollWindow = Pick<Window, "scrollX" | "scrollY" | "scrollTo">;
type ScrollDocument = Pick<Document, "body">;
const locks = new WeakMap<
  ScrollDocument,
  { count: number; restore: () => void }
>();

// overflow:hidden alone does not keep the page still behind a dialog on iOS.
// Share the lock when one dialog opens another and restore the original scroll.
export function lockPageScroll(document: ScrollDocument, window: ScrollWindow) {
  let lock = locks.get(document);
  if (!lock) {
    const { style } = document.body;
    const properties = ["position", "top", "left", "right", "width"];
    const previous = properties.map((property) => [
      style.getPropertyValue(property),
      style.getPropertyPriority(property),
    ]);
    const { scrollX, scrollY } = window;
    style.setProperty("position", "fixed");
    style.setProperty("top", `${-scrollY}px`);
    style.setProperty("left", `${-scrollX}px`);
    style.setProperty("right", "0");
    style.setProperty("width", "100%");
    lock = {
      count: 0,
      restore: () => {
        properties.forEach((property, index) => {
          const [value, priority] = previous[index];
          if (value) style.setProperty(property, value, priority);
          else style.removeProperty(property);
        });
        window.scrollTo({ left: scrollX, top: scrollY, behavior: "instant" });
      },
    };
    locks.set(document, lock);
  }
  lock.count++;
  let released = false;
  return () => {
    if (released) return;
    released = true;
    if (--lock.count === 0) {
      lock.restore();
      locks.delete(document);
    }
  };
}
