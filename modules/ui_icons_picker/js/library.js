/**
 * @file
 * JavaScript behavior for UI Icons picker library in Drupal.
 */
/* eslint-disable no-restricted-syntax, max-nested-callbacks, no-unused-vars, func-names, no-continue */
((Drupal, drupalSettings, once) => {
  /**
   * Shortest filter searched for, IconSearch::SEARCH_MIN_LENGTH.
   */
  const searchMinLength = 2;

  /**
   * UI Icons picker library search.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPickerLibrarySearch = {
    attach(context) {
      let typingTimer;
      const typingInterval = 600;

      // Auto submit filter by name.
      once('setIconPickerSearch', '.icon-filter-input', context).forEach(
        (element) => {
          let searched = element.value.trim();

          // A single character is too short to search for, an empty filter
          // brings the whole list back.
          const isSearchable = (value) =>
            value !== searched &&
            (value === '' || value.length >= searchMinLength);

          const search = () => {
            clearTimeout(typingTimer);
            const button = document.querySelector('.icon-ajax-search-submit');
            const ajax = Drupal.ajax.instances.find(
              (instance) => instance && instance.element === button,
            );
            // Drupal drops a request sent while the previous one runs.
            if (ajax && ajax.ajaxing) {
              typingTimer = setTimeout(search, typingInterval);
              return;
            }
            searched = element.value.trim();
            button.dispatchEvent(new MouseEvent('mousedown'));
          };

          element.addEventListener('input', () => {
            clearTimeout(typingTimer);
            if (isSearchable(element.value.trim())) {
              typingTimer = setTimeout(search, typingInterval);
            }
          });

          element.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
              return;
            }
            // Enter would submit the form otherwise.
            event.preventDefault();
            if (isSearchable(element.value.trim())) {
              search();
            }
          });
        },
      );

      // Submit the form when clicked any icon.
      once('setIconPick', '.icon-preview-load', context).forEach((element) => {
        element.addEventListener('click', function (event) {
          document.querySelector('.icon-ajax-select-submit').click();
        });
      });
    },
  };

  /**
   * UI Icons picker library preview.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPickerLibraryPreview = {
    attach(context, settings) {
      once('loadIconPreview', '.icon-picker-modal__content', context).forEach(
        (list) => {
          if (!settings.ui_icons_preview_data) {
            return;
          }
          // Read from the grid, ajax deep merges settings into the previous
          // ones and they would still hold the ids of the grid before.
          const iconFullIds = Array.from(
            list.querySelectorAll('input.icon-preview-load'),
            (input) => input.value,
          ).filter((value) => value !== '_none_');
          Drupal.Icon.loadIconPreview({
            ...settings.ui_icons_preview_data,
            icon_full_ids: iconFullIds,
          });
        },
      );
    },
  };
})(Drupal, drupalSettings, once);
