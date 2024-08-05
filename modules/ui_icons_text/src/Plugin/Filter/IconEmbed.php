<?php

namespace Drupal\ui_icons_text\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\UiIconsetManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to embed icon items using a custom tag.
 *
 * @internal
 */
#[Filter(
  id: "icon_embed",
  title: new TranslatableMarkup("Embed icon"),
  description: new TranslatableMarkup("Embeds icon items using a custom tag, <code>&lt;drupal-icon&gt;</code>."),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 100,
  settings: [
    "allowed_iconset" => [],
  ],
)]
class IconEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The ui icons service.
   *
   * @var \Drupal\ui_icons\Plugin\UiIconsetManagerInterface
   */
  protected $pluginManagerUiIconset;

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
   * An array of counters for the recursive rendering protection.
   *
   * Each counter takes into account all the relevant information about the
   * field and the referenced entity that is being rendered.
   *
   * @var array
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter::$recursiveRenderDepth
   */
  protected static $recursiveRenderDepth = [];

  /**
   * Constructs a IconEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ui_icons\Plugin\UiIconsetManagerInterface $ui_iconset
   *   The icon manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UiIconsetManagerInterface $ui_iconset, RendererInterface $renderer, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManagerUiIconset = $ui_iconset;
    $this->renderer = $renderer;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ui_iconset'),
      $container->get('renderer'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $iconset = $this->pluginManagerUiIconset->listIconsetWithDescriptionOptions();

    $form['allowed_iconset'] = [
      '#title' => $this->t('Iconset selectable'),
      '#type' => 'checkboxes',
      '#options' => $iconset,
      '#default_value' => $this->settings['allowed_iconset'],
      '#description' => $this->t('If none are selected, all will be allowed.'),
      '#element_validate' => [[static::class, 'validateOptions']],
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

    foreach ($xpath->query('//drupal-icon[normalize-space(@data-icon-id)!=""]') as $node) {
      /** @var \DOMElement $node */
      $icon_id = $node->getAttribute('data-icon-id');
      // Delete the consumed attributes.
      $node->removeAttribute('data-icon-id');

      // Because of Ckeditor attributes system, we use a single attribute with
      // serialized options.
      $options = [];
      /** @var \DOMElement $node */
      $data_options = $node->getAttribute('data-icon-options');
      if ($data_options && json_validate($data_options)) {
        $options = json_decode($data_options, TRUE);
      }

      $icon = $this->pluginManagerUiIconset->getIcon($icon_id);
      assert($icon === NULL || $icon instanceof IconDefinitionInterface);

      // Use default options if none set.
      if (empty($options)) {
        [$iconset_id] = explode(':', $icon_id);
        $options = $this->pluginManagerUiIconset->getExtractorFormDefaults($iconset_id);
      }

      if (!$icon) {
        $this->loggerFactory->get('ui_icons')->error('During rendering of embedded icon: the icon item with ID "@id" does not exist.', ['@id' => $icon_id]);
      }

      $build = $icon
        ? $this->getWrappedRenderable($icon, $options)
        : $this->renderMissingIconIndicator($icon_id);

      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
      <p>You can embed icon:</p>
      <ul>
        <li>Choose which icon item to embed: <code>&lt;drupal-icon data-icon-id="iconset:icon_id" /&gt;</code></li>
        <li>Optionally also pass options with data-options: <code>data-options="{\'width\':100}"</code>, otherwise the default options from the Iconset definition are used.</li>
      </ul>');
    }
    else {
      return $this->t('You can embed icon items (using the <code>&lt;drupal-icon&gt;</code> tag).');
    }
  }

  /**
   * Wrap icon renderable in a specific class.
   *
   * @param \Drupal\ui_icons\IconDefinitionInterface $icon
   *   The icon to render.
   * @param array $options
   *   Options to pass as context to the rendered icon.
   *
   * @return array
   *   Renderable array.
   *
   * @todo wrapping, class and library as filter settings?
   */
  protected function getWrappedRenderable(IconDefinitionInterface $icon, array $options): array {
    $build = $icon->getRenderable($options);
    $build['#prefix'] = '<span class="drupal-icon">';
    $build['#suffix'] = '</span>';
    $build['#attached']['library'][] = 'ui_icons_text/icon.content';

    return $build;
  }

  /**
   * Renders the given render array into the given DOM node.
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
    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, $markup);
  }

  /**
   * Builds the render array for the indicator when icon cannot be loaded.
   *
   * @param string $icon_id
   *   The icon id failing.
   *
   * @return array
   *   A render array.
   */
  protected function renderMissingIconIndicator(string $icon_id): array {
    $title = $this->t('The referenced icon: @name is missing and needs to be re-embedded.', ['@name' => $icon_id]);
    $icon = '<svg width="15" height="14" fill="none" xmlns="http://www.w3.org/2000/svg"><title>' . $title . '</title><path d="M7.002 0a7 7 0 100 14 7 7 0 000-14zm3 5c0 .551-.16 1.085-.477 1.586l-.158.22c-.07.093-.189.241-.361.393a9.67 9.67 0 01-.545.447l-.203.189-.141.129-.096.17L8 8.369v.63H5.999v-.704c.026-.396.078-.73.204-.999a2.83 2.83 0 01.439-.688l.225-.21-.01-.015.176-.14.137-.128c.186-.139.357-.277.516-.417l.148-.18A.948.948 0 008.002 5 1.001 1.001 0 006 5H4a3 3 0 016.002 0zm-1.75 6.619a.627.627 0 01-.625.625h-1.25a.627.627 0 01-.626-.625v-1.238c0-.344.281-.625.626-.625h1.25c.344 0 .625.281.625.625v1.238z" fill="#d72222"/></svg>';
    return [
      '#type' => 'inline_template',
      '#template' => '<span class="drupal-icon">{{ icon | raw }}<span>',
      '#context' => ['icon' => $icon],
      '#attached' => ['library' => ['ui_icons_text/icon.content']],
    ];
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
    if (strlen($content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = [];
    // @todo ensure iconset definition is still available?
    return $dependencies;
  }

}
