<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'icon',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('Get an icon from UI Icons module.'),
  prop_types: ['icon']
)]
class IconSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $value = $this->getSetting('value');
    [$pack_id, $icon_id] = explode(IconDefinition::ICON_SEPARATOR, $value['icon_id']);
    return [
      'icon_pack' => $pack_id ?: '',
      'icon' => $icon_id ?: '',
      'settings' => $value['icon_settings'][$pack_id] ?? [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $value = $this->getSetting('value');
    return [
      'value' => [
        '#type' => 'icon_autocomplete',
        '#default_value' => $value['icon_id'] ?? '',
        '#default_settings' => $value['icon_settings'] ?? [],
        '#show_settings' => TRUE,
        '#return_id' => TRUE,
      ],
    ];
  }

}
