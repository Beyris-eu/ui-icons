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
            // jQuery UI does not keep the suggestions in the window: next to
            // its right edge, a menu wider than the input was cut off.
            position: {
              my: 'left top',
              at: 'left bottom',
              collision: 'flipfit',
            },
          });
        });
    },
  };

  /**
   * Name of the input a suggestion was just picked in.
   *
   * Core disables the input during the rebuild the pick starts, and the
   * element opts out of core's refocus afterwards: a blur starts that rebuild
   * as well, and the focus then has to stay where the user moved it.
   *
   * @type {?string}
   */
  let pickedInput = null;

  // cspell:ignore autocompleteclose autocompletefocus autocompleteopen
  // cspell:ignore autocompleteresponse autocompleteselect menublur
  /**
   * Gives the icon autocomplete the semantics of a combobox.
   *
   * jQuery UI renders its menu without roles and speaks for it through a live
   * region of its own, assertive, in English, and reading the machine id of
   * each suggestion. Expose the menu as a listbox the input controls instead,
   * and say in a polite status next to the input why no suggestion shows.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconAutocompleteCombobox = {
    attach(context) {
      // The input a suggestion was picked in is replaced by its rebuild.
      if (pickedInput && context.querySelector) {
        const input = context.querySelector(`input[name="${pickedInput}"]`);
        if (input) {
          pickedInput = null;
          // Unless the focus moved on meanwhile, with Tab for instance.
          if (document.activeElement === document.body) {
            input.focus({ preventScroll: true });
          }
        }
      }

      once(
        'iconAutocompleteCombobox',
        '.ui-icons-input-wrapper input[data-autocomplete-path]',
        context,
      ).forEach((input) => {
        const $input = $(input);
        const instance = $input.autocomplete('instance');
        const menu = instance.menu.element[0];
        const minLength = $input.autocomplete('option', 'minLength');

        const status = document.createElement('div');
        status.className = 'ui-icons-autocomplete-status';
        status.setAttribute('role', 'status');
        // Before the description, which some admin themes move out of the
        // flow under the preview.
        const description = document.getElementById(
          input.getAttribute('aria-describedby'),
        );
        if (description) {
          description.before(status);
        } else {
          input.after(status);
        }
        instance.liveRegion.remove();

        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', Drupal.t('Icon suggestions'));
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', menu.id);

        const setActiveOption = (active) => {
          menu.querySelectorAll('[role="option"]').forEach((option) => {
            option.setAttribute('aria-selected', String(option === active));
          });
          if (active) {
            input.setAttribute('aria-activedescendant', active.id);
          } else {
            input.removeAttribute('aria-activedescendant');
          }
        };

        input.addEventListener('input', () => {
          const { length } = input.value.trim();
          status.textContent =
            length && length < minLength
              ? Drupal.formatPlural(
                  minLength,
                  'Type at least 1 character.',
                  'Type at least @count characters.',
                )
              : '';
        });

        $input.on({
          autocompleteresponse(event, ui) {
            status.innerHTML = ui.content?.length
              ? ''
              : Drupal.t('No icon matches %query.', {
                  '%query': input.value.trim(),
                });
          },
          autocompleteopen() {
            menu.querySelectorAll('.ui-menu-item').forEach((item) => {
              item.setAttribute('role', 'presentation');
            });
            menu.querySelectorAll('.ui-menu-item-wrapper').forEach((option) => {
              option.setAttribute('role', 'option');
            });
            setActiveOption(null);
            input.setAttribute('aria-expanded', 'true');
          },
          autocompletefocus() {
            setActiveOption(
              instance.menu.active?.children('.ui-menu-item-wrapper')[0],
            );
          },
          autocompleteclose() {
            input.setAttribute('aria-expanded', 'false');
            setActiveOption(null);
          },
          autocompleteselect() {
            pickedInput = input.name;
          },
        });
        // Going back up to the input leaves no option active.
        $(menu).on('menublur', () => setActiveOption(null));
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
