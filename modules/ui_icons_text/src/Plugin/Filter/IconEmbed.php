<?php

declare(strict_types=1);

namespace Drupal\ui_icons_text\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\ui_icons\IconSearch;
use Drupal\ui_icons\IconSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to embed icon items using a custom tag.
 *
 * @internal
 */
#[Filter(
  id: 'icon_embed',
  title: new TranslatableMarkup('Embed icon'),
  description: new TranslatableMarkup('Embeds icon items using a custom tag, <code>&lt;drupal-icon&gt;</code>.'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 100,
  settings: [
    'allowed_icon_pack' => [],
    'wrapper' => TRUE,
    'selector_format' => 'icon_autocomplete',
    'result_format' => 'list',
    // Default autocomplete result length. Multiple of 12 to match grid format.
    'max_result' => 24,
  ],
)]
class IconEmbed extends FilterBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * The ui icons service.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected $pluginManagerIconPack;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The module handler, to offer the selectors of enabled submodules.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a IconEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface $plugin_manager_icon_pack
   *   The icon manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IconPackManagerInterface $plugin_manager_icon_pack,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManagerIconPack = $plugin_manager_icon_pack;
    $this->renderer = $renderer;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.icon_pack'),
      $container->get('renderer'),
      $container->get('logger.factory'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['allowed_icon_pack'] = [
      '#title' => $this->t('Icon Pack selectable'),
      '#type' => 'checkboxes',
      '#options' => $this->pluginManagerIconPack->listIconPackOptions(TRUE),
      '#default_value' => $this->settings['allowed_icon_pack'],
      '#description' => $this->t('If none are selected, all will be allowed.'),
      '#element_validate' => [[static::class, 'validateOptions']],
    ];

    $form['wrapper'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap icons in a span'),
      '#description' => $this->t('Without it, the classes and attributes of an icon go on the icon itself, when its pack renders a single element.'),
      '#default_value' => $this->settings['wrapper'] ?? TRUE,
    ];

    $form['selector_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Selector format'),
      '#options' => $this->getSelectorFormat(),
      '#default_value' => $this->settings['selector_format'] ?? 'icon_autocomplete',
    ];

    $form['result_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Result format'),
      '#options' => $this->getAutocompleteFormat(),
      '#default_value' => $this->settings['result_format'] ?? 'list',
      '#states' => [
        'visible' => [
          ':input[name="filters[icon_embed][settings][selector_format]"]' => ['value' => 'icon_autocomplete'],
        ],
      ],
    ];

    $form['max_result'] = [
      '#type' => 'number',
      '#min' => 2,
      '#max' => IconSearch::SEARCH_RESULT_MAX,
      '#title' => $this->t('Maximum results'),
      '#default_value' => $this->settings['max_result'] ?? IconSearch::SEARCH_RESULT,
      '#states' => [
        'visible' => [
          ':input[name="filters[icon_embed][settings][selector_format]"]' => ['value' => 'icon_autocomplete'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Form element validation handler.
   *
   * @param array $element
   *   The allowed_view_modes form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateOptions(array &$element, FormStateInterface $form_state): void {
    // Filters the #value property so only selected values appear in the
    // config.
    $form_state->setValueForElement($element, array_filter($element['#value']));
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<drupal-icon') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    $query = $xpath->query('//drupal-icon[normalize-space(@data-icon-id)!=""]');

    if (FALSE === $query || empty($query->count())) {
      return $result;
    }

    $allowed_icon_pack = array_filter($this->settings['allowed_icon_pack'] ?? []);
    foreach ($query as $node) {
      /** @var \DOMElement $node */
      $icon_full_id = $node->getAttribute('data-icon-id');
      // The editor source mode lets any icon id through the selector limit.
      $pack_id = IconDefinition::getIconDataFromId($icon_full_id)['pack_id'] ?? NULL;
      if ($allowed_icon_pack && !in_array($pack_id, $allowed_icon_pack, TRUE)) {
        $node->parentNode->removeChild($node);
        continue;
      }
      $settings = $this->extractSettings($node);
      $attributes = $this->extractAttributes($node);
      $build = ($this->settings['wrapper'] ?? TRUE)
        ? $this->getWrappedRenderable($icon_full_id, $settings, $attributes)
        : $this->getUnwrappedRenderable($icon_full_id, $settings, $attributes);
      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

  /**
   * Reads the icon settings off a `<drupal-icon>` node.
   *
   * Because of the CKEditor attributes system, settings travel as a single
   * serialized attribute.
   *
   * @param \DOMElement $node
   *   The `<drupal-icon>` node.
   *
   * @return array
   *   The scalar settings declared by the icon pack, empty when absent or not
   *   valid JSON.
   */
  private function extractSettings(\DOMElement $node): array {
    $data_settings = $node->getAttribute('data-icon-settings');
    if (!$data_settings || !json_validate($data_settings)) {
      return [];
    }

    return IconSettings::filter($node->getAttribute('data-icon-id'), json_decode($data_settings, TRUE));
  }

  /**
   * Reads the passthrough HTML attributes off a `<drupal-icon>` node.
   *
   * @param \DOMElement $node
   *   The `<drupal-icon>` node.
   *
   * @return array
   *   Attributes to apply to the rendered icon.
   */
  private function extractAttributes(\DOMElement $node): array {
    $attributes = [];

    if ($class = $node->getAttribute('class')) {
      $attributes['class'] = explode(' ', $class);
    }
    if ($aria_label = $node->getAttribute('aria-label')) {
      $attributes['aria-label'] = $aria_label;
    }
    // A bare aria-hidden is undefined in ARIA, so only the two valid values
    // are copied, as strings.
    $aria_hidden = $node->getAttribute('aria-hidden');
    if (in_array($aria_hidden, ['true', 'false'], TRUE)) {
      $attributes['aria-hidden'] = $aria_hidden;
    }
    if ($role = $node->getAttribute('role')) {
      $attributes['role'] = $role;
      // The element with role="presentation" is not part of the accessibility
      // tree and should not have an accessible name.
      // @see https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/presentation_role
      if (in_array($role, ['presentation', 'none'])) {
        unset($attributes['aria-label']);
      }
    }

    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return (string) $this->t('
      <p>You can embed icon:</p>
      <ul>
        <li>Choose which icon item to embed: <code>&lt;drupal-icon data-icon-id="pack_id:icon_id" /&gt;</code></li>
        <li>Optionally also pass settings with data-icon-settings: <code>data-icon-settings="{\'width\':100}"</code>, otherwise the default settings from the Icon Pack definition are used.</li>
      </ul>');
    }
    else {
      return (string) $this->t('You can embed icon items (using the <code>&lt;drupal-icon&gt;</code> tag).');
    }
  }

  /**
   * Wrap icon renderable in a specific class.
   *
   * @param string $icon_full_id
   *   The icon full id to render.
   * @param array $settings
   *   Settings to pass as context to the rendered icon.
   * @param array $attributes
   *   Extra attributes to pass to the wrapper.
   *
   * @return array
   *   Renderable array.
   *
   * @todo wrapping, class and library as filter settings?
   */
  protected function getWrappedRenderable(string $icon_full_id, array $settings, array $attributes): array {
    $attributes['class'][] = 'drupal-icon';
    $attributes = new Attribute($attributes);

    $build = IconDefinition::getRenderable($icon_full_id, $settings);

    $build['#prefix'] = '<span' . $attributes . '>';
    $build['#suffix'] = '</span>';
    $build['#attached']['library'][] = 'ui_icons_text/icon.content';

    return $build;
  }

  /**
   * Gets the icon renderable without the wrapper.
   *
   * @param string $icon_full_id
   *   The icon full ID.
   * @param array $settings
   *   The icon settings.
   * @param array $attributes
   *   The attributes of the icon tag.
   *
   * @return array
   *   The icon renderable, which takes the attributes once rendered.
   */
  protected function getUnwrappedRenderable(string $icon_full_id, array $settings, array $attributes): array {
    $attributes['class'][] = 'drupal-icon';

    $build = IconDefinition::getRenderable($icon_full_id, $settings);
    $build['#icon_embed_attributes'] = $attributes;
    $build['#post_render'][] = [static::class, 'moveAttributesToIcon'];
    $build['#attached']['library'][] = 'ui_icons_text/icon.content';

    return $build;
  }

  /**
   * Post render callback putting the tag attributes on the icon element.
   *
   * Only an icon rendered as a single element can take them. Otherwise, as
   * for a text before the element or an unknown icon, the wrapper is back.
   *
   * @param string $markup
   *   The rendered icon.
   * @param array $element
   *   The icon renderable.
   *
   * @return string
   *   The icon with the attributes.
   */
  public static function moveAttributesToIcon(string $markup, array $element): string {
    $attributes = $element['#icon_embed_attributes'];
    $dom = Html::load($markup);
    $nodes = array_filter(
      iterator_to_array($dom->getElementsByTagName('body')->item(0)->childNodes),
      // Theme debug comments and indentation are not part of the icon.
      static fn (\DOMNode $node): bool => !$node instanceof \DOMComment && !($node instanceof \DOMText && trim($node->textContent) === ''),
    );
    $icon = count($nodes) === 1 ? reset($nodes) : NULL;
    if (!$icon instanceof \DOMElement) {
      return '<span' . new Attribute($attributes) . '>' . $markup . '</span>';
    }

    foreach ($attributes as $name => $value) {
      if ('class' === $name) {
        $value = implode(' ', array_unique(array_merge(array_filter(explode(' ', $icon->getAttribute('class'))), $value)));
      }
      $icon->setAttribute($name, TRUE === $value ? 'true' : (string) $value);
    }

    return Html::serialize($dom);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['moveAttributesToIcon'];
  }

  /**
   * Renders the given render array into the given DOM node.
   *
   * @todo this is a copy from Media core, not sure we really need it.
   *
   * @param array $build
   *   The render array to render in isolation.
   * @param \DOMNode $node
   *   The DOM node to render into.
   * @param \Drupal\filter\FilterProcessResult $result
   *   The accumulated result of filter processing, updated with the metadata
   *   bubbled during rendering.
   */
  protected function renderIntoDomNode(array $build, \DOMNode $node, FilterProcessResult &$result): void {
    // We need to render the embedded entity:
    // - without replacing placeholders, so that the placeholders are
    //   only replaced at the last possible moment. Hence we cannot use
    //   either renderInIsolation() or renderRoot(), so we must use render().
    // - without bubbling beyond this filter, because filters must
    //   ensure that the bubbleable metadata for the changes they make
    //   when filtering text makes it onto the FilterProcessResult
    //   object that they return ($result). To prevent that bubbling, we
    //   must wrap the call to render() in a render context.
    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });

    // Empty rendered mean icon id is probably invalid or removed.
    if ('<span class="drupal-icon"></span>' === (string) $markup) {
      $param = ['@pack_id' => $build['#pack_id'], '@icon_id' => $build['#icon_id']];
      $this->loggerFactory->get('ui_icons')->error('Failed rendering of icon ID "@pack_id:@icon_id", does not exist or has been deleted.', $param);
    }

    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, $markup);
  }

  /**
   * Replaces the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected static function replaceNodeContent(\DOMNode &$node, $content): void {
    if (strlen((string) $content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0);
      if (NULL === $nodes) {
        return;
      }
      $replacement_nodes = $nodes->childNodes;
    }
    else {
      $node_document = $node->ownerDocument;
      if (NULL === $node_document) {
        return;
      }
      $replacement_nodes = [$node_document->createTextNode('')];
    }

    $node_document = $node->ownerDocument;
    if (NULL === $node_document) {
      return;
    }
    $node_parent = $node->parentNode;
    if (NULL === $node_parent) {
      return;
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node_document->importNode($replacement_node, TRUE);
      $node_parent->insertBefore($replacement_node, $node);
    }
    $node_parent->removeChild($node);
  }

  /**
   * Gets icon selector formats.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Icon selector format options.
   */
  private function getSelectorFormat(): array {
    $options = [
      'icon_autocomplete' => $this->t('Autocomplete'),
      'icon_picker' => $this->t('Picker'),
    ];
    if ($this->moduleHandler->moduleExists('ui_icons_popover')) {
      $options['icon_popover'] = $this->t('Popover');
    }

    return $options;
  }

  /**
   * Gets icon autocomplete result formats.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Icon result format options.
   */
  private function getAutocompleteFormat(): array {
    return [
      'list' => $this->t('List'),
      'grid' => $this->t('Grid'),
    ];
  }

}
