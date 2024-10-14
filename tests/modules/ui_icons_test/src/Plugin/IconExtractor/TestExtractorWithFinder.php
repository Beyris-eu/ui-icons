<?php

declare(strict_types=1);

namespace Drupal\ui_icons_test\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorWithFinder;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the icon_extractor.
 */
#[IconExtractor(
  id: 'test_finder',
  label: new TranslatableMarkup('Test finder'),
  description: new TranslatableMarkup('Test finder extractor.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class TestExtractorWithFinder extends IconExtractorWithFinder {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    $files = $this->getFilesFromSources();
    $icons = [];
    foreach ($files as $file) {
      if (!isset($file['icon_id'])) {
        continue;
      }
      $icons[] = $this->createIcon($file['icon_id'], $file['source'], $file['group'] ?? NULL);
    }

    return $icons;
  }

}
