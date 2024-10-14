<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorWithFinder;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the icon_extractor.
 *
 * This extractor need the file content, remote url will raise too much risks
 * and slow down if the file is not available anymore. Then `path` extractor
 * must be used.
 */
#[IconExtractor(
  id: 'svg',
  label: new TranslatableMarkup('SVG'),
  description: new TranslatableMarkup('Handle SVG files from one or many paths.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class SvgExtractor extends IconExtractorWithFinder {

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
      if (!$content = $this->extractSvg($file['absolute_path'] ?? '')) {
        continue;
      }
      $icons[] = $this->createIcon(
        $file['icon_id'],
        $file['source'],
        $file['group'] ?? NULL,
        [
          'content' => $content,
        ],
      );
    }

    return $icons;
  }

  /**
   * Extract svg values, simply exclude parent <svg>.
   *
   * @param string $source
   *   Local path or url to the svg file.
   *
   * @return string|null
   *   The inner SVG content as string.
   *
   * @todo allow some pattern for xpath to select children?
   */
  private function extractSvg(string $source): ?string {
    if (!$content = $this->iconFinder->getFileContents($source)) {
      return NULL;
    }

    libxml_use_internal_errors(TRUE);

    if (!$svg = simplexml_load_string((string) $content)) {
      // @todo log a warning with the xml error.
      return NULL;
    }

    $content = '';
    foreach ($svg as $child) {
      $content .= $child->asXML();
    }

    return $content;
  }

}
