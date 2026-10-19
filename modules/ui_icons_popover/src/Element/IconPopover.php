<?php

declare(strict_types=1);

namespace Drupal\ui_icons_popover\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Url;
use Drupal\ui_icons\Element\IconAutocomplete;

/**
 * Provides a form element to select an icon from an in-place popover.
 *
 * Same properties as the 'icon_autocomplete' element, except #result_format
 * and #max_result which are ignored. The server renders a plain text input,
 * js/icon.popover.js hides it and adds the trigger button and the popover,
 * so the element still works without JavaScript.
 *
 * @see \Drupal\ui_icons\Element\IconAutocomplete
 *
 * Usage example:
 * @code
 * $form['icon'] = [
 *   '#type' => 'icon_popover',
 *   '#title' => $this->t('Select icon'),
 *   '#default_value' => 'my_icon_pack:my_default_icon',
 *   '#allowed_icon_pack' => [
 *     'my_icon_pack',
 *     'other_icon_pack',
 *   ],
 *   '#show_settings' => TRUE,
 * ];
 * @endcode
 */
#[FormElement('icon_popover')]
class IconPopover extends IconAutocomplete {

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

    unset($element['icon_id']['#autocomplete_route_name']);
    unset($element['icon_id']['#autocomplete_query_parameters']);
    $element['icon_id']['#description'] = new TranslatableMarkup('Enter the icon id as pack_id:icon_id.');

    $attributes = &$element['icon_id']['#attributes'];
    $attributes['data-icon-popover'] = 'true';
    $attributes['data-index-url'] = Url::fromRoute('ui_icons_popover.index')->toString();
    $attributes['data-previews-url'] = Url::fromRoute('ui_icons_popover.previews')->toString();
    if (!empty($element['#allowed_icon_pack'])) {
      $attributes['data-allowed-icon-pack'] = implode('+', $element['#allowed_icon_pack']);
    }

    $value = $element['icon_id']['#value'] ?? '';
    if (is_string($value) && str_contains($value, IconDefinition::ICON_SEPARATOR) && $icon = self::iconPack()->getIcon($value)) {
      $attributes['data-icon-label'] = $icon->getLabel();
    }

    $element['icon_id']['#attached']['library'][] = 'ui_icons_popover/popover';
    // Previews come back as JSON, which carries no assets, so the libraries
    // of the packs they show must already be on the page.
    foreach (self::iconPack()->getDefinitions() ?? [] as $pack_id => $definition) {
      if (!empty($definition['library']) && static::isPackAllowed($element, $pack_id)) {
        $element['icon_id']['#attached']['library'][] = $definition['library'];
      }
    }

    return $element;
  }

}
