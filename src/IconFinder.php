<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * UI Icons finder for icon files under specific paths.
 *
 * Handle our `sources` format to describe paths.
 * Will search files with specific extension and extract `icon_id` and optional
 * `group` if set.
 * The `group` can be anywhere in the path and the `icon_id` can be a part of
 * the file name.
 * The result will include relative and absolute paths to the icon.
 * If the source start with a slash, `/`, path will be relative to the Drupal
 * installation, if not it will be relative to the definition folder.
 * The result Icon definition will be passed to the Extractor to prepare the
 * Icon to be returned as renderable.
 *
 * For example we have a definition file is in my_theme as
 * `my_theme.ui_icons.yml`, containing these `sources`:
 *
 * @code
 * sources:
 *   - assets/icons/{icon_id}.svg
 * @endcode
 * /DRUPAL_ROOT/web/themes/my_theme/icons/my_icon.svg
 * @code
 * [
 *   'name' => 'my_icon.svg',
 *   'icon_id' => 'my_icon',
 *   'relative_path' => '/themes/my_theme/icons/my_icon.svg',
 *   'absolute_path' => '/DRUPAL_ROOT/web/themes/my_theme/icons/my_icon.svg',
 *   'group' => NULL,
 * ]
 * @endcode
 *
 * @code
 * sources:
 *   - icons/prefix-{icon_id}.svg
 * @endcode
 * /DRUPAL_ROOT/web/themes/my_theme/icons/prefix-icon.svg
 * @code
 * [
 *   'name' => 'prefix-icon.svg',
 *   'icon_id' => 'icon',
 *   'relative_path' => '/themes/my_theme/icons/prefix-icon.svg',
 *   'absolute_path' => '/DRUPAL_ROOT/web/themes/my_theme/icons/prefix-icon.svg',
 *   'group' => NULL,
 * ],
 * @endcode
 *
 * @code
 * sources:
 *   - icons/{group}/{icon_id}.svg
 * @endcode
 * /DRUPAL_ROOT/web/themes/my_theme/icons/some_group/my_icon.svg
 * @code
 * [
 *   'name' => 'my_icon.svg',
 *   'icon_id' => 'my_icon',
 *   'relative_path' => '/themes/my_theme/icons/some_group/my_icon.svg',
 *   'absolute_path' => '/DRUPAL_ROOT/web/themes/my_theme/icons/some_group/my_icon.svg',
 *   'group' => 'some_group',
 * ]
 * @endcode
 *
 * @code
 * sources:
 *   - /libraries/my_library/icons/{group}/{icon_id}.svg
 * @endcode
 * /DRUPAL_ROOT/web/libraries/my_library/icons/other_group/lib_icon.svg
 * @code
 * [
 *   'name' => 'lib_icon.svg',
 *   'icon_id' => 'lib_icon',
 *   'relative_path' => '/libraries/my_library/icons/other_group/lib_icon.svg',
 *   'absolute_path' => '/DRUPAL_ROOT/web/libraries/my_library/icons/other_group/lib_icon.svg',
 *   'group' => 'other_group',
 * ]
 * @endcode
 */
class IconFinder implements ContainerInjectionInterface {

  use AutowireTrait;

  protected const GROUP_PATTERN = '{group}';
  protected const ICON_ID_PATTERN = '{icon_id}';

