<?php

declare(strict_types=1);

namespace Drupal\ui_icons_test\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorBase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the ui_icons_extractor.
 */
#[IconExtractor(
  id: 'test',
  label: new TranslatableMarkup('Test'),
  description: new TranslatableMarkup('Test extractor.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class TestExtractor extends IconExtractorBase {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    $this->getFilesFromSources();
    return [];
  }

}
