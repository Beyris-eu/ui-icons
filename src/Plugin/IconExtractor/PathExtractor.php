<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\Plugin\IconExtractorWithFinder;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the ui_icons_extractor.
 */
#[IconExtractor(
  id: 'path',
  label: new TranslatableMarkup('Local path'),
  description: new TranslatableMarkup('All files from one or many paths. Works for any file type.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class PathExtractor extends IconExtractorWithFinder {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    if (!isset($this->configuration['config']['sources'])) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: sources` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    $files = $this->getFilesFromSources($this->configuration['config']['sources'] ?? [], $this->configuration['definition_relative_path'] ?? '');

    if (empty($files)) {
      return [];
    }

    $icons = [];
    foreach ($files as $file) {
      $icon_full_id = $this->configuration['icon_pack_id'] . ':' . $file['icon_id'];
      $icons[$icon_full_id] = $this->createIcon($file['icon_id'], $this->configuration, $file['source'], $file['group'] ?? NULL);
    }

    return $icons;
  }

}
