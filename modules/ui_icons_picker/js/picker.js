/**
 * @file
 * JavaScript behavior for UI Icons picker selector in Drupal.
 */
/* eslint-disable no-unused-vars, func-names */
(($, Drupal, once) => {
  /**
   * @namespace
   */
  Drupal.IconPicker = Drupal.IconPicker || {};

  /**
   * Id of the element holding the library dialog.
   *
   * Kept out of #drupal-modal so a dialog opened from inside another dialog
   * does not close its opener. IconSelectForm reads it back from the dialog
   * options to know which dialog to close on selection.
   */
  Drupal.IconPicker.dialogTarget = 'ui-icons-picker-dialog';

  /**
   * Opens the icon library dialog.
   *
   * @param {HTMLElement} element
   *   The element carrying the dialog data attributes, that is the icon_id
   *   input of an icon_picker or icon_autocomplete element.
   */
  Drupal.IconPicker.openDialog = function (element) {
    const ajaxSettings = {
      element,
      progress: { type: 'none' },
      url: element.getAttribute('data-dialog-url'),
      // A dialog with an own target rather than dialogType 'modal'. All modals
      // share the single #drupal-modal element, so opening the library from a
      // form that is itself in a modal, CKEditor embedded content or Layout
      // Builder for instance, would replace the very form the picked icon has
      // to be written back to. The modal option keeps the overlay behaviour.
      dialogType: 'dialog',
      httpMethod: 'GET',
      dialog: {
        target: Drupal.IconPicker.dialogTarget,
        modal: true,
        classes: {
          'ui-dialog': 'icon-library-widget-modal',
        },
        title: Drupal.t('Select icon'),
        height: '95%',
        width: '95%',
        query: {
          wrapper_id: element.getAttribute('data-wrapper-id'),
          allowed_icon_pack: element.getAttribute('data-allowed-icon-pack'),
        },
      },
    };

    const myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  };

  function openDialog(event) {
    event.preventDefault();
    Drupal.IconPicker.openDialog(event.currentTarget);
  }

  /**
   * Attaches the Icon dialog behavior to all required fields.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the Icon dialog behaviors.
   */
  Drupal.behaviors.icon_dialog = {
    attach(context) {
      once('dialog', 'input.form-icon-dialog', context).forEach((element) => {
        element.addEventListener('click', openDialog);
      });
    },
  };

  /**
   * Updates the icon library selection.
   *
   * @param {Object} ajax
   *   The AJAX object.
   * @param {Object} response
   *   The response object from the AJAX call.
   * @param {string} response.wrapper_id
   *   The ID of the wrapper element.
   * @param {string} response.icon_full_id
   *   The full ID of the icon.
   * @param {string} status
   *   The status of the AJAX call.
   */
  Drupal.AjaxCommands.prototype.updateIconLibrarySelection = function (
    ajax,
    response,
    status,
  ) {
    const elem = document.querySelector(
      `#${response.wrapper_id} input[name$='icon_id]']`,
    );
    if (!elem) {
      // The form that opened the library is gone, nothing to write back to.
      return;
    }
    elem.value = response.icon_full_id;
    jQuery(elem).trigger('change');
  };
})(jQuery, Drupal, once);
