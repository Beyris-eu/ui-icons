<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Url;
use Drupal\ui_icons\Element\IconAutocomplete;

/**
 * Provides a form element to select an icon with a fancy picker.
 */
#[FormElement('icon_picker')]
class IconPicker extends IconAutocomplete {

  /**
   * Callback for creating form sub element icon_id.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element with icon_id element.
   */
  public static function processIcon(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element = parent::processIcon($element, $form_state, $complete_form);

    $element['icon_id']['#attributes'] = [
      'data-dialog-url' => Url::fromRoute('ui_icons_picker.ui')->toString(),
      'class' => [
        'form-icon-dialog',
      ],
    ];
    $element['icon_id']['#attached'] = [
      'library' => [
        'ui_icons_picker/picker',
      ],
    ];

    unset($element['icon_id']['#autocomplete_route_name']);
    unset($element['icon_id']['#autocomplete_query_parameters']);

    return $element;
  }

}
