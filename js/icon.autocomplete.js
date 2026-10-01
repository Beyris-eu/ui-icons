/**
 * @file
 * JavaScript behavior for UI Icons autocomplete selector in Drupal.
 */
(($, Drupal, once) => {
  /**
   * UI Icons autocomplete tweaks.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconAutocompleteSelect = {
    attach(context) {
      once(
        'setIconAutocompletePreview',
        '.ui-icons-wrapper .ui-icons-input-wrapper input',
        context,
      )
        .filter(
          (iconSelector) => typeof $(iconSelector).autocomplete() === 'object',
        )
        .forEach((iconSelector) => {
          jQuery(iconSelector).autocomplete('option', {
            delay: 500,
            minLength: 2,
          });
        });
    },
  };

  /**
   * Keeps the dialog usable when the icon settings are toggled.
   *
   * A dialog is sized and positioned when it opens, and core only recomputes
   * that on viewport changes. Expanding the settings grows the content beyond
   * the viewport, putting the dialog buttons out of reach on a fixed-position
   * element that cannot be scrolled to.
   *
   * The details can exist before the dialog wrapper does, so the dialog cannot
   * be part of the selector; the namespaced event is a no-op outside a dialog.
   * The settings wrapper comes from the icon-selector template, shared by the
   * icon_autocomplete and icon_picker elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconAutocompleteDialogResize = {
    attach(context) {
      once(
        'setIconSettingsDialogResize',
        '.ui-icons-settings-wrapper details',
        context,
      ).forEach((details) => {
        details.addEventListener('toggle', () => {
          $(window).trigger('resize.dialogResize');
        });
      });
    },
  };
})(jQuery, Drupal, once);
