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

  /**
   * Keeps the icon in a form submitted while the element rebuilds itself.
   *
   * Core disables the input that triggered an ajax request until it answers,
   * and a disabled input is left out of the submitted values, so a Save in
   * that window dropped the icon. Re-enable it before the form is serialized,
   * for a plain submit and for an ajax one.
   *
   * @param {HTMLFormElement|undefined} form
   *   The form being submitted.
   */
  const enableIconInputs = (form) => {
    form
      ?.querySelectorAll('.ui-icons-input-wrapper input:disabled')
      .forEach((input) => {
        input.disabled = false;
      });
  };

  document.addEventListener(
    'submit',
    (event) => enableIconInputs(event.target),
    true,
  );

  const { beforeSerialize } = Drupal.Ajax.prototype;
  Drupal.Ajax.prototype.beforeSerialize = function iconBeforeSerialize(
    element,
    options,
  ) {
    enableIconInputs(this.$form?.[0]);
    beforeSerialize.call(this, element, options);
  };
})(jQuery, Drupal, once);
