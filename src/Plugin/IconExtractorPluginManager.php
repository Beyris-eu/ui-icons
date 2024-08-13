<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\ui_icons\Attribute\IconExtractor;

/**
 * IconExtractor plugin manager.
 */
class IconExtractorPluginManager extends DefaultPluginManager {

  /**
   * The plugin form manager.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, PluginFormFactoryInterface $plugin_form_manager) {
    parent::__construct('Plugin/IconExtractor', $namespaces, $module_handler, IconExtractorPluginInterface::class, IconExtractor::class);
    $this->alterInfo('ui_icons_extractor_info');
    $this->setCacheBackend($cache_backend, 'ui_icons_extractor_plugins');
    $this->pluginFormFactory = $plugin_form_manager;
  }

  /**
   * Get multiple extractor settings form.
   *
   * @param array $iconPack_configurations
   *   All the icon pack configurations containing the extractor.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface[]
   *   The extractor form indexed by extractor id.
   */
  public function getExtractorForms(array $iconPack_configurations): array {
    $extractor_forms = [];
    foreach ($iconPack_configurations as $iconPack_configuration) {
      $icon_pack_id = $iconPack_configuration['id'];
      $extractor_forms[$icon_pack_id] = $this->getExtractorForm($iconPack_configuration);
    }

    return $extractor_forms;
  }

  /**
   * Get an extractor settings form.
   *
   * @param array $extractor_configuration
   *   The extractor configuration.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface|null
   *   The extractor form or null.
   */
  public function getExtractorForm(array $extractor_configuration): ?PluginFormInterface {
    if (!isset($extractor_configuration['settings'])) {
      return NULL;
    }
    /** @var \Drupal\ui_icons\Plugin\IconExtractorPluginInterface $plugin */
    $plugin = $this->createInstance($extractor_configuration['extractor'], $extractor_configuration);
    return $this->getPluginForm($plugin);
  }

  /**
   * Retrieves the plugin form for a given icon extractor.
   *
   * @param \Drupal\ui_icons\Plugin\IconExtractorPluginInterface $icon_extractor
   *   The ui icons extractor plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   The plugin form for this plugin.
   */
  protected function getPluginForm(IconExtractorPluginInterface $icon_extractor): PluginFormInterface {
    if ($icon_extractor instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($icon_extractor, 'settings');
    }
    return $icon_extractor;
  }

}
