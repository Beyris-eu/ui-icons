<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\Form\IconExtractorSettingsForm;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconFinder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for ui_icons_extractor plugins.
 */
abstract class IconExtractorPluginBase extends PluginBase implements IconExtractorPluginInterface, PluginWithFormsInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use PluginWithFormsTrait;

  /**
   * Constructs a IconExtractorPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ui_icons\IconFinder $iconFinder
   *   The icons finder service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected IconFinder $iconFinder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ui_icons.finder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if (!isset($this->configuration['settings'])) {
      return $form;
    }

    return IconExtractorSettingsForm::generateSettingsForm($this->configuration['settings'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public static function createIcon(string $name, string $path, array $data, ?string $group = NULL): IconDefinition {
    $data = self::filterDataFromDefinition($data);
    return IconDefinition::create($name, $path, $data, $group);
  }

  /**
   * Clean the icon data object values.
   *
   * @param array $definition
   *   The definition used as data.
   *
   * @return array
   *   The clean data definition.
   */
  private static function filterDataFromDefinition(array $definition): array {
    return [
      'icon_pack_id' => $definition['icon_pack_id'],
      'icon_pack_label' => $definition['icon_pack_label'] ?? '',
      'template' => $definition['template'] ?? NULL,
      'library' => $definition['library'] ?? NULL,
      'content' => $definition['content'] ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilesFromSources(array $sources, array $paths): array {
    if (empty($sources)) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: sources` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    if (!isset($paths['drupal_root']) || !isset($paths['absolute_path']) || !isset($paths['relative_path'])) {
      throw new IconPackConfigErrorException(sprintf('Could not retrieve paths for extractor %s.', $this->getPluginId()));
    }

    $files = [];
    foreach ($sources as $source) {
      $files_found = $this->iconFinder->getFilesFromSource($source, $paths['drupal_root'], $paths['absolute_path'], $paths['relative_path']);
      $files = array_merge($files, $files_found);
    }

    return $files;
  }

}
