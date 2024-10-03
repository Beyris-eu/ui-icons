<?php

declare(strict_types=1);

namespace Drupal\ui_icons_test\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorWithFinder;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the ui_icons_extractor.
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
    $this->getFilesFromSources();
    return [];
  }

}