  /**
   * Constructs a new IconFinder.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFilesFromSource(string $source, string $drupal_root, string $definition_absolute_path, string $definition_relative_path): array {
    $is_absolute = str_starts_with($source, '/');
    $path_info = pathinfo($source);

    $group_position_end = TRUE;
    $group_position = strpos($path_info['dirname'], self::GROUP_PATTERN);
    $has_group = $group_position !== FALSE;

    if ($has_group) {
      $group_position_end = strlen($path_info['dirname']) === $group_position + strlen(self::GROUP_PATTERN);
      $path_search = rtrim(substr($path_info['dirname'], 0, $group_position), '/');
    }
    else {
      $path_search = rtrim($path_info['dirname'], '/');
    }

    $path_search = $is_absolute ? $drupal_root . $path_search : $definition_absolute_path . '/' . $path_search;

    $files = $this->findFiles($path_search, '#\\.' . $path_info['extension'] . '#', $group_position_end);

    if (empty($files)) {
      return [];
    }

    $base_relative_path = $is_absolute ? '' : $definition_relative_path . '/';

    return $this->createFileArray($files, $has_group, $group_position_end, $base_relative_path, $path_info);
  }

  /**
   * {@inheritdoc}
   */
  public function fileUrlGenerateString(string $uri): string {
    return $this->fileUrlGenerator->generateString($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileContents(string $uri): string {
    return \file_get_contents($uri);
  }

  /**
   * Scan a directory to find files.
   *
   * @param string $dir
   *   The path to search, without group if set.
   * @param string $mask
   *   The file mask, mostly *.extension.
   * @param bool $group_position_end
   *   Flag if the group value is at the end of the path.
   *
   * @return array
   *   List of files with metadata.
   */
  private function findFiles(string $dir, string $mask, bool $group_position_end): array {
    $options = [
      'recurse' => TRUE,
      'min_depth' => $group_position_end ? 0 : 2,
    ];

    try {
      return $this->fileSystem->scanDirectory($dir, $mask, $options);
    }
    catch (\Throwable $th) {
      // @todo error missing directory?
      return [];
    }
  }

  /**
   * Create files data from files list.
   *
   * @param array $files
   *   The list of files found.
   * @param bool $has_group
   *   Flag if there is a group value in path.
   * @param bool $group_position_end
   *   Flag if the group value is at the end of the path.
   * @param string $base_relative_path
   *   The relative path of the file without group if set.
   * @param array $path_info
   *   The path info from pathinfo().
   *
   * @return array
   *   List of files with custom metadata.
   */
  private function createFileArray(array $files, bool $has_group, bool $group_position_end, string $base_relative_path, array $path_info): array {
    $result = [];

    foreach ($files as $file) {
      $group = $this->determineGroup($file->uri, $has_group, $group_position_end, $path_info['dirname']);
      $uri = $this->buildDrupalUri($has_group, $group, $path_info['dirname'], $base_relative_path, $file->filename);

      $filename = $icon_id = $file->name;
      $icon_id = $this->extractIconId($path_info['filename'], $filename);

      if (!$icon_id) {
        continue;
      }

      $result[$filename] = [
        'name' => $icon_id,
        'icon_id' => $icon_id,
        'relative_path' => $uri,
        'absolute_path' => $file->uri,
        'group' => $group,
      ];
    }

    return $result;
  }

  /**
   * Check if icon_id is a part of the name and need to be extracted.
   *
   * @param string $path_filename
   *   The path and filename to extract icon ID from.
   * @param string $filename
   *   The filename to match against.
   *
   * @return string
   *   The extracted icon ID or the original filename.
   */
  private function extractIconId(string $path_filename, string $filename): ?string {
    if ($path_filename !== self::ICON_ID_PATTERN) {
      $pattern = str_replace(self::ICON_ID_PATTERN, '(?<icon_id>.+?)', $path_filename);
      if (preg_match('@' . $pattern . '@', $filename, $matches)) {
        // @todo should return null? add test to know.
        if (isset($matches['icon_id'])) {
          return $matches['icon_id'];
        }
      }
      else {
        return NULL;
      }
    }

    return $filename;
  }

  /**
   * Builds a Drupal URI based on the provided parameters.
   *
   * @param bool $has_group
   *   Indicates if a group is present.
   * @param string|null $group
   *   The group value (if present).
   * @param string $dirname
   *   The directory name.
   * @param string $base_relative_path
   *   The base relative path.
   * @param string $filename
   *   The filename to append.
   *
   * @return string
   *   The generated Drupal URI.
   */
  private function buildDrupalUri(bool $has_group, ?string $group, string $dirname, string $base_relative_path, string $filename): string {
    $current_relative_path = $has_group ? str_replace(self::GROUP_PATTERN, $group, $dirname) : $dirname;
    $current_relative_path = $base_relative_path . $current_relative_path;
    $current_relative_path = sprintf('%s/%s', $current_relative_path, $filename);

    return $this->fileUrlGenerateString($current_relative_path);
  }

  /**
   * Determines the group based on the URI and other parameters.
   *
   * @param string $uri
   *   The URI to extract group from.
   * @param bool $has_group
   *   Indicates if a group is present.
   * @param bool $group_position_end
   *   Indicates if the group position is at the end.
   * @param string $dirname
   *   The directory name pattern.
   *
   * @return string|null
   *   The determined group or null.
   */
  private function determineGroup(string $uri, bool $has_group, bool $group_position_end, string $dirname): ?string {
    if (!$has_group) {
      return NULL;
    }

    if ($group_position_end) {
      return basename(dirname($uri));
    }

    $pattern = str_replace(self::GROUP_PATTERN, '(?P<group>.+)?', $dirname);
    preg_match('@' . $pattern . '@', $uri, $matches);
    return $matches['group'] ?? NULL;
  }

}
