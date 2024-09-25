/**
 * @file
 * JavaScript behavior for UI Icons picker library in Drupal.
 */
// eslint-disable-next-line func-names
(function ($, Drupal, once) {
  /**
   * UI Icons picker library.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPickerLibrary = {
    attach(context) {
      // Auto submit filter by name.
      const iconPickerLibrarySearch = once(
        'setIconPickerSearch',
        '.icon-filter-input',
        context,
      );
      let typingTimer;
      const typingInterval = 600;

      iconPickerLibrarySearch.forEach((element) => {
        element.addEventListener('keypress', function (event) {
          if (event.keyCode === 13) {
            document
              .querySelector('.icon-ajax-search-submit')
              .dispatchEvent(new MouseEvent('mousedown'));
          }
        });

        element.addEventListener('keyup', function () {
          clearTimeout(typingTimer);
          typingTimer = setTimeout(function () {
            document
              .querySelector('.icon-ajax-search-submit')
              .dispatchEvent(new MouseEvent('mousedown'));
          }, typingInterval);
        });

        element.addEventListener('keydown', function () {
          clearTimeout(typingTimer);
        });
      });

      // Move the icon instead of label in each radio.
      const iconPickerPreview = once('setIconPreview', '.icon-radio', context);
      iconPickerPreview.forEach((element) => {
        // See templates/icon-preview.html.twig for css selector.
        const iconPreview = document.querySelector(
          `.icon-preview[data-icon-id='${element.value}']`,
        );
        if (!iconPreview || typeof element.labels[0] === 'undefined') {
          return;
        }
        element.labels[0].textContent = '';
        element.labels[0].prepend(iconPreview);

        // Submit when clicked any icon.
        element.addEventListener('click', function (event) {
          document.querySelector('.icon-ajax-select-submit').click();
        });
      });
    },
  };
})(jQuery, Drupal, once);
