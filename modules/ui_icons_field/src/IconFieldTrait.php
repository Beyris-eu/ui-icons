<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field;

/**
 * Provides a trait for icon field.
 */
trait IconFieldTrait {

  /**
   * Get the icon rendering position options available to the link formatter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of options for position options.
   */
  public function getDisplayPositions(): array {
    return [
      'before' => $this->t('Before'),
      'after' => $this->t('After'),
      'icon_only' => $this->t('Icon only'),
    ];
  }

  /**
   * Get the icon selector options.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of options for selectors options.
   */
  private function getPickerOptions(): array {
    if (\Drupal::moduleHandler()->moduleExists('ui_icons_picker')) {
      return [
        'icon_autocomplete' => $this->t('Autocomplete'),
        'icon_picker' => $this->t('Picker'),
      ];
    }

    return [
      'icon_autocomplete' => $this->t('Autocomplete'),
    ];
  }

  /**
   * Get the icon selector element type.
   *
   * 'icon_picker' comes from the optional ui_icons_picker module. An unknown
   * '#type' renders as nothing rather than failing, which would leave the
   * field with no icon input at all, so fall back when it is gone.
   *
   * @return string
   *   The form element type.
   */
  private function getIconSelector(): string {
    $icon_selector = $this->getSetting('icon_selector');
    if (!\Drupal::service('plugin.manager.element_info')->hasDefinition($icon_selector)) {
      return 'icon_autocomplete';
    }

    return $icon_selector;
  }

  /**
   * Get the icon selector autocomplete format.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of format for selectors options.
   */
  private function getAutocompleteFormat(): array {
    return [
      'list' => $this->t('List'),
      'grid' => $this->t('Grid'),
    ];
  }

}
