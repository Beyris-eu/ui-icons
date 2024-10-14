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
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Defines an Icon Pack plugin manager to deal with icons.
 *
 * Extension can define icon pack in an EXTENSION_NAME.icons.yml file
 * contained in the extension's base directory. Each icon pack has the
 * following structure:
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     description: STRING
 *     license: STRING
 *     license_url: STRING
 *     links:
 *       - DOCUMENTATION
 *     license: LICENSE
 *     license_url: http://LICENSE
 *     version: 1.0.0
 *     enabled: BOOL
 *     extractor: PLUGIN_NAME
 *     config: OBJECT
 *     settings:
 *       FORM_KEY:
 *         KEY: VALUE
 *         ...
 *     template: STRING
 *     preview: STRING
 *     library: STRING
 * @endcode
 * For example:
 * @code
 * my_icon_pack:
 *   label: "My icons"
 *   description: "My UI Icons pack to use everywhere."
 *   extractor: svg
 *   config:
 *     sources:
 *       - icons/{icon_id}.svg
 *       - icons_grouped/{group}/{icon_id}.svg
 *   settings:
 *     size:
 *       title: "Size"
 *       type: "integer"
 *       default: 32
 *   template: >
 *     <img src={{ source }} width="{{ size|default(32) }}" height="{{ size|default(32) }}"/>
 *   library: "my_theme/my_lib"
 * @endcode
 *
 * @see plugin_api
 */
class IconPackManager extends DefaultPluginManager implements IconPackManagerInterface {

  private const SCHEMA_VALIDATE = 'icon_pack.schema.json';

