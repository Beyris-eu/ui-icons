<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

/**
 * UI Icons finder for icon files under specific paths or URLs.
 *
 * This class locates icon files based on a provided source, which can be either
 * a local path or a URL.
 *
 * URLs:
 * For URLs, the source is treated as the direct URL to the icon resource.
 *
 * Local Paths:
 * For local paths, the class leverage Symfony Finder features with some extra
 * functionalities related to our Icon definition.
 * - Icon ID Extraction (`{icon_id}`): A placeholder `{icon_id}` within
 *   the filename allows extracting a portion as the icon ID. For
 *   example, a source definition like `/{icon_id}-24.svg` would extract
 *   "book" as the icon ID from the file "book-24.svg".
 * - Group Metadata Extraction (`{group}`): A placeholder `{group}` within
 *   the path allows extracting a folder name as group metadata for the icon.
 *   For instance, a source definition like `/foo/{group}/*` for the file
 *   "foo/outline/icon.svg" would assign "outline" as the group for the icon.
 *
 * The source path can be:
 * - Absolute: Starting with a slash `/`, indicating a path relative to the
 *   Drupal installation root.
 * - Relative: Without a leading slash, indicating a path relative to the
 *   definition folder.
 *
 * The class returns an array containing information about the found icon
 * files, including their relative and absolute paths, as well as the extracted
 * group metadata.
 * This information is then used by Icon Extractor plugins to process and
 * prepare the icons for rendering.
 */
class IconFinder implements ContainerInjectionInterface, IconFinderInterface {

  use AutowireTrait;

  /**
   * Pattern to match a group placeholder in a source path.
   *
   * This constant is used to identify and extract group metadata from source
   * paths defined for icon sets.
   */
  private const GROUP_PATTERN = '{group}';

  /**
   * Pattern to match an icon ID placeholder in a filename.
   *
   * This constant is used to identify and extract icon IDs from filenames
   * within source paths defined for icon sets.
   */
  private const ICON_ID_PATTERN = '{icon_id}';

  /**
   * List of allowed file extensions for icon files.
   *
   * This restriction is in place for security reasons, limiting the search
   * to common image file types for Icons.
   */
  private const LIMIT_SEARCH_EXT = ['gif', 'svg', 'png', 'gif'];

  /**
   * Keep track of the icons.
   *
   * Used to increment Icon name when already exist in the sources.
   */
  private array $countIcons = [];

  /**
   * Constructs a new IconFinder object.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   * @param string $appRoot
   *   The application root.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly string $appRoot,
  ) {}

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
    $content = \file_get_contents($uri);
    if (FALSE === $content) {
      return '';
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilesFromSources(array $sources, string $definition_relative_path): array {
    $files = [];
    foreach ($sources as $source) {
      if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
        $files = array_merge($files, self::getFileFromHttpUrl($source));
      }
      else {
        $files = array_merge($files, $this->getFilesFromPath($source, $definition_relative_path));
      }
    }

    return $files;
  }

  /**
   * Get files from an HTTP URL.
   *
   * Depending extractor, `source` is used as url or path to the Icon.
   * For example SVG will need `absolute_path` to read the content and extract
   * the Icon content for wrapping.
   *
   * @param string $source
   *   The path or url.
   *
   * @return array
   *   An array of a single icon file information, containing:
   *   - 'icon_id': The cleaned filename.
   *   - 'source': The url to the Icon.
   *   - 'absolute_path': The url to the Icon.
   */
  private static function getFileFromHttpUrl(string $source): array {
    $source = urldecode($source);
    $path_info = pathinfo($source);
    $icon_id = self::getCleanIconId($path_info['filename']);

    return [
      $icon_id => [
        'icon_id' => $icon_id,
        'source' => $source,
        'absolute_path' => $source,
      ],
    ];
  }

