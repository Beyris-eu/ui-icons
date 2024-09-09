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
 *   description: 'My UI Icons pack to use everywhere.'
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
    $this->alterInfo('ui_icons');
    $this->setCacheBackend($cacheBackend, 'ui_icons_pack', ['ui_icons_pack_plugin']);
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
  public function getIcons(): array {
    $definitions = $this->getDefinitions();

    $icons = [];
    foreach ($definitions as $definition) {
      if (!isset($definition['extractor'])) {
        continue;
      }

      if (!isset($definition['_icons']['list'])) {
        $icons += $this->getIconsFromDefinition($definition);
        continue;
      }
      $icons += $definition['_icons']['list'];
    }

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
    $icon_pack_definition = $this->getDefinitions();

    $options = [];
    foreach ($icon_pack_definition as $icon_pack_id => $definition) {
      if (!isset($definition['_icons']['count'][$definition['extractor']])) {
        continue;
      }
      $count = $definition['_icons']['count'][$definition['extractor']];
      if (0 === $count) {
        continue;
      }
      $options[$icon_pack_id] = sprintf('%s (%u)', $definition['label'] ?? $definition['id'], $count);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function listIconPackWithDescriptionOptions(): array {
    $icon_pack_definition = $this->getDefinitions();

    $options = [];
    foreach ($icon_pack_definition as $icon_pack_id => $definition) {
      if (!isset($definition['_icons']['count'][$definition['extractor']])) {
        continue;
      }
      $count = $definition['_icons']['count'][$definition['extractor']];
      if (0 === $count) {
        continue;
      }
      $description = '';
      if (isset($definition['description'])) {
        $description = ' - ' . $definition['description'];
      }
      $options[$icon_pack_id] = sprintf('%s (%u)%s', $definition['label'] ?? $definition['id'], $count, $description);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function listIconOptions(?array $allowed_icon_pack = NULL): array {
    $icons = $this->getIcons();

    if (empty($icons)) {
      return [];
    }

    $result = [];
    foreach ($icons as $icon_full_id => $icon) {
      if ($allowed_icon_pack) {
        if (in_array($icon->getIconPackId(), $allowed_icon_pack)) {
          $result[$icon_full_id] = $icon->getLabel();
        }
      }
      else {
        $result[$icon_full_id] = $icon->getLabel();
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractorFormDefaults(string $icon_pack_id): array {
    $all_icon_pack = $this->getDefinitions();

    if (!isset($all_icon_pack[$icon_pack_id]) || !isset($all_icon_pack[$icon_pack_id]['settings'])) {
      return [];
    }

    $default = [];
    foreach ($all_icon_pack[$icon_pack_id]['settings'] as $name => $definition) {
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
          continue;
        }
        $icons = $this->getIconsFromDefinition($definition);
        $count_icons = count($icons);
        $definitions[$key]['_icons'] = [
          'list' => $icons,
          'count' => [],
        ];
        $definitions[$key]['_icons']['count'][$definition['extractor']] = $count_icons;
      }

      $this->setCachedDefinitions($definitions);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractorPluginForms(array &$form, FormStateInterface $form_state, array $default_settings = [], array $allowed_icon_pack = [], bool $wrap_details = FALSE): void {
    $icon_pack = $this->getDefinitions();

    if (!empty($allowed_icon_pack)) {
      $icon_pack = array_intersect_key($icon_pack, $allowed_icon_pack);
    }

    $extractor_forms = $this->iconPackExtractorManager->getExtractorForms($icon_pack);
    if (empty($extractor_forms)) {
      return;
    }

    foreach ($icon_pack as $icon_pack_id => $plugin) {
      // Simply skip if no settings declared in definition.
      if (!isset($plugin['settings']) || empty($plugin['settings'])) {
        continue;
      }

      // Create the container for each extractor settings used to have the
      // extractor form.
      $form[$icon_pack_id] = [
        '#type' => $wrap_details ? 'details' : 'container',
        '#title' => $wrap_details ? $plugin['label'] : '',
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
  public function processDefinition(&$definition, $plugin_id) {
    if (preg_match('@[^a-z0-9_]@', $plugin_id)) {
      throw new IconPackConfigErrorException(sprintf('Invalid Icon Pack id in: %s, name: %s must contain only lowercase letters, numbers, and underscores.', $definition['provider'], $plugin_id));
    }

    $this->validateDefinition($definition);

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
    $generic_schema = (object) ['$ref' => $schema_ref];

    return $this->validateSchema($generic_schema, $definition);
  }

  /**
   * Validates a string against a JSON Schema. It logs any possible errors.
   *
   * @param object $schema
   *   The JSON Schema object.
   * @param array $definition
   *   The definition to validate.
   *
   * @return bool
   *   TRUE if the definition is a valid instance of the schema. FALSE
   *   otherwise.
   *
   * @throws \Drupal\ui_icons\Exception\IconPackConfigErrorException
   *   Thrown when the definition is not valid.
   */
  private function validateSchema(object $schema, array $definition): bool {
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
    $message = implode("\n", $message_parts);

    throw new IconPackConfigErrorException(
      sprintf(
        'Error in definition `%s`:%s',
        $definition_object->id,
        $message
      )
    );
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

    $extractor = $this->iconPackExtractorManager->createInstance($definition['extractor'], $definition);
    return $extractor->discoverIcons();
  }

}