  /**
   * The schema validator.
   *
   * This property will only be set if the validator library is available.
   *
   * @var \JsonSchema\Validator|null
   */
  private ?Validator $validator = NULL;

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
    $this->alterInfo('icon_pack');
    $this->setCacheBackend($cacheBackend, 'icon_pack', ['icon_pack_plugin']);
  }

  /**
   * Sets the validator service if available.
   *
   * @param \JsonSchema\Validator|null $validator
   *   The JSON Validator class.
   */
  public function setValidator(?Validator $validator = NULL): void {
    if ($validator) {
      $this->validator = $validator;
    }
    elseif (class_exists(Validator::class)) {
      $this->validator = new Validator();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): ?array {
    $definitions = $this->getCachedDefinitions();

    if (NULL !== $definitions) {
      return $definitions;
    }

    $definitions = $this->findDefinitions();

    $this->setCachedDefinitions($definitions);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    if (preg_match('@[^a-z0-9_]@', $plugin_id)) {
      throw new IconPackConfigErrorException(sprintf('Invalid Icon Pack id in: %s, name: %s must contain only lowercase letters, numbers, and underscores.', $definition['provider'], $plugin_id));
    }

    $this->validateDefinition($definition);

    // Do not include disabled definition with `enabled: false`.
    if (isset($definition['enabled']) && $definition['enabled'] === FALSE) {
      return;
    }

    if (!isset($definition['provider'])) {
      return;
    }

    // Provide path information for extractors.
    $relative_path = $this->moduleHandler->moduleExists($definition['provider'])
      ? $this->moduleHandler->getModule($definition['provider'])->getPath()
      : $this->themeHandler->getTheme($definition['provider'])->getPath();

    $definition['relative_path'] = $relative_path;
    // To avoid the need for appRoot in extractors.
    $definition['absolute_path'] = sprintf('%s/%s', $this->appRoot, $relative_path);

    // Load all discovered icons in the definition to get cached.
    $definition['icons'] = $this->getIconsFromDefinition($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcons(?array $allowed_icon_pack = NULL): array {
    $definitions = $this->getDefinitions();

    if (NULL === $definitions) {
      return [];
    }

    $icons = [];
    foreach ($definitions as $definition) {
      if ($allowed_icon_pack && !in_array($definition['id'], $allowed_icon_pack)) {
        continue;
      }
      $icons = array_merge($icons, $definition['icons'] ?? []);
    }

    return $icons;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(string $icon_id): ?IconDefinitionInterface {
    $icons = $this->getIcons();

    foreach ($icons as $icon) {
      if ($icon->getId() === $icon_id) {
        return $icon;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractorFormDefaults(string $pack_id): array {
    $all_icon_pack = $this->getDefinitions();

    if (!isset($all_icon_pack[$pack_id]) || !isset($all_icon_pack[$pack_id]['settings'])) {
      return [];
    }

    $default = [];
    foreach ($all_icon_pack[$pack_id]['settings'] as $name => $definition) {
      if (isset($definition['default'])) {
        $default[$name] = $definition['default'];
      }
    }

    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractorPluginForms(array &$form, FormStateInterface $form_state, array $default_settings = [], array $allowed_icon_pack = [], bool $wrap_details = FALSE): void {
    $icon_pack = $this->getDefinitions();

    if (NULL === $icon_pack) {
      return;
    }

    if (!empty($allowed_icon_pack)) {
      $icon_pack = array_intersect_key($icon_pack, $allowed_icon_pack);
    }

    $extractor_forms = $this->iconPackExtractorManager->getExtractorForms($icon_pack);
    if (empty($extractor_forms)) {
      return;
    }

    foreach ($icon_pack as $pack_id => $plugin) {
      // Simply skip if no settings declared in definition.
      if (!isset($plugin['settings']) || empty($plugin['settings'])) {
        continue;
      }

      // Create the container for each extractor settings used to have the
      // extractor form.
      $form[$pack_id] = [
        '#type' => $wrap_details ? 'details' : 'container',
        '#title' => $wrap_details ? $plugin['label'] : '',
      ];

      // Create the extractor form and set settings so we can build with values.
      $subform_state = SubformState::createForSubform($form[$pack_id], $form, $form_state);
      $subform_state->getCompleteFormState()->setValue('saved_values', $default_settings[$pack_id] ?? []);
      if (is_a($extractor_forms[$pack_id], '\Drupal\Core\Plugin\PluginFormInterface')) {
        $form[$pack_id] += $extractor_forms[$pack_id]->buildConfigurationForm($form[$pack_id], $subform_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listIconPackOptions(bool $include_description = FALSE): array {
    $definitions = $this->getDefinitions();

    if (NULL === $definitions) {
      return [];
    }

    $options = [];
    foreach ($definitions as $definition) {
      if (empty($definition['icons'])) {
        continue;
      }
      $label = $definition['label'] ?? $definition['id'];
      if ($include_description && isset($definition['description'])) {
        $label = sprintf('%s - %s', $label, $definition['description']);
      }
      $options[$definition['id']] = sprintf('%s (%u)', $label, count($definition['icons']));
    }

    natsort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface {
    if (!$this->discovery) {
      $this->discovery = new YamlDiscovery('icons', $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories());
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists(mixed $provider): bool {
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

  /**
   * Discover list of icons from definition extractor.
   *
   * @param array $definition
   *   The definition.
   *
   * @return array
   *   Discovered icons.
   */
  private function getIconsFromDefinition(array $definition): array {
    if (!isset($definition['extractor'])) {
      return [];
    }

    /** @var \Drupal\ui_icons\Plugin\IconExtractorInterface $extractor */
    $extractor = $this->iconPackExtractorManager->createInstance($definition['extractor'], $definition);
    return $extractor->discoverIcons();
  }

  /**
   * Validates a definition against the JSON schema specification.
   *
   * @param array $definition
   *   The definition to alter.
   *
   * @return bool
   *   FALSE if the response failed validation, otherwise TRUE.
   *
   * @throws \Drupal\ui_icons\Exception\IconPackConfigErrorException
   *   Thrown when the definition is not valid.
   */
  private function validateDefinition(array $definition): bool {
    // If the validator isn't set, then the validation library is not installed.
    if (!$this->validator) {
      return TRUE;
    }

    $schema_ref = sprintf(
      'file://%s/%s',
      implode('/', [
        $this->appRoot,
        $this->moduleHandler->getModule('ui_icons')->getPath(),
      ]),
      self::SCHEMA_VALIDATE
    );
    $schema = (object) ['$ref' => $schema_ref];

    $definition_object = Validator::arrayToObjectRecursive($definition);

    $this->validator->validate($definition_object, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

    if ($this->validator->isValid()) {
      return TRUE;
    }

    $message_parts = array_map(
      static function (array $error): string {
        return sprintf("[%s] %s", $error['property'], $error['message']);
      },
      $this->validator->getErrors()
    );
    $message = implode(", ", $message_parts);

    throw new IconPackConfigErrorException(
      sprintf(
        '%s:%s Error in definition `%s`:%s',
        $definition['provider'],
        $definition['id'],
        $definition_object->id,
        $message
      )
    );
  }

}
