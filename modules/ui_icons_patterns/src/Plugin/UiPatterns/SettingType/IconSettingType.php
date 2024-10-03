<?php

namespace Drupal\ui_icons_patterns\Plugin\UIPatterns\SettingType;

use Drupal\ui_icons\IconDefinition;
use Drupal\ui_patterns_settings\Definition\PatternDefinitionSetting;
use Drupal\ui_patterns_settings\Plugin\PatternSettingTypeBase;

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
      '#type' => 'icon_autocomplete',
      '#title' => $def->getLabel(),
      '#default_value' => $value['target_id'] ?? '',
      '#default_settings' => $value['settings'] ?? [],
      '#show_settings' => TRUE,
      '#return_id' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess($value, array $context) {
    if (!is_array($value)) {
      return [
        'icon_pack' => '',
        'icon' => '',
        'settings' => [],
      ];
    }
    // Value not coming from ::settingsForm(), like component definition's
    // preview, has an already resolved flat structure with primitive only.
    if (is_string($value['icon']) && isset($value['icon_pack'])) {
      return $value;
    }
    // Data coming from ::settingsForm() have an IconDefinition objects.
    [$pack_id, $icon_id] = explode(IconDefinition::ICON_SEPARATOR, $value['target_id']);
    return [
      'icon_pack' => $pack_id ?: '',
      'icon' => $icon_id ?: '',
      'settings' => $value['settings'][$pack_id] ?? [],
    ];
  }

}
