/**
 * @file
 * JavaScript behavior for UI Icons picker library in Drupal.
 */
// eslint-disable-next-line func-names
(function ($, Drupal, once) {
  'use strict';
  /**
   * UI Icons picker library.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPickerLibrary = {

    attach(context) {

      // Auto submit filter by name.
      const iconPickerLibrarySearch = once('setIconPickerSearch', '.icon-filter-input', context);
      let typingTimer;
      const typingInterval = 600;
      
      iconPickerLibrarySearch.forEach(element => {
        element.addEventListener('keypress', function(event) {
          if (event.keyCode === 13) {
            document.querySelector('.icon-ajax-search-submit').dispatchEvent(new MouseEvent('mousedown'));
          }
        });
    
        element.addEventListener('keyup', function() {
          clearTimeout(typingTimer);
          typingTimer = setTimeout(function() {
            document.querySelector('.icon-ajax-search-submit').dispatchEvent(new MouseEvent('mousedown'));
          }, typingInterval);
        });
    
        element.addEventListener('keydown', function() {
          clearTimeout(typingTimer);
        });
      });

      // Submit when clicked icon preview.
      const iconPickerPreview = once('setIconPreviewClick', '.icon-preview', context);
      iconPickerPreview.forEach(element => {
        element.addEventListener('click', function(event) {
          const icon_id = element.getAttribute('data-icon-id');
          const input = document.querySelector(`[data-icon-select='${icon_id}']`);
          input.dispatchEvent(new MouseEvent('click'));
        });
      });
    },
  };
})(jQuery, Drupal, once);
