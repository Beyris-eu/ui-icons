<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\Plugin\IconExtractorPluginBase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the ui_icons_extractor.
 */
#[IconExtractor(
  id: 'manual',
  label: new TranslatableMarkup('Manual'),
  description: new TranslatableMarkup('Put the list of icons directly in the config.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class ManualExtractor extends IconExtractorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getIcons(): array {
    if (!isset($this->configuration['config']['icons'])) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: icons` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    $icons = [];
    foreach ($this->configuration['config']['icons'] as $icon) {
      if (!is_array($icon)) {
        continue;
      }

      $icon_full_id = $this->configuration['icon_pack_id'] . ':' . $icon['name'];
      // @todo relative path?
      $icons[$icon_full_id] = $this->createIcon($icon['name'], $icon['source'], $this->configuration, $icon['group'] ?? NULL);
    }

    return $icons;
  }

}
