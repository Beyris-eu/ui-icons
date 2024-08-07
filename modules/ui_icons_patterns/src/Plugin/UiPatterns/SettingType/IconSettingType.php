<?php

namespace Drupal\ui_icons_patterns\Plugin\UIPatterns\SettingType;

use Drupal\ui_patterns_settings\Definition\PatternDefinitionSetting;
use Drupal\ui_patterns_settings\Plugin\PatternSettingTypeBase;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Icon setting type.
 *
 * @UiPatternsSettingType(
 *   id = "icon",
 *   label = @Translation("Icon")
 * )
 */
class IconSettingType extends PatternSettingTypeBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, $value, PatternDefinitionSetting $def, $form_type) {
    $value = $this->getValue($value);
    $form[$def->getName()] = [
      '#type' => 'ui_icon_autocomplete',
      '#title' => $def->getLabel(),
      '#default_value' => isset($value['icon']) ? $value['icon']->getId() : '',
      '#default_settings' => $value['settings'] ?? [],
      '#show_settings' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess($value, array $context) {
    if (!is_array($value)) {
      return [
        "iconset" => '',
        "icon" => '',
        "options" => [],
      ];
    }
    // Value not coming from ::settingsForm(), like component definition's
    // preview, has an already resolved flat structure with primitive only.
    if (is_string($value['icon']) && isset($value['iconset']) ) {
      // @todo: Replace by return $value once UiIconsTwigExtension accepts null options
      return [
        "iconset" => $value['iconset'],
        "icon" => $value['icon'],
        "options" => $value['options'] ?? [],
      ];
    }
    // Data coming from ::settingsForm() have an IconDefinition objects.
    $icon = $value['icon'];
    return [
      "iconset" => $icon->getIconsetId(),
      "icon" => $icon->getName(),
      "options" => $value['settings'] ?? [],
    ];
  }

}
