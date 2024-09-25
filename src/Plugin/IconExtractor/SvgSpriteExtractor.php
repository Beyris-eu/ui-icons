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
  id: 'svg_sprite',
  label: new TranslatableMarkup('SVG Sprite'),
  description: new TranslatableMarkup('Open an SVG XML file and get the icons.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class SvgSpriteExtractor extends IconExtractorWithFinder {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    if (!isset($this->configuration['config']['sources'])) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: sources` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    $files = $this->getFilesFromSources($this->configuration['config']['sources'] ?? [], $this->configuration['_path_info'] ?? []);

    if (empty($files)) {
      return [];
    }

    $icons = [];
    foreach ($files as $file) {
      $icon_ids = $this->extractIdsFromXml($file['absolute_path']);
      foreach ($icon_ids as $icon_id) {
        $icon_full_id = $this->configuration['icon_pack_id'] . ':' . $icon_id;
        $icons[$icon_full_id] = $this->createIcon($icon_id, $this->configuration, $file['relative_path'], $file['group']);
      }
    }

    return $icons;
  }

  /**
   * Extract icon ID from XML.
   *
   * @param string $source
   *   Path to the SVG file.
   *
   * @return array
   *   A list of icons ID.
   */
  private function extractIdsFromXml(string $source): array {
    $content = $this->iconFinder->getFileContents($source);

    libxml_use_internal_errors(TRUE);
    $svg = simplexml_load_string($content);
    if ($svg === FALSE) {
      $errors = [];
      foreach (libxml_get_errors() as $error) {
        $errors[] = trim($error->message);
      }
      return $errors;
    }
    if ($svg->symbol) {
      return $this->extractIdsFromSymbols($svg->symbol);
    }
    if ($svg->defs->symbol) {
      return $this->extractIdsFromSymbols($svg->defs->symbol);
    }
    return [];
  }

  /**
   * Extract icon ID from SVG symbols.
   *
   * @param \SimpleXMLElement $wrapper
   *   A SVG element.
   *
   * @return array
   *   A list of icons ID.
   */
  private function extractIdsFromSymbols(\SimpleXMLElement $wrapper): array {
    $ids = [];
    foreach ($wrapper as $symbol) {
      if (isset($symbol['id'])) {
        $ids[] = (string) $symbol['id'];
      }
    }
    return $ids;
  }

}
