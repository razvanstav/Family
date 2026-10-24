type PageTransition = Pick<
  ViewTransition,
  "ready" | "finished" | "updateCallbackDone" | "skipTransition"
>;

type NavigationDocument = Pick<Document, "visibilityState"> & {
  documentElement: Pick<HTMLElement, "setAttribute" | "removeAttribute">;
  startViewTransition?: (update: () => void) => PageTransition;
};

export function createPageNavigation<Page>(
  document: NavigationDocument,
  reducedMotion: () => boolean,
  commit: (page: Page, native: boolean) => void,
  currentPage?: () => Page,
) {
  let revision = 0;
  let active: PageTransition | null = null;

  function stop() {
    revision++;
    active?.skipTransition();
    active = null;
    document.documentElement.removeAttribute("data-page-navigation");
  }

  function navigate(page: Page) {
    // Skipping a native transition still runs its update callback.
    stop();
    if (currentPage && Object.is(currentPage(), page)) return;
    if (
      !document.startViewTransition ||
      reducedMotion() ||
      document.visibilityState !== "visible"
    ) {
      commit(page, false);
      return;
    }
    const request = revision;
    let committed = false;
    document.documentElement.setAttribute("data-page-navigation", "");
    try {
      const transition = document.startViewTransition(() => {
        if (request !== revision) return;
        committed = true;
        // The caller must flush React's DOM update synchronously here.
        commit(page, true);
      });
      active = transition;
      // Skipping an animation rejects ready even when its update succeeds.
      void transition.ready.catch(() => {});
      void transition.updateCallbackDone.catch(() => {});
      void transition.finished
        .catch(() => {})
        .finally(() => {
          if (active !== transition) return;
          active = null;
          document.documentElement.removeAttribute("data-page-navigation");
        });
    } catch (error) {
      document.documentElement.removeAttribute("data-page-navigation");
      if (committed) throw error;
      if (request === revision) commit(page, false);
    }
  }

  return { navigate, stop };
}
