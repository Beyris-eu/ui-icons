<?php

declare(strict_types=1);

namespace Drupal\ui_icons_popover\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Turns the preview box of an 'icon_autocomplete' element into a popover.
 *
 * Added to the 'icon_autocomplete' #process chain by
 * UiIconsPopoverHooks::elementInfoAlter(). It only carries the data the
 * popover needs on the icon_id input, the preview box is wired up client side
 * so the element degrades to a plain autocomplete without JavaScript.
 *
 * Set '#show_picker' to FALSE on the element to opt out, the same switch the
 * ui_icons_picker modal uses.
 *
 * @see \Drupal\ui_icons\Element\IconAutocomplete
 * @see \Drupal\ui_icons_popover\Hook\UiIconsPopoverHooks
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
final class IconPopoverTrigger {

  /**
   * Callback attaching the popover to an icon_autocomplete element.
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
   *   The processed element with the popover data attributes.
   */
  public static function processPopoverTrigger(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    if (!isset($element['icon_id']) || FALSE === (bool) ($element['#show_picker'] ?? TRUE)) {
      return $element;
    }

    // Not 'data-icon-popover': that one makes the script replace the input,
    // which would trade away the autocomplete.
    $element['icon_id']['#attributes']['data-icon-popover-trigger'] = 'true';
    IconPopover::setPopoverAttributes($element);

    return $element;
  }

}
