<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\ui_icons\IconDefinitionInterface;
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
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   Manage entity view mode configurations and displays.
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
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
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
    $this->entityDisplayRepository = $entityDisplayRepository;
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
      $container->get('plugin.manager.icon_pack'),
      $container->get('entity_display.repository')
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

    // Access FieldWidget settings to match this formatter settings.
    // Except in some context like views where we don't have the bundle.
    if (!$bundle = $this->fieldDefinition->getTargetBundle()) {
      $widget_settings = [
        'icon_position' => FALSE,
        'allowed_icon_pack' => [],
      ];
    }
    else {
      $field_name = $this->fieldDefinition->getName();
      $form_display = $this->entityDisplayRepository->getFormDisplay(
        $this->fieldDefinition->getTargetEntityTypeId(),
        $bundle,
        // @todo is it possible to support form display?
        'default'
      );
      $component = $form_display->getComponent($field_name);
      if (isset($component['settings'])) {
        $widget_settings = $component['settings'];
      }
      else {
        $widget_settings = [
          'icon_position' => FALSE,
          'allowed_icon_pack' => [],
        ];
      }
    }

    if (isset($widget_settings['icon_position']) && FALSE === $widget_settings['icon_position']) {
      $elements['icon_display'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon display'),
        '#options' => $this->getDisplayPositions(),
        '#default_value' => $this->getSetting('icon_display') ?? 'icon_only',
      ];
    }

    $this->pluginManagerIconPack->getExtractorPluginForms(
      $elements,
      $form_state,
      $this->getSetting('icon_settings') ?: [],
      $widget_settings['allowed_icon_pack'] ? array_filter($widget_settings['allowed_icon_pack']) : [],
      TRUE
    );

    // Placeholder to get all settings serialized as the form keys are dynamic
    // and based on icon pack definition options.
    // @todo change to #element_submit when available.
    // @see https://drupal.org/i/2820359
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

    $find_icon_settings = function ($elem) use (&$find_icon_settings) {
      if (!is_array($elem)) {
        return FALSE;
      }

      if (isset($elem['icon_settings'])) {
        return $elem;
      }

      foreach ($elem as $value) {
        $result = $find_icon_settings($value);
        if ($result !== FALSE) {
          return $result;
        }
      }

      return FALSE;
    };

    $settings = array_filter($values, function ($elem) use ($find_icon_settings) {
      return $find_icon_settings($elem) !== FALSE;
    });

    // Extract the value excluding 'icon_settings' key.
    $filtered_values = array_map(function ($elem) use ($find_icon_settings) {
      $found = $find_icon_settings($elem);
      return array_filter($found, function ($key) {
        return $key !== 'icon_settings';
      }, ARRAY_FILTER_USE_KEY);
    }, $settings);

    if (!$filtered_values) {
      return;
    }

    $filtered_values = reset($filtered_values);

    // Do not include this form values.
    unset($filtered_values['icon_display']);
    // Clean unwanted values from link formatter.
    foreach (array_keys(LinkFormatter::defaultSettings()) as $key) {
      unset($filtered_values[$key]);
    }

    // Set the value for the element in the form state to b saved.
    $form_state->setValueForElement($element, $filtered_values);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    $formatter_icon_display = $this->getSetting('icon_display');

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $icon_full_id = $item->options['icon']['target_id'] ?? NULL;
      if (NULL === $icon_full_id) {
        continue;
      }

      $icon = $this->pluginManagerIconPack->getIcon($icon_full_id);
      if (!$icon instanceof IconDefinitionInterface) {
        continue;
      }

      $pack_id = $icon->getPackId();

      $settings = [];
      $formatter_settings = $this->getSetting('icon_settings') ?? [];
      if (isset($formatter_settings[$pack_id])) {
        $settings = $formatter_settings[$pack_id];
      }

      $icon_display = $item->options['icon_display'] ?? $formatter_icon_display ?? NULL;

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