  /**
   * Get files from a local path.
   *
   * This is a wrapper to use Symfony Finder with 2 extras features {group} and
   * {icon_id}.
   *
   * @param string $source
   *   The source path, which can be absolute (starting with '/') or relative
   *   to the definition folder.
   * @param string $definition_relative_path
   *   The relative path to the definition folder.
   *
   * @return array
   *   An array of icon file information, with each element containing:
   *   - 'icon_id': The extracted icon ID.
   *   - 'source': The source path to the icon file.
   *   - 'absolute_path': The absolute path to the icon file.
   *   - 'group': The extracted group metadata.
   */
  private function getFilesFromPath(string $source, string $definition_relative_path): array {
    // If we have {group} in path, we replace by wildcard for Symfony Finder.
    $source_without_group = str_replace(self::GROUP_PATTERN, '*', $source);
    $path_info = pathinfo($source_without_group);

    if (!isset($path_info['dirname'])) {
      return [];
    }

    $dirname = rtrim($path_info['dirname'], '/');
    $is_absolute = str_starts_with($source, '/');
    $path_search = $is_absolute ? $this->appRoot . $dirname : sprintf('%s/%s/%s', $this->appRoot, $definition_relative_path, $dirname);

    // Check {icon_id} pattern, keep track and replace by wildcard.
    $path_info_filename = $path_info['filename'];
    $has_icon_pattern = FALSE;
    if (FALSE !== strrpos($path_info['filename'], self::ICON_ID_PATTERN)) {
      $has_icon_pattern = TRUE;
      $path_info['filename'] = str_replace(self::ICON_ID_PATTERN, '*', $path_info['filename']);
    }

    $finder = self::findFiles($path_search, $path_info, $has_icon_pattern);
    if (NULL === $finder) {
      return [];
    }

    // Check {group} information.
    $path_info_group = pathinfo($source);
    $has_group = (FALSE !== strpos($source, self::GROUP_PATTERN));
    $group_position = $has_group ? self::determineGroupPosition($path_info_group, $is_absolute, $definition_relative_path) : NULL;

    return $this->processFoundFiles($finder, $has_group, $group_position, $has_icon_pattern, $path_info_filename);
  }

  /**
   * Creates a Finder instance with configured patterns and return result.
   *
   * @param string $finder_path
   *   The path to search in.
   * @param array $path_info
   *   The file path info.
   * @param bool $has_icon_pattern
   *   The filename contains {icon_id} pattern.
   *
   * @return \Symfony\Component\Finder\Finder|null
   *   The configured Finder instance.
   */
  private static function findFiles(string $finder_path, array $path_info, bool $has_icon_pattern): ?Finder {
    $finder_names = self::determineFinderNames($path_info, $has_icon_pattern);

    $finder = new Finder();
    try {
      $finder
        ->depth(0)
        ->in($finder_path)
        ->files()
        ->name($finder_names)
        ->sortByExtension();
    }
    catch (\Throwable $th) {
      // @todo log invalid folders?
      return NULL;
    }

    if (!$finder->hasResults()) {
      return NULL;
    }

    return $finder;
  }

  /**
   * Processes found files and extracts icon information.
   *
   * @param \Symfony\Component\Finder\Finder $finder
   *   The Finder instance with found files.
   * @param bool $has_group
   *   Whether the source path contains a group pattern.
   * @param int|null $group_position
   *   The position of the group in the path, or null if not applicable.
   * @param bool $has_icon_pattern
   *   The path include the icon_id pattern.
   * @param string $path_info_filename
   *   The filename pattern used for matching.
   *
   * @return array
   *   An array of processed icon information.
   */
  private function processFoundFiles(Finder $finder, bool $has_group, ?int $group_position, bool $has_icon_pattern, string $path_info_filename): array {
    $result = [];

    foreach ($finder as $file) {
      $relative_path = str_replace($this->appRoot, '', $file->getPathName());
      $group = $has_group ? $this->extractGroupFromPath($relative_path, $group_position) : '';

      $filename = $file->getFilenameWithoutExtension();
      $icon_id = self::getCleanIconId($filename);

      if ($has_icon_pattern) {
        $icon_id = self::extractIconIdFromFilename($filename, $path_info_filename);
      }

      // Track existing Id and increment.
      $this->countIcons[$icon_id] = $this->countIcons[$icon_id] ?? 0;
      if (isset($result[$icon_id])) {
        $this->countIcons[$icon_id]++;
        $icon_id .= '__' . $this->countIcons[$icon_id];
      }

      $result[$icon_id] = [
        'icon_id' => $icon_id,
        'source' => $this->fileUrlGenerateString($relative_path),
        'absolute_path' => $file->getPathName(),
        'group' => $group,
      ];
    }

    return $result;
  }

