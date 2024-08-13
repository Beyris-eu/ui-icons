<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Interface for UI Iconset manager.
 */
interface UiIconsetManagerInterface extends PluginManagerInterface {

  /**
   * Get a list of all the icons available for this iconset.
   *
   * The icons provided as an associative array with the keys and values equal
   * to the icon ID and icon definition respectively.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface[]
   *   Gets a built list of icons that are in this iconset. Array is keyed by
   *   the icon ID and the array values are the icon definition for each of
   *   the icons listed.
   */
  public function getIcons(): array;

  /**
   * Get definition of a specific icon.
   *
   * @param string $icon_id
   *   The ID of the icon to retrieve definition of.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface|null
   *   Icon definition.
   */
  public function getIcon(string $icon_id): ?IconDefinitionInterface;

  /**
   * Populates a key-value pair of available icons.
   *
   * @param array|null $allowed_iconset
   *   Include only icons of these iconset.
   *
   * @return array
   *   An array of translated icons labels, keyed by ID.
   */
  public function listOptions(?array $allowed_iconset = NULL): array;

  /**
   * Populates a key-value pair of available iconset.
   *
   * @return array
   *   An array of translated iconset labels, keyed by ID.
   */
  public function listIconsetOptions(): array;

  /**
   * Populates a key-value pair of available iconset with description.
   *
   * @return array
   *   An array of translated iconset labels and description, keyed by ID.
   */
  public function listIconsetWithDescriptionOptions(): array;

  /**
   * Retrieve extractor forms based on the provided icon set limit.
   *
   * @param array $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $default_settings
   *   The settings for the forms (optional).
   * @param array $allowed_iconset
   *   The list of icon set (optional).
   */
  public function getExtractorPluginForms(array &$form, FormStateInterface $form_state, array $default_settings = [], array $allowed_iconset = []): void;

  /**
   * Retrieve extractor default options.
   *
   * @param string $iconset
   *   The iconset to look for.
   *
   * @return array
   *   The extractor defaults options.
   */
  public function getExtractorFormDefaults(string $iconset): array;

  /**
   * Performs extra processing on plugin definitions.
   *
   * By default we add defaults for the type to the definition. If a type has
   * additional processing logic they can do that by replacing or extending the
   * method.
   *
   * @param array $definition
   *   The definition to alter.
   * @param string $plugin_id
   *   The plugin id.
   */
  public function processDefinition(array &$definition, string $plugin_id): void;

}
