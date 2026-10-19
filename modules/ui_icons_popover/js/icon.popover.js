/**
 * @file
 * JavaScript behavior for the UI Icons popover selector.
 */
((Drupal, once) => {
  /**
   * Tiles added to the grid per animation frame when a view is large.
   */
  const CHUNK = 300;

  /**
   * Counts the popovers of the page, to name each one's anchor.
   */
  let instances = 0;

  /**
   * Name of the input an icon was just picked or cleared in.
   *
   * The pick rebuilds the element through ajax, replacing the trigger that
   * had the focus. The new trigger takes it back, see the attach below.
   *
   * @type {?string}
   */
  let pendingFocus = null;

  /**
   * Creates an element.
   *
   * @param {string} tag
   *   The tag name.
   * @param {object} attributes
   *   Attributes to set, `class` and `text` set the class name and the text.
   * @param {Array<Node|string>} children
   *   The children to append.
   *
   * @return {HTMLElement}
   *   The element.
   */
  const create = (tag, attributes = {}, children = []) => {
    const element = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => {
      if (name === 'class') {
        element.className = value;
      } else if (name === 'text') {
        element.textContent = value;
      } else {
        element.setAttribute(name, value);
      }
    });
    element.append(...children);
    return element;
  };

  /**
   * Turns an icon_popover input into a trigger button and its popover.
   *
   * @param {HTMLInputElement} input
   *   The input the server rendered, which keeps holding the value.
   *
   * @return {HTMLButtonElement}
   *   The trigger.
   */
  function enhance(input) {
    const data = Drupal.uiIconsPopover;
    const { id } = input;
    const allowed = input.dataset.allowedIconPack || '';
    const wrapper = input.closest('.ui-icons-input-wrapper');
    const serverPreview = wrapper.querySelector('.ui-icons-preview');

    const preview = create('span', {
      class: 'ui-icons-popover__preview',
      'aria-hidden': 'true',
    });
    const serverIcon = serverPreview?.querySelector('.ui-icons-preview-icon');
    if (serverIcon) {
      preview.append(...serverIcon.childNodes);
    }
    const value = create('span', {
      class: 'ui-icons-popover__value',
      id: `${id}--value`,
      text: input.dataset.iconLabel || Drupal.t('Choose an icon'),
    });
    const trigger = create(
      'button',
      {
        type: 'button',
        class: 'ui-icons-popover__trigger',
        id: `${id}--trigger`,
        popovertarget: `${id}--panel`,
        'aria-haspopup': 'dialog',
        'aria-describedby': value.id,
      },
      [preview, value],
    );
    const clear = create(
      'button',
      {
        type: 'button',
        class: 'ui-icons-popover__clear',
        'aria-label': Drupal.t('Remove icon'),
        title: Drupal.t('Remove icon'),
      },
      ['×'],
    );
    clear.hidden = !input.value || input.required;

    const search = create('input', {
      type: 'search',
      class: 'ui-icons-popover__search',
      placeholder: Drupal.t('Search icons'),
      'aria-label': Drupal.t('Search icons'),
      autocomplete: 'off',
      role: 'combobox',
      'aria-autocomplete': 'list',
      'aria-expanded': 'false',
    });
    const rail = create('div', {
      class: 'ui-icons-popover__rail',
      role: 'tablist',
      'aria-orientation': 'vertical',
      'aria-label': Drupal.t('Icon groups'),
    });
    const grid = create('div', {
      class: 'ui-icons-popover__grid',
      id: `${id}--tabpanel`,
      role: 'tabpanel',
    });
    const footer = create('div', {
      class: 'ui-icons-popover__footer',
      'aria-hidden': 'true',
    });
    const panel = create(
      'div',
      {
        class: 'ui-icons-popover__panel',
        id: `${id}--panel`,
        popover: 'auto',
        role: 'dialog',
        'aria-label': Drupal.t('Choose an icon'),
      },
      [
        search,
        create('div', { class: 'ui-icons-popover__body' }, [rail, grid]),
        footer,
      ],
    );

    // Next to the input wrapper, not in it: js/icon.autocomplete.js turns any
    // input in there into an autocomplete, the search box included.
    wrapper.hidden = true;
    wrapper.after(
      create('div', { class: 'ui-icons-popover' }, [trigger, clear, panel]),
    );

    const label = document.querySelector(`label[for="${CSS.escape(id)}"]`);
    if (label) {
      label.htmlFor = trigger.id;
    }
    if (input.classList.contains('error')) {
      trigger.classList.add('error');
      trigger.setAttribute('aria-invalid', 'true');
    }

    instances += 1;
    const anchor = `--ui-icons-popover-${instances}`;
    trigger.style.setProperty('anchor-name', anchor);
    panel.style.setProperty('position-anchor', anchor);

    /**
     * The index of the allowed packs, once loaded.
     *
     * @type {?object}
     */
    let index = null;

    /**
     * Bumped on each render, so an unfinished chunked render stops.
     */
    let renderId = 0;

    /**
     * Tiles scrolled into view whose previews are not loaded yet.
     *
     * @type {Set<HTMLButtonElement>}
     */
    const queued = new Set();
    let frame = 0;

    const fillPreview = (tile) => {
      const markup = data.previews.get(tile.dataset.iconId);
      if (markup === undefined) {
        return false;
      }
      tile.innerHTML = markup;
      return true;
    };

    const observer = new IntersectionObserver(
      (entries) => {
        entries
          .filter((entry) => entry.isIntersecting)
          .forEach((entry) => {
            queued.add(entry.target);
            observer.unobserve(entry.target);
          });
        if (frame || !queued.size) {
          return;
        }
        frame = requestAnimationFrame(async () => {
          frame = 0;
          const tiles = [...queued];
          queued.clear();
          await data.loadPreviews(
            input.dataset.previewsUrl,
            allowed,
            tiles.map((tile) => tile.dataset.iconId),
          );
          tiles.forEach(fillPreview);
        });
      },
      { root: grid, rootMargin: '200px' },
    );

    const showStatus = (text) => {
      observer.disconnect();
      grid.replaceChildren(
        create('p', { class: 'ui-icons-popover__status', text }),
      );
    };

    const createTile = (icon) => {
      const tile = create('button', {
        type: 'button',
        class: 'ui-icons-popover__tile',
        tabindex: '-1',
        'data-icon-id': icon.id,
        'aria-label': icon.label,
        title: `${icon.label} (${icon.pack})`,
      });
      if (icon.id === input.value) {
        tile.setAttribute('aria-current', 'true');
      }
      if (!fillPreview(tile)) {
        observer.observe(tile);
      }
      return create('div', { role: 'gridcell' }, [tile]);
    };

    /**
     * Counts the tiles of one grid row, from the resolved CSS grid tracks.
     *
     * @return {number}
     *   The column count.
     */
    const columnCount = () => {
      const probe = create('div', { class: 'ui-icons-popover__row' });
      grid.append(probe);
      const count = getComputedStyle(probe)
        .gridTemplateColumns.split(' ')
        .filter(Boolean).length;
      probe.remove();
      return Math.max(1, count);
    };

    /**
     * Renders the sections of a view as one ARIA grid.
     *
     * @param {Array<object>} sections
     *   Each with a `label`, its `icons`, and `heading` to show the label.
     * @param {string} labelledBy
     *   The id of the tab naming the grid.
     */
    const renderGrid = (sections, labelledBy) => {
      renderId += 1;
      const current = renderId;
      observer.disconnect();
      queued.clear();
      const columns = columnCount();
      const table = create('div', {
        class: 'ui-icons-popover__table',
        role: 'grid',
        'aria-labelledby': labelledBy,
      });
      grid.replaceChildren(table);
      grid.scrollTop = 0;

      // Rows are queued section by section, then appended in chunks so a
      // large view never blocks the page.
      const rows = [];
      sections.forEach((section) => {
        const group = create('div', {
          class: `ui-icons-popover__section${section.heading ? ' ui-icons-popover__section--lazy' : ''}`,
          role: 'rowgroup',
          'aria-label': section.label,
        });
        group.style.setProperty(
          '--rows',
          Math.ceil(section.icons.length / columns),
        );
        if (section.heading) {
          group.append(
            create('div', {
              class: 'ui-icons-popover__heading',
              'aria-hidden': 'true',
              text: section.label,
            }),
          );
        }
        table.append(group);
        for (let start = 0; start < section.icons.length; start += columns) {
          rows.push([group, section.icons.slice(start, start + columns)]);
        }
      });

      const appendRows = (from) => {
        if (current !== renderId) {
          return;
        }
        let count = 0;
        let position = from;
        while (position < rows.length && count < CHUNK) {
          const [group, icons] = rows[position];
          group.append(
            create(
              'div',
              { class: 'ui-icons-popover__row', role: 'row' },
              icons.map(createTile),
            ),
          );
          count += icons.length;
          position += 1;
        }
        if (position < rows.length) {
          requestAnimationFrame(() => appendRows(position));
        }
      };
      appendRows(0);

      const tiles = table.querySelectorAll('.ui-icons-popover__tile');
      const focusable =
        table.querySelector('[aria-current="true"]') || tiles[0];
      if (focusable) {
        focusable.tabIndex = 0;
      }
    };

    /**
     * Lists the rail entries: Recent, All, the groups, then Other.
     *
     * @return {Array<object>}
     *   Each with a `key`, a `label` and its `sections`.
     */
    const railEntries = () => {
      const entries = [];
      const recent = data.recent ? data.recent.get(index) : [];
      if (recent.length) {
        const icons = recent.map((iconId) => index.byId.get(iconId));
        entries.push({
          key: 'recent',
          label: Drupal.t('Recent'),
          sections: [{ label: Drupal.t('Recent'), icons }],
        });
      }

      const other = index.icons.filter((icon) => icon.group === null);
      const groups = index.groups.map((group) => ({
        key: `group:${group.key}`,
        label: group.label,
        sections: [
          {
            label: group.label,
            icons: index.icons.filter((icon) => icon.group === group.key),
          },
        ],
      }));
      if (groups.length && other.length) {
        groups.push({
          key: 'other',
          label: Drupal.t('Other'),
          sections: [{ label: Drupal.t('Other'), icons: other }],
        });
      }

      entries.push({
        key: 'all',
        label: Drupal.t('All'),
        sections: groups.length
          ? groups.map((entry) => ({ ...entry.sections[0], heading: true }))
          : [{ label: Drupal.t('All'), icons: index.icons }],
      });

      return entries.concat(groups);
    };

    let entries = [];

    /**
     * The rail entry shown, restored when a search is cleared.
     *
     * @type {?object}
     */
    let currentEntry = null;

    const tabId = (entry) => `${id}--tab-${entries.indexOf(entry)}`;

    const resetSearch = () => {
      search.value = '';
      search.setAttribute('aria-expanded', 'false');
      search.removeAttribute('aria-controls');
      search.removeAttribute('aria-activedescendant');
    };

    const showEntry = (entry) => {
      currentEntry = entry;
      rail.querySelectorAll('[role="tab"]').forEach((tab) => {
        const selected = tab.id === tabId(entry);
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
      });
      renderGrid(entry.sections, tabId(entry));
    };

    const buildRail = () => {
      entries = railEntries();
      rail.replaceChildren(
        ...entries.map((entry) => {
          const count = entry.sections.reduce(
            (total, section) => total + section.icons.length,
            0,
          );
          const tab = create(
            'button',
            {
              type: 'button',
              class: 'ui-icons-popover__tab',
              id: tabId(entry),
              role: 'tab',
              'aria-controls': grid.id,
            },
            [
              create('span', { text: entry.label }),
              create('span', {
                class: 'ui-icons-popover__count',
                text: String(count),
              }),
            ],
          );
          tab.addEventListener('click', () => {
            resetSearch();
            showEntry(entry);
          });
          return tab;
        }),
      );
    };

    /**
     * Picks the view to open on: the current icon's group, else the first.
     *
     * @return {object}
     *   The rail entry.
     */
    const initialEntry = () => {
      const icon = index.byId.get(input.value);
      if (icon) {
        const key = icon.group === null ? 'other' : `group:${icon.group}`;
        const entry = entries.find((candidate) => candidate.key === key);
        if (entry) {
          return entry;
        }
        return entries.find((candidate) => candidate.key === 'all');
      }
      return entries[0];
    };

    const scrollToCurrent = () => {
      const tile = grid.querySelector('[aria-current="true"]');
      if (tile) {
        grid.scrollTop =
          tile.offsetTop - (grid.clientHeight - tile.offsetHeight) / 2;
      }
    };

    /**
     * Shows the search results as a listbox the search input drives.
     *
     * @param {string} query
     *   The words to search for.
     */
    const showResults = (query) => {
      const icons = data.search(index, query);
      renderId += 1;
      observer.disconnect();
      queued.clear();
      search.removeAttribute('aria-activedescendant');

      if (!icons.length) {
        const status = create('p', { class: 'ui-icons-popover__status' });
        status.innerHTML = Drupal.t('No icon matches %query.', {
          '%query': query.trim(),
        });
        grid.replaceChildren(status);
        search.setAttribute('aria-expanded', 'false');
        search.removeAttribute('aria-controls');
        Drupal.announce(status.textContent);
        return;
      }

      const listbox = create(
        'div',
        {
          class: 'ui-icons-popover__results',
          id: `${id}--results`,
          role: 'listbox',
          'aria-label': Drupal.t('Search icons'),
        },
        icons.map((icon, position) => {
          const option = create('div', {
            class: 'ui-icons-popover__tile',
            id: `${id}--option-${position}`,
            role: 'option',
            'aria-selected': 'false',
            'data-icon-id': icon.id,
            'aria-label': icon.label,
            title: `${icon.label} (${icon.pack})`,
          });
          if (!fillPreview(option)) {
            observer.observe(option);
          }
          return option;
        }),
      );
      grid.replaceChildren(listbox);
      grid.scrollTop = 0;
      search.setAttribute('aria-expanded', 'true');
      search.setAttribute('aria-controls', listbox.id);
      Drupal.announce(
        Drupal.formatPlural(icons.length, '1 icon', '@count icons'),
      );
    };

    const onSearch = Drupal.debounce(() => {
      if (!index) {
        return;
      }
      if (search.value.trim()) {
        showResults(search.value);
        return;
      }
      resetSearch();
      if (currentEntry) {
        showEntry(currentEntry);
      }
    }, 150);

    /**
     * Moves the active search result, which the input keeps the focus over.
     *
     * @param {number} step
     *   1 for the next result, -1 for the previous one.
     */
    const moveActiveOption = (step) => {
      const options = [...grid.querySelectorAll('[role="option"]')];
      if (!options.length) {
        return;
      }
      const active = options.findIndex(
        (option) => option.id === search.getAttribute('aria-activedescendant'),
      );
      const next =
        options[Math.max(0, Math.min(options.length - 1, active + step))];
      options.forEach((option) => {
        option.setAttribute('aria-selected', String(option === next));
      });
      search.setAttribute('aria-activedescendant', next.id);
      next.scrollIntoView({ block: 'nearest' });
      footer.textContent = next.getAttribute('aria-label');
    };

    const open = async () => {
      resetSearch();
      search.focus();
      if (!index) {
        showStatus(Drupal.t('Loading icons…'));
        try {
          index = await data.loadIndex(input.dataset.indexUrl, allowed);
        } catch (error) {
          showStatus(Drupal.t('Icons could not be loaded.'));
          return;
        }
      }
      buildRail();
      showEntry(initialEntry());
      scrollToCurrent();
      // Words typed while the index loaded.
      if (search.value.trim()) {
        showResults(search.value);
      }
    };

    const pick = (iconId) => {
      const icon = index.byId.get(iconId);
      if (data.recent) {
        data.recent.add(iconId);
      }
      preview.innerHTML = data.previews.get(iconId) ?? '';
      value.textContent = icon.label;
      input.value = iconId;
      pendingFocus = input.name;
      panel.hidePopover();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Without CSS anchor positioning, place the panel under the trigger, or
    // above it when there is no room below. A small screen gets a sheet.
    const anchored = CSS.supports('anchor-name', '--a');
    const sheet = window.matchMedia('(max-width: 480px)');
    const place = () => {
      if (anchored || sheet.matches || !panel.matches(':popover-open')) {
        return;
      }
      const box = trigger.getBoundingClientRect();
      const below = window.innerHeight - box.bottom;
      const top =
        below < panel.offsetHeight && box.top > below
          ? box.top - panel.offsetHeight - 4
          : box.bottom + 4;
      const left = Math.max(
        4,
        Math.min(box.left, window.innerWidth - panel.offsetWidth - 4),
      );
      panel.style.top = `${top}px`;
      panel.style.left = `${left}px`;
    };

    panel.addEventListener('toggle', (event) => {
      if (event.newState === 'open') {
        place();
        window.addEventListener('resize', place);
        document.addEventListener('scroll', place, {
          capture: true,
          passive: true,
        });
        open();
      } else {
        window.removeEventListener('resize', place);
        document.removeEventListener('scroll', place, { capture: true });
      }
    });

    grid.addEventListener('click', (event) => {
      const tile = event.target.closest('.ui-icons-popover__tile');
      if (tile) {
        pick(tile.dataset.iconId);
      }
    });
    const showName = (event) => {
      const tile = event.target.closest?.('.ui-icons-popover__tile');
      if (tile) {
        footer.textContent = tile.getAttribute('aria-label');
      }
    };
    grid.addEventListener('mouseover', showName);
    grid.addEventListener('focusin', showName);

    search.addEventListener('input', onSearch);
    search.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        moveActiveOption(event.key === 'ArrowDown' ? 1 : -1);
      }
      // The search input sits in the node form: Enter must not submit it.
      if (event.key === 'Enter') {
        event.preventDefault();
        const active = search.getAttribute('aria-activedescendant');
        if (active) {
          pick(document.getElementById(active).dataset.iconId);
        }
      }
    });

    // Manual activation: arrows move the focus, Enter or Space shows the tab.
    rail.addEventListener('keydown', (event) => {
      const tabs = [...rail.querySelectorAll('[role="tab"]')];
      const position = tabs.indexOf(document.activeElement);
      const targets = {
        ArrowDown: position + 1,
        ArrowRight: position + 1,
        ArrowUp: position - 1,
        ArrowLeft: position - 1,
        Home: 0,
        End: tabs.length - 1,
      };
      if (position < 0 || !(event.key in targets)) {
        return;
      }
      event.preventDefault();
      const next = tabs[(targets[event.key] + tabs.length) % tabs.length];
      tabs.forEach((tab) => {
        tab.tabIndex = tab === next ? 0 : -1;
      });
      next.focus();
    });

    // Roving focus over the grid tiles, row by row across the sections.
    grid.addEventListener('keydown', (event) => {
      const tile = event.target.closest('button.ui-icons-popover__tile');
      if (!tile) {
        return;
      }
      const rows = [...grid.querySelectorAll('[role="row"]')];
      const row = tile.closest('[role="row"]');
      const y = rows.indexOf(row);
      const tilesOf = (target) =>
        target ? [...target.querySelectorAll('.ui-icons-popover__tile')] : [];
      const current = tilesOf(row);
      const x = current.indexOf(tile);
      const sameColumn = (target) => {
        const others = tilesOf(target);
        return others[Math.min(x, others.length - 1)];
      };
      const moves = {
        ArrowRight: () => current[x + 1] ?? tilesOf(rows[y + 1])[0],
        ArrowLeft: () => current[x - 1] ?? tilesOf(rows[y - 1]).at(-1),
        ArrowDown: () => sameColumn(rows[y + 1]),
        ArrowUp: () => sameColumn(rows[y - 1]),
        Home: () => current[0],
        End: () => current.at(-1),
      };
      if (!moves[event.key]) {
        return;
      }
      event.preventDefault();
      const target = moves[event.key]();
      if (target) {
        tile.tabIndex = -1;
        target.tabIndex = 0;
        target.focus();
      }
    });

    // A dialog the popover opens in, such as the CKEditor icon dialog, closes
    // on Escape too. The popover closes itself.
    panel.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.stopPropagation();
      }
    });

    clear.addEventListener('click', () => {
      input.value = '';
      pendingFocus = input.name;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    return trigger;
  }

  /**
   * Replaces the icon_popover input with its trigger and popover.
   *
   * Browsers without the popover API keep the plain input.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.iconPopover = {
    attach(context) {
      if (!('togglePopover' in HTMLElement.prototype)) {
        return;
      }
      once('icon-popover', 'input[data-icon-popover]', context).forEach(
        (input) => {
          const trigger = enhance(input);
          if (pendingFocus === input.name) {
            pendingFocus = null;
            if (document.activeElement === document.body) {
              trigger.focus({ preventScroll: true });
            }
          }
        },
      );
    },
  };
})(Drupal, once);
