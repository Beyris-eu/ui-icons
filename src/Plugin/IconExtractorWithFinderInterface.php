<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

/**
 * Interface for ui_icons_extractor plugins.
 */
interface IconExtractorWithFinderInterface extends IconExtractorInterface {

  /**
   * Create files data from sources config.
   *
   * @return array<string, array<string, string|null>>
   *   List of files with metadata.
   */
  public function getFilesFromSources(): array;

}
