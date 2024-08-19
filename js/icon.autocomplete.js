/**
 * @file
 * JavaScript behavior for UI Icons autocomplete selector in Drupal.
 */
// eslint-disable-next-line func-names
(function ($, Drupal, once) {
  /**
   * UI Icons autocomplete tweaks.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconAutocompleteSelect = {
    attach(context) {
      const iconSelectors = once(
        'setIconPreview',
        '.ui-icons-wrapper .ui-icons-input-wrapper input',
        context,
      );

      if (!iconSelectors || iconSelectors.length === 0) {
        return;
      }

      iconSelectors.forEach((iconSelector) => {
        const selector = jQuery(iconSelector);

        if (typeof selector.autocomplete() !== 'function') {
          return;
        }

        // Current Drupal core autocomplete is based on jQuery UI.
        // Change autocomplete trigger to 3 characters and set delay a bit
        // longer from 300 to 500.
        // @see https://api.jqueryui.com/autocomplete/
        const options = {
          // @todo check if autofocus first result is a good idea.
          // autoFocus: true,
          delay: 500,
          minLength: 2,
        };
        selector.autocomplete('option', options);
      });
    },
  };
})(jQuery, Drupal, once);
