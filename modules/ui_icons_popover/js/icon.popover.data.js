/**
 * @file
 * Icon data the popover selector loads, searches and remembers.
 */
((Drupal) => {
  /**
   * Icons per preview request, under the server cap.
   *
   * @see \Drupal\ui_icons_popover\Controller\IconPopoverController::PREVIEWS_MAX
   */
  const BATCH_SIZE = 60;

  /**
   * Where the recently picked icons are kept, for the whole site.
   */
  const RECENT_KEY = 'ui_icons_popover.recent';

  /**
   * How many recently picked icons are kept.
   */
  const RECENT_MAX = 24;

  /**
   * Index requests, keyed by url and allowed packs.
   *
   * @type {Map<string, Promise<object>>}
   */
  const indexes = new Map();

  /**
   * Preview requests in flight, keyed by icon full id.
   *
   * @type {Map<string, Promise<void>>}
   */
  const inflight = new Map();

  /**
   * Adds query arguments to an url.
   *
   * @param {string} url
   *   The url, absolute or root relative.
   * @param {Array<Array<string>>} params
   *   The arguments as key and value pairs, a key may repeat.
   *
   * @return {string}
   *   The url with its query.
   */
  const withQuery = (url, params) => {
    const target = new URL(url, window.location.origin);
    params.forEach(([key, value]) => target.searchParams.append(key, value));
    return target.toString();
  };

  /**
   * Fetches JSON, rejecting on any response that is not a success.
   *
   * @param {string} url
   *   The url to get.
   *
   * @return {Promise<object>}
   *   The decoded response.
   */
  const getJson = async (url) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
      throw new Error(`${response.status} ${url}`);
    }
    return response.json();
  };

  /**
   * The query argument limiting a request to some packs.
   *
   * @param {string} allowed
   *   The pack ids joined with `+`, empty for all packs.
   *
   * @return {Array<Array<string>>}
   *   The argument, or none.
   */
  const allowedParams = (allowed) =>
    allowed ? [['allowed_icon_pack', allowed]] : [];

  /**
   * Reads the recently picked icon ids, most recent first.
   *
   * @return {Array<string>}
   *   The ids, empty when the storage is unavailable or holds anything else.
   */
  const readRecent = () => {
    try {
      const ids = JSON.parse(window.localStorage.getItem(RECENT_KEY));
      return Array.isArray(ids)
        ? ids.filter((id) => typeof id === 'string')
        : [];
    } catch (error) {
      return [];
    }
  };

  /**
   * @namespace
   */
  Drupal.uiIconsPopover = {
    /**
     * Preview markup loaded so far, keyed by icon full id.
     *
     * Empty for an icon the server would not render.
     *
     * @type {Map<string, string>}
     */
    previews: new Map(),

    /**
     * Loads the icon index of some packs, once per page.
     *
     * @param {string} url
     *   The index route path.
     * @param {string} allowed
     *   The pack ids joined with `+`, empty for all packs.
     *
     * @return {Promise<object>}
     *   The index: `groups` as listed by the server, `icons` with their `id`,
     *   `label`, `group`, `pack` label and a lowercase `text` to search, and
     *   `byId` mapping each full id to its icon.
     */
    loadIndex(url, allowed) {
      const key = `${url}|${allowed}`;
      if (!indexes.has(key)) {
        const request = getJson(withQuery(url, allowedParams(allowed))).then(
          (data) => {
            const groups = new Map(
              data.groups.map((group) => [group.key, group.label]),
            );
            const icons = data.icons.map(([id, label, group]) => {
              const packId = id.slice(0, id.indexOf(':'));
              return {
                id,
                label,
                group,
                pack: data.packs[packId] ?? packId,
                text: `${id} ${label} ${groups.get(group) ?? ''}`.toLowerCase(),
              };
            });
            return {
              groups: data.groups,
              icons,
              byId: new Map(icons.map((icon) => [icon.id, icon])),
            };
          },
        );
        // A failed index is asked for again on the next open.
        request.catch(() => indexes.delete(key));
        indexes.set(key, request);
      }
      return indexes.get(key);
    },

    /**
     * Loads the previews of some icons into the previews map.
     *
     * @param {string} url
     *   The previews route path.
     * @param {string} allowed
     *   The pack ids joined with `+`, empty for all packs.
     * @param {Array<string>} ids
     *   The icon full ids.
     *
     * @return {Promise<void>}
     *   Settles once every requested id is loaded or failed.
     */
    loadPreviews(url, allowed, ids) {
      const { previews } = Drupal.uiIconsPopover;
      const missing = ids.filter(
        (id) => !previews.has(id) && !inflight.has(id),
      );
      for (let start = 0; start < missing.length; start += BATCH_SIZE) {
        const batch = missing.slice(start, start + BATCH_SIZE);
        const params = [
          ...allowedParams(allowed),
          ...batch.map((id) => ['ids[]', id]),
        ];
        const request = getJson(withQuery(url, params))
          .then((data) => {
            batch.forEach((id) => previews.set(id, data[id] ?? ''));
          })
          .finally(() => batch.forEach((id) => inflight.delete(id)));
        batch.forEach((id) => inflight.set(id, request));
      }
      return Promise.allSettled(ids.map((id) => inflight.get(id))).then(
        () => {},
      );
    },

    /**
     * Searches an index.
     *
     * Every word must appear in the icon id, label or group. Icons whose id
     * starts with the first word come first, the index order is kept
     * otherwise.
     *
     * @param {object} index
     *   An index from loadIndex().
     * @param {string} query
     *   The words to search for.
     *
     * @return {Array<object>}
     *   The matching icons.
     */
    search(index, query) {
      const words = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
      if (!words.length) {
        return [];
      }
      const matches = index.icons.filter((icon) =>
        words.every((word) => icon.text.includes(word)),
      );
      const startsWith = (icon) =>
        icon.id
          .slice(icon.id.indexOf(':') + 1)
          .toLowerCase()
          .startsWith(words[0]);
      return [
        ...matches.filter(startsWith),
        ...matches.filter((icon) => !startsWith(icon)),
      ];
    },

    /**
     * The icons picked lately, shared by every popover of the site.
     *
     * @namespace
     */
    recent: {
      /**
       * Gets the recently picked icons an index holds.
       *
       * @param {object} index
       *   An index from loadIndex(), limited to the packs of the element.
       *
       * @return {Array<string>}
       *   The icon full ids, most recent first.
       */
      get(index) {
        return readRecent().filter((id) => index.byId.has(id));
      },

      /**
       * Remembers a picked icon.
       *
       * @param {string} id
       *   The icon full id.
       */
      add(id) {
        const ids = [id, ...readRecent().filter((other) => other !== id)];
        try {
          window.localStorage.setItem(
            RECENT_KEY,
            JSON.stringify(ids.slice(0, RECENT_MAX)),
          );
        } catch (error) {
          // Without storage, Recent just stays empty.
        }
      },
    },
  };
})(Drupal);
