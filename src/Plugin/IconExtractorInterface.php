<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ui_icons\IconDefinition;

/**
 * Interface for ui_icons_extractor plugins.
 */
interface IconExtractorInterface extends PluginFormInterface {

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
  public function discoverIcons(): array;

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
   * @param string $icon_id
   *   The id of the icon.
   * @param array $data
   *   The additional data of the icon.
   * @param string|null $source
   *   The source of the icon (optional).
   * @param string|null $group
   *   The group of the icon (optional).
   *
   * @return \Drupal\ui_icons\IconDefinition
   *   The Icon definition.
   */
  public static function createIcon(string $icon_id, array $data, ?string $source = NULL, ?string $group = NULL): IconDefinition;

}
