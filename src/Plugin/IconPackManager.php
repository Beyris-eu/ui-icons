<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Defines an Icon Pack plugin manager to deal with icons.
 *
 * Extension can define icon pack in an EXTENSION_NAME.ui_icons.yml file
 * contained in the extension's base directory. Each icon pack has the
 * following structure:
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     description: STRING
 *     enabled: BOOL
 *     extractor: MACHINE_NAME
 *     config:
 *       sources: ARRAY
 *     settings:
 *       FORM_KEY:
 *         KEY: VALUE
 *         ...
 *     template: STRING
 *     library: STRING
 * @endcode
 * For example:
 * @code
 * my_icon_pack:
 *   label: 'My icons'
 *   description: 'My UI Icons set to use everywhere.'
 *   extractor: svg
 *   config:
 *     sources:
 *       - icons/{icon_id}.svg
 *       - icons_grouped/{group}/{icon_id}.svg
 *   settings:
 *     width:
 *       title: 'Width'
 *       type: 'integer'
 *     height:
 *       title: 'Height'
 *       type: 'integer'
 *   template: '<img src={{ source }} title='{{ title }}' width='{{ width|default(32) }}' height='{{ height|default(32) }}'/>'
 *   library: 'my_theme/my_lib'
 * @endcode
 *
 * @see plugin_api
 */
class IconPackManager extends DefaultPluginManager implements IconPackManagerInterface {

