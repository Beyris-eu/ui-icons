/**
 * @file
 * JavaScript behavior for UI Icons picker selector in Drupal.
 */
// eslint-disable-next-line func-names
(function ($, Drupal, once) {

  'use strict';

  function openDialog(event) {
    event.preventDefault();

    var element = event.target || event.srcElement;

    var ajaxSettings = {
      element: element,
      progress: { type: 'throbber' },
      url: element.getAttribute('data-dialog-url'),
      dialogType: 'modal',
      httpMethod: 'GET',
      dialog: {
        classes: {
          'ui-dialog': 'icon-library-widget-modal'
        },
        title: Drupal.t('Select icon'),
        height: '90%',
        width: '90%',
        query: { wrapper_id: element.getAttribute('data-wrapper-id') }
      },
    };

    var myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
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
      once('dialog', 'input.form-icon-dialog', context).forEach(
        (element) => {
          element.addEventListener('click', openDialog);
        },
      );
    },
  };

  Drupal.AjaxCommands.prototype.updateIconLibrarySelection = function (
    ajax,
    response,
    status,
  ) {
    const elem = document.querySelector(`#${response.wrapper_id} input[name$='icon_id]']`);
    elem.value = response.icon_full_id;
    jQuery(elem).trigger("change");
  };

})(jQuery, Drupal, once);
