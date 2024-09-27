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
  id: 'svg',
  label: new TranslatableMarkup('SVG'),
  description: new TranslatableMarkup('All files from one or many paths. Works for any file type.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class SvgExtractor extends IconExtractorWithFinder {

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
      $this->configuration['content'] = $this->extractSvg($file['absolute_path']);
      $icon_full_id = $this->configuration['icon_pack_id'] . ':' . $file['icon_id'];
      $icons[$icon_full_id] = $this->createIcon($file['icon_id'], $this->configuration, $file['source'], $file['group'] ?? NULL);
    }

    return $icons;
  }

  /**
   * Extract svg values, simply exclude parent <svg>.
   *
   * @param string $uri
   *   Local path to the svg file.
   *
   * @return string
   *   The inner SVG content as string.
   *
   * @todo allow some pattern for xpath to select children?
   */
  private function extractSvg(string $uri): string {
    $content = $this->iconFinder->getFileContents($uri);

    libxml_use_internal_errors(TRUE);
    $svg = simplexml_load_string($content);
    if ($svg === FALSE) {
      $errors = [];
      foreach (libxml_get_errors() as $error) {
        $errors[] = $error->message;
      }
      return implode(', ', $errors);
    }

    $content = '';
    foreach ($svg as $child) {
      $content .= $child->asXML();
    }
    return $content;
  }

}