  /**
   * Constructs the IconPackPluginManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\ui_icons\Plugin\IconExtractorPluginManager $iconPackExtractorManager
   *   The ui_icons plugin extractor service.
   * @param string $appRoot
   *   The application root.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    protected ThemeHandlerInterface $themeHandler,
    CacheBackendInterface $cacheBackend,
    protected IconExtractorPluginManager $iconPackExtractorManager,
    protected string $appRoot,
  ) {
    $this->moduleHandler = $module_handler;
    $this->factory = new ContainerFactory($this);
    $this->alterInfo('ui_icons');
    $this->setCacheBackend($cacheBackend, 'ui_icons_pack', ['ui_icons_pack_plugin']);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcons(): array {
    $definitions = $this->getDefinitions();

    // Keep loaded list in the definition to have cache.
    if (isset($definitions['_icons_loaded'])) {
      return $definitions['_icons_loaded'];
    }

    $icons = [];
    foreach ($definitions as $definition) {
      /** @var \Drupal\ui_icons\Plugin\IconExtractorPluginInterface $extractor */
      $extractor = $this->iconPackExtractorManager->createInstance($definition['extractor'], $definition);
      $icons += $extractor->getIcons();
    }

    $definitions['_icons_loaded'] = $icons;
    $this->setCachedDefinitions($definitions);

    return $icons;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(string $icon_id): ?IconDefinitionInterface {
    $icons = $this->getIcons();
    if (isset($icons[$icon_id])) {
      return $icons[$icon_id];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function listIconPackOptions(): array {
    $iconPack = $this->getCleanDefinitions();

    $options = [];
    foreach ($iconPack as $key => $set) {
      $options[$key] = $set['label'];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function listIconPackWithDescriptionOptions(): array {
    $iconPack = $this->getCleanDefinitions();

    $options = [];
    foreach ($iconPack as $key => $set) {
      $options[$key] = $set['label'];
      if (isset($set['description'])) {
        $options[$key] .= ' - ' . $set['description'];
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function listOptions(?array $allowed_icon_pack = NULL): array {
    $icons = $this->getIcons();

    if (empty($icons)) {
      return [];
    }

    $result = [];
    foreach ($icons as $icon_id => $icon) {
      if ($allowed_icon_pack) {
        if (in_array($icon->getIconPackId(), $allowed_icon_pack)) {
          $result[$icon_id] = sprintf('%s (%s)', $icon->getName(), $icon->getIconPackLabel());
        }
      }
      else {
        $result[$icon_id] = sprintf('%s (%s)', $icon->getName(), $icon->getIconPackLabel());
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractorFormDefaults(string $iconPack): array {
    $all_icon_pack = $this->getCleanDefinitions();

    if (!isset($all_icon_pack[$iconPack]) || !isset($all_icon_pack[$iconPack]['settings'])) {
      return [];
    }

    $default = [];
    foreach ($all_icon_pack[$iconPack]['settings'] as $name => $definition) {
      if (isset($definition['default'])) {
        $default[$name] = $definition['default'];
      }
    }

    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): ?array {
    $definitions = $this->getCachedDefinitions();
    if (!isset($definitions)) {
      $definitions = $this->findDefinitions();
      foreach ($definitions as $key => $definition) {
        if (isset($definition['enabled']) && $definition['enabled'] === FALSE) {
          unset($definitions[$key]);
        }
      }
      $this->setCachedDefinitions($definitions);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractorPluginForms(array &$form, FormStateInterface $form_state, array $default_settings = [], array $allowed_icon_pack = []): void {
    $iconPack = $this->getCleanDefinitions();
    if (!empty($allowed_icon_pack)) {
      $iconPack = array_intersect_key($iconPack, $allowed_icon_pack);
    }

    $extractor_forms = $this->iconPackExtractorManager->getExtractorForms($iconPack);
    if (empty($extractor_forms)) {
      return;
    }

    foreach ($iconPack as $icon_pack_id => $plugin) {
      // Simply skip if no settings declared in definition.
      if (!isset($plugin['settings']) || empty($plugin['settings'])) {
        continue;
      }

      // Create the container for each extractor settings used to have the
      // extractor form.
      $form[$icon_pack_id] = [
        '#type' => 'container',
        // Name is used for js hide/show settings.
        // @see web/modules/ui_icons/js/ui_icons.admin.js
        '#attributes' => ['name' => 'icon-settings--' . $icon_pack_id],
      ];

      // Create the extractor form and set settings so we can build with values.
      $subform_state = SubformState::createForSubform($form[$icon_pack_id], $form, $form_state);
      $subform_state->getCompleteFormState()->setValue('saved_values', $default_settings[$icon_pack_id] ?? []);
      if (is_a($extractor_forms[$icon_pack_id], '\Drupal\Core\Plugin\PluginFormInterface')) {
        $form[$icon_pack_id] += $extractor_forms[$icon_pack_id]->buildConfigurationForm($form[$icon_pack_id], $subform_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface {
    if (!$this->discovery) {
      $this->discovery = new YamlDiscovery('ui_icons', $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories());
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    if (preg_match('@[^a-z0-9_]@', $plugin_id)) {
      throw new IconPackConfigErrorException(sprintf('Invalid Icon Pack id in: %s, name: %s must contain only lowercase letters, numbers, and underscores.', $definition['provider'], $plugin_id));
    }

    // @todo replace with json validation.
    if (!isset($definition['extractor'])) {
      throw new IconPackConfigErrorException('Missing `extractor:` key in your definition!');
    }
    // @todo is it needed as an extractor plugin can exist without config key?
    if (!isset($definition['config'])) {
      throw new IconPackConfigErrorException('Missing `config:` key in your definition extractor!');
    }

    $relative_path = $this->moduleHandler->moduleExists($definition['provider'])
    ? $this->moduleHandler->getModule($definition['provider'])->getPath()
    : $this->themeHandler->getTheme($definition['provider'])->getPath();

    // Provide path information for extractor.
    $definition += [
      '_path_info' => [
        'drupal_root' => $this->appRoot,
        'absolute_path' => sprintf('%s/%s', $this->appRoot, $relative_path),
        'relative_path' => $relative_path,
      ],
      'icon_pack_id' => $definition['id'],
      'icon_pack_label' => $definition['label'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists(mixed $provider): bool {
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

  /**
   * Return definitions without cached loaded icons.
   */
  private function getCleanDefinitions(): array {
    $definitions = $this->getDefinitions();
    unset($definitions['_icons_loaded']);
    return $definitions;
  }

}
