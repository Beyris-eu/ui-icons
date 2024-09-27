<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

/**
 * UI Icons finder for icon files under specific paths or urls.
 *
 * Handle our `sources` format to describe paths or urls, for paths:
 * Will search files with specific extension and extract optional `{icon_id}`
 * and `{group}` if set.
 * The `{group}` can be anywhere in the path and the `{icon_id}` can be a part
 * of the file name.
 *
 * The result will include relative and absolute paths to the icon.
 *
 * If the source start with a slash, `/`, path will be relative to the Drupal
 * installation, if not it will be relative to the definition folder.
 *
 * The result Icon definition will be passed to the Extractor to prepare the
 * Icon to be returned as renderable.
 *
 * For urls the source will be the direct url to the resource.
 */
class IconFinder implements ContainerInjectionInterface, IconFinderInterface {

  use AutowireTrait;

  private const GROUP_PATTERN = '{group}';
  private const ICON_ID_PATTERN = '{icon_id}';

  /**
   * For security, do not allow search other than images.
   */
  private const LIMIT_SEARCH_EXT = ['gif', 'svg', 'png', 'gif'];

  /**
   * Track the list of icons for each call of this class.
   */
  private array $countIcons = [];

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
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
  public function getFilesFromSource(string $source, string $drupal_root, string $definition_absolute_path, string $definition_relative_path): array {
    if (FALSE !== filter_var($source, FILTER_VALIDATE_URL)) {
      return self::getFileFromHttpUrl($source);
    }

    return $this->getFilesFromPath($source, $drupal_root, $definition_absolute_path, $definition_relative_path);
  }

  /**
   * Get files from a local path.
   *
   * @param string $source
   *   The path or url.
   * @param string $drupal_root
   *   The Drupal root.
   * @param string $definition_absolute_path
   *   The current definition absolute path.
   * @param string $definition_relative_path
   *   The current definition relative path.
   *
   * @return array
   *   List of files with metadata.
   */
  private function getFilesFromPath(string $source, string $drupal_root, string $definition_absolute_path, string $definition_relative_path): array {

    $source_group = str_replace(self::GROUP_PATTERN, '*', $source);
    $path_info = pathinfo($source_group);

    if (!isset($path_info['dirname'])) {
      return [];
    }

    $path_info_group = pathinfo($source);
    $is_absolute = str_starts_with($source_group, '/');

    $dirname = rtrim($path_info['dirname'], '/');
    $path_search = $is_absolute ? $drupal_root . $dirname : $definition_absolute_path . '/' . $dirname;

    $path_info_filename = $path_info['filename'];
    $has_icon_pattern = FALSE;
    if (FALSE !== strrpos($path_info['filename'], self::ICON_ID_PATTERN)) {
      $has_icon_pattern = TRUE;
      $path_info['filename'] = str_replace(self::ICON_ID_PATTERN, '*', $path_info['filename']);
    }

    $names = self::determineFinderNames($path_info, $has_icon_pattern);
    $finder = new Finder();
    try {
      $finder
        ->depth(0)
        ->in($path_search)
        ->files()
        ->name($names)
        ->sortByExtension();
    }
    catch (\Throwable $th) {
      // @todo log invalid folders?
      return [];
    }
    if (!$finder->hasResults()) {
      return [];
    }

    $has_group = (FALSE !== strpos($source, self::GROUP_PATTERN));

    $group_position = 0;
    if ($has_group) {
      $group_position = self::determineGroupPosition($path_info_group, $is_absolute, $definition_relative_path);
    }

    $result = [];
    foreach ($finder as $file) {
      $group = '';
      if ($has_group) {
        $parts = explode('/', trim(str_replace($drupal_root, '', $file->getPath()), '/'));
        $group = $parts[$group_position] ?? '';
      }

      $filename = $file->getFilenameWithoutExtension();
      $icon_id = self::getCleanIconId($filename) ?? $filename;
      if ($has_icon_pattern) {
        $icon_id = self::determineIconId($path_info_filename, $icon_id);
      }

      $countIcons[$icon_id] = $countIcons[$icon_id] ?? 0;
      if (isset($result[$icon_id])) {
        $countIcons[$icon_id]++;
        $icon_id .= '__' . $countIcons[$icon_id];
      }

      $result[$icon_id] = [
        'icon_id' => $icon_id,
        'relative_path' => $this->fileUrlGenerateString(str_replace($drupal_root, '', $file->getPathName())),
        'absolute_path' => $file->getPathName(),
        'group' => $group,
      ];
    }

    return $result;
  }

  /**
   * Get files from an HTTP URL.
   *
   * @param string $source
   *   The path or url.
   *
   * @return array
   *   List of files with metadata.
   */
  private static function getFileFromHttpUrl(string $source): array {
    $path_info = pathinfo($source);
    $icon_id = self::getCleanIconId($path_info['filename']) ?? $path_info['filename'];
    return [
      $icon_id => [
        'icon_id' => $icon_id,
        'relative_path' => $source,
        'absolute_path' => $source,
        'group' => '',
      ],
    ];
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
   * @param string $mask
   *   The path with {icon_id}.
   * @param string $filename
   *   The filename found to match against.
   *
   * @return string
   *   The extracted icon ID or the original filename.
   */
  private static function determineIconId(string $mask, string $filename): ?string {
    $pattern = str_replace(self::ICON_ID_PATTERN, '(?<icon_id>.+)?', $mask);
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
   * @return string|null
   *   The cleaned string used as id.
   */
  private static function getCleanIconId(string $name): ?string {
    return preg_replace('@[^a-z0-9_-]+@', '_', mb_strtolower($name));
  }

}