  /**
   * Extracts the group from a file path based on the group position.
   *
   * @param string $path
   *   The file path.
   * @param int|null $group_position
   *   The position of the group in the path, or null if not applicable.
   *
   * @return string
   *   The extracted group, or an empty string if not found.
   */
  private function extractGroupFromPath(string $path, ?int $group_position): string {
    if (is_null($group_position)) {
      return '';
    }

    $parts = explode('/', trim($path, '/'));
    return $parts[$group_position] ?? '';
  }

  /**
   * Check if icon_id is a part of the name and need to be extracted.
   *
   * @param array $path_info
   *   The file path info.
   * @param bool $has_icon_pattern
   *   The file name contains the {icon_id} placeholder.
   *
   * @return string
   *   The names string to use in the Finder.
   */
  private static function determineFinderNames(array $path_info, bool $has_icon_pattern): string {
    // In case of full filename, return directly.
    if (FALSE === strpos($path_info['filename'], '*')) {
      return $path_info['basename'];
    }

    // If an extension is set wwe replace wildcard by our limited list of images
    // to avoid listing of files.
    if (isset($path_info['extension'])) {
      // We can have multiple extensions with glob brace: {png,svg}. So check if
      // we allow them.
      if (FALSE !== strpos($path_info['extension'], '{')) {
        $source_names = explode(',', str_replace(['{', '}', ' '], '', $path_info['extension']));
        $names = array_intersect($source_names, self::LIMIT_SEARCH_EXT);
        return Glob::toRegex($path_info['filename'] . '.{' . implode(',', $names) . '}');
      }

      if (in_array($path_info['extension'], self::LIMIT_SEARCH_EXT)) {
        return $path_info['filename'] . '.' . $path_info['extension'];
      }
    }

    // Default match for images.
    return Glob::toRegex($path_info['filename'] . '.{' . implode(',', self::LIMIT_SEARCH_EXT) . '}');
  }

  /**
   * Check if {icon_id} is a part of the name and need to be extracted.
   *
   * @param string $filename
   *   The filename found to match against.
   * @param string $filename_pattern
   *   The path with {icon_id}.
   *
   * @return string
   *   The extracted icon ID or the original filename.
   */
  private static function extractIconIdFromFilename(string $filename, string $filename_pattern): ?string {
    $pattern = str_replace(self::ICON_ID_PATTERN, '(?<icon_id>.+)?', $filename_pattern);
    if (preg_match('@' . $pattern . '@', $filename, $matches)) {
      return $matches['icon_id'] ?? NULL;
    }

    return NULL;
  }

  /**
   * Determines the group based on the URI and other parameters.
   *
   * @param array $path_info
   *   The file path info.
   * @param bool $is_absolute
   *   The file source is absolute, ie: relative to Drupal core.
   * @param string $definition_relative_path
   *   The definition file relative path.
   *
   * @return int|null
   *   The determined group position.
   */
  private static function determineGroupPosition(array $path_info, bool $is_absolute, string $definition_relative_path): ?int {
    $absolute_path = $path_info['dirname'];
    if (!$is_absolute) {
      $absolute_path = sprintf('%s/%s', $definition_relative_path, $path_info['dirname']);
    }
    $parts = explode('/', trim($absolute_path, '/'));

    $result = array_search(self::GROUP_PATTERN, $parts, TRUE);
    if (FALSE === $result) {
      return NULL;
    }
    return (int) $result;
  }

  /**
   * Generate a clean Icon Id.
   *
   * @param string $name
   *   The name to clean.
   *
   * @return string
   *   The cleaned string used as id.
   */
  private static function getCleanIconId(string $name): string {
    $clean_name = preg_replace('@[^a-z0-9_-]+@', '_', mb_strtolower($name));
    if (NULL === $clean_name) {
      return $name;
    }
    return trim($clean_name, '_');
  }

}
