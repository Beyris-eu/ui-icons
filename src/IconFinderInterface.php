<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

/**
 * Interface for UI Icons finder.
 */
interface IconFinderInterface {

  /**
   * Create files from source paths.
   *
   * @param string[] $sources
   *   The list of paths or urls.
   * @param string $relative_path
   *   The current definition relative path.
   *
   * @return array<string, array<string, string|null>>
   *   List of files with metadata.
   */
  public function getFilesFromSources(array $sources, string $relative_path): array;

  /**
   * Wrapper to the file_get_contents function.
   *
   * This allow usage in extractor and easier unit test.
   *
   * @param string $uri
   *   The uri to process.
   *
   * @return string|bool
   *   The file content.
   */
  public function getFileContents(string $uri): string|bool;

}
