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
   * @param array $sources
   *   The list of paths or urls.
   * @param string $definition_relative_path
   *   The current definition relative path.
   *
   * @return array
   *   List of files with metadata.
   */
  public function getFilesFromSources(array $sources, string $definition_relative_path): array;

  /**
   * Wrapper tho the file url generator.
   *
   * To avoid injection in IconExtractorBase.
   *
   * @param string $uri
   *   The uri to process.
   *
   * @return string
   *   The Drupal url access of the uri.
   */
  public function fileUrlGenerateString(string $uri): string;

  /**
   * Wrapper tho the file_get_contents function..
   *
   * @param string $uri
   *   The uri to process.
   *
   * @return string
   *   The file content.
   */
  public function getFileContents(string $uri): string;

}
