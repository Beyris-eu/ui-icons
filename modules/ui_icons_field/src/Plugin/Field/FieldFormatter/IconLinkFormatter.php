<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field formatter for displaying icon in link field content.
 */
#[FieldFormatter(
  id: 'icon_link_formatter',
  label: new TranslatableMarkup('Link icon'),
  field_types: [
    'link',
  ],
)]
class IconLinkFormatter extends LinkFormatter {

  /**
   * Constructs a new IconLinkFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator service.
   * @param \Drupal\ui_icons\Plugin\IconPackManagerInterface $pluginManagerIconPack
   *   The ui icons pack manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected PathValidatorInterface $path_validator,
    protected IconPackManagerInterface $pluginManagerIconPack,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $path_validator
    );
    $this->pluginManagerIconPack = $pluginManagerIconPack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('path.validator'),
      $container->get('plugin.manager.ui_icons_pack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'icon_settings' => [],
      'icon_display' => 'icon_only',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();

    if ($settings['icon_settings']) {
      $summary[] = $this->t('Specific settings saved');
    }

    if (!empty($settings['icon_display'])) {
      $summary[] = $this->t('Icon display: %position', ['%position' => $this->getDisplayPositions()[$settings['icon_display']]]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['icon_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon display'),
      '#description' => $this->t('If the form display allow user to set his own display, this will be ignored.'),
      '#options' => $this->getDisplayPositions(),
      '#default_value' => $this->getSetting('icon_display') ?? 'icon_only',
    ];

    $elements['icon_pack_notice'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('If the form display allow user to set his own settings, these values will be ignored.'),
      '#attributes' => ['class' => ['description']],
    ];

    $icon_settings = $this->getSetting('icon_settings') ?? [];

    $this->pluginManagerIconPack->getExtractorPluginForms($elements, $form_state, $icon_settings);

    // Placeholder to get all settings serialized as the form keys are dynamic
    // and based on icon pack definition options.
    // @todo change to #element_submit when available in
    // https://drupal.org/i/2820359.
    $elements['icon_settings'] = [
      '#type' => 'hidden',
      '#element_validate' => [
        [$this, 'validateSettings'],
      ],
    ];

    return $elements;
  }

  /**
   * Get the icon rendering position options available to the link formatter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of options for position options.
   */
  public function getDisplayPositions(): array {
    return [
      'before' => $this->t('Before'),
      'after' => $this->t('After'),
      'icon_only' => $this->t('Icon only'),
    ];
  }

  /**
   * Validation callback for extractor settings element.
   *
   * All extractor settings form values are serialized in a single declared
   * icon_settings form key.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateSettings(array $element, FormStateInterface $form_state, &$complete_form): void {
    $values = $form_state->getValues();
    $name = $this->fieldDefinition->getName();

    $settings = $values['fields'][$name]['settings_edit_form']['settings'] ?? [];
    // Clean unwanted values from link.
    foreach (['icon_settings', 'trim_length', 'icon_display', 'url_only', 'url_plain', 'rel', 'target'] as $key) {
      unset($settings[$key]);
    }

    // @todo do we need configuration validation of plugin form?
    $form_state->setValueForElement($element, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    $icon_display_formatter = $this->getSetting('icon_display');

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $icon = $item->options['value']['icon'] ?? NULL;

      if (NULL === $icon) {
        continue;
      }

      $settings = $item->options['value']['settings'] ?? [];
      $icon_pack_id = $icon->getIconPackId();

      // Priority is to look for widget settings, then formatter, then defaults
      // from definition.
      if (!empty($settings) && isset($settings[$icon_pack_id]) && !empty($settings[$icon_pack_id])) {
        $settings = $settings[$icon_pack_id];
      }
      else {
        $formatter_settings = $this->getSetting('icon_settings') ?? [];
        if (isset($formatter_settings[$icon_pack_id]) && !empty($formatter_settings[$icon_pack_id])) {
          $settings = $formatter_settings[$icon_pack_id];
        }
        else {
          // If the settings form has never been saved, we need to get extractor
          // default values if set.
          // @todo move to getRenderable()?
          $settings = $this->pluginManagerIconPack->getExtractorFormDefaults($icon_pack_id);
        }
      }

      $icon_display = $item->options['icon_display'] ?? $icon_display_formatter ?? NULL;

      switch ($icon_display) {
        case 'before':
          $elements[$delta] = [
            'icon' => $icon->getRenderable($settings),
            'link' => $elements[$delta],
          ];
          break;

        case 'after':
          $elements[$delta] = [
            'link' => $elements[$delta],
            'icon' => $icon->getRenderable($settings),
          ];
          break;

        default:
          $elements[$delta]['#title'] = $icon->getRenderable($settings);
          break;
      }
    }

    return $elements;
  }

}
