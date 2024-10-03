<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorWithFinder;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the ui_icons_extractor.
 */
#[IconExtractor(
  id: 'path',
  label: new TranslatableMarkup('Path or URL'),
  description: new TranslatableMarkup('Handle paths or urls for icons.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class PathExtractor extends IconExtractorWithFinder {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    $files = $this->getFilesFromSources();

    if (empty($files)) {
      return [];
    }

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
