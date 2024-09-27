<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

/**
 * Interface for ui_icons_extractor plugins.
 */
interface IconExtractorWithFinderInterface extends IconExtractorInterface {

  /**
   * Create files from sources config.
   *
   * @param array $sources
   *   The extractor config sources, path or url.
   * @param string $relative_path
   *   The definition relative path.
   *
   * @return array
   *   List of files with metadata.
   */
  public function getFilesFromSources(array $sources, string $relative_path): array;

}
