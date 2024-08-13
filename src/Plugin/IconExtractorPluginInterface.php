<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ui_icons\IconDefinition;

/**
 * Interface for ui_icons_extractor plugins.
 */
interface IconExtractorPluginInterface extends PluginFormInterface {

  /**
   * Get a list of all the icons available for this extractor.
   *
   * The icons provided as an associative array with the keys and values equal
   * to the icon ID and icon definition respectively.
   *
   * @return \Drupal\ui_icons\IconDefinition[]
   *   Gets a built list of icons that are found by this extractor. Array is
   *   keyed by the icon ID and the array values are the icon definition for
   *   each of the icons listed.
   */
  public function getIcons(): array;

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   */
  public function description(): string;

  /**
   * Create the icon definition.
   *
   * @param string $name
   *   The name of the icon.
   * @param string $path
   *   The path of the icon.
   * @param array $data
   *   The additional data of the icon.
   * @param string|null $group
   *   The group of the icon (optional).
   *
   * @return \Drupal\ui_icons\IconDefinition
   *   The Icon definition.
   */
  public static function createIcon(string $name, string $path, array $data, ?string $group = NULL): IconDefinition;

  /**
   * Create files from sources config.
   *
   * @param array $sources
   *   The extractor config sources, path or url.
   * @param array $paths
   *   The definition paths. Include:
   *   - drupal_root
   *   - absolute_path
   *   - relative_path.
   *
   * @return array
   *   List of files with metadata.
   */
  public function getFilesFromSources(array $sources, array $paths): array;

}
