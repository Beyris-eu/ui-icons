/**
 * @file
 * Canvas client-side transform for ui_icons' `icon_widget`.
 *
 * The widget's icon selectors (`icon_autocomplete`, and `icon_picker` which
 * extends it) nest the picked identifier under `value.icon_id` — e.g. a
 * single-delta value arrives as `[{ value: { icon_id: "phosphor:house" } }]`.
 * When the field allows per-icon settings, the icon pack's own settings ride
 * along under `value.icon_settings`, keyed by icon pack.
 *
 * The `ui_icon` field type expects `{ target_id, settings }`, and no built-in
 * transform can reach two levels deep, so this transform reshapes each delta
 * record. Settings keep their `[pack_id => settings]` keying, as stored.
 *
 * Registered as `Drupal.canvasTransforms.uiIcon`.
 *
 * @see \Drupal\ui_icons_canvas\Hook\UiIconsCanvasHooks::fieldWidgetInfoAlter()
 */
((Drupal) => {
  /**
   * Re-expands keys that Canvas' form-state decoder flattened.
   *
   * Canvas decodes Drupal's bracket-notation input names with `qs.parse()` and
   * no options, so it uses qs' default `depth: 5`. An icon settings input is
   * named `canvas_component_props[uuid][icon][0][value][icon_settings][pack][setting]`
   * — seven levels — so qs stops descending after `icon_settings` and hands us
   * a single literal key `"[pack][setting]"` instead of a nested object.
   *
   * Rebuild the nesting here rather than shipping settings the server cannot
   * read. Objects that arrived properly nested pass through untouched, so this
   * becomes a no-op once Canvas raises the decoder's depth.
   *
   * @see https://github.com/ljharb/qs#parsing-objects
   */
  const expandFlattenedKeys = (input) => {
    if (!input || typeof input !== 'object' || Array.isArray(input)) {
      return input;
    }
    const expanded = {};
    Object.entries(input).forEach(([key, value]) => {
      const segments = key.startsWith('[')
        ? key.slice(1, -1).split('][')
        : [key];
      let cursor = expanded;
      segments.forEach((segment, index) => {
        if (index === segments.length - 1) {
          cursor[segment] = expandFlattenedKeys(value);
          return;
        }
        if (!cursor[segment] || typeof cursor[segment] !== 'object') {
          cursor[segment] = {};
        }
        cursor = cursor[segment];
      });
    });
    return expanded;
  };

  Drupal.canvasTransforms = Drupal.canvasTransforms || {};
  Drupal.canvasTransforms.uiIcon = (value, options = {}) => {
    const { multiple = false } = options;
    const records = Array.isArray(value) ? value : [value];
    const items = records.map((record) => {
      if (!record || typeof record !== 'object') {
        return null;
      }
      // `icon_autocomplete` nests its inputs under `value`; tolerate a flat
      // record too in case the structure is normalized upstream.
      const inner =
        'value' in record && record.value && typeof record.value === 'object'
          ? record.value
          : record;
      const id = inner && typeof inner === 'object' ? inner.icon_id : null;
      if (!id) {
        return null;
      }
      const item = { target_id: id };
      // Only send settings when the widget actually collected some, so props
      // that do not declare `settings` keep a minimal stored value.
      const settings = expandFlattenedKeys(inner.icon_settings);
      if (settings && typeof settings === 'object') {
        item.settings = settings;
      }
      return item;
    });
    return multiple
      ? items.filter((item) => item !== null)
      : (items[0] ?? null);
  };
})(Drupal);
