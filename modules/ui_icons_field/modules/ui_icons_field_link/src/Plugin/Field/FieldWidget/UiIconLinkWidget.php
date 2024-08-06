<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field_link\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\UiIconsetManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'link field' widget with ui icons support.
 */
#[FieldWidget(
  id: 'ui_icon_link_widget',
  label: new TranslatableMarkup('Link UI icon'),
  field_types: ['link'],
)]
class UiIconLinkWidget extends LinkWidget implements ContainerFactoryPluginInterface {

  /**
   * Constructs an UiIconLinkWidget instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The Plugin settings.
   * @param array $third_party_settings
   *   The Plugin third party settings.
   * @param \Drupal\ui_icons\Plugin\UiIconsetManagerInterface $pluginManagerUiIconset
   *   The ui icons manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected UiIconsetManagerInterface $pluginManagerUiIconset,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
    );
    $this->pluginManagerUiIconset = $pluginManagerUiIconset;
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
      $configuration['third_party_settings'],
      $container->get('plugin.manager.ui_iconset'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'allowed_iconset' => [],
      'icon_required' => TRUE,
      'icon_position' => FALSE,
      'show_settings' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['allowed_iconset'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Limit Icon set'),
      '#description' => $this->t('Select Icons set to make available. If no selection, all will be made available.'),
      '#options' => $this->pluginManagerUiIconset->listIconsetWithDescriptionOptions(),
      '#default_value' => $this->getSetting('allowed_iconset'),
      '#multiple' => TRUE,
    ];

    $elements['icon_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon required'),
      '#description' => $this->t('Set the icon selection mandatory, will be applied only if the link itself is required.'),
      '#default_value' => (bool) $this->getSetting('icon_required'),
    ];

    $elements['icon_position'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow icon display position selection'),
      '#description' => $this->t('If selected, a "position" select will be made available. Default is from the display of this field.'),
      '#default_value' => (bool) $this->getSetting('icon_position'),
    ];

    $elements['show_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow icon settings form'),
      '#description' => $this->t('If selected, all Icons set settings will be made available for all enabled Icon set above. Note that display setting on these field will be ignored.'),
      '#default_value' => $this->getSetting('show_settings'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $settings = $this->getSettings();

    $allowed_iconset = array_filter($settings['allowed_iconset']);

    if (!empty($allowed_iconset)) {
      $labels = $this->pluginManagerUiIconset->listIconsetOptions();
      $list = array_intersect_key($labels, $allowed_iconset);
      $summary[] = $this->t('With Icon set: @set', ['@set' => implode(', ', $list)]);
    }
    else {
      $summary[] = $this->t('All icon sets available for selection');
    }

    if (TRUE === (bool) $settings['icon_required']) {
      $summary[] = $this->t('Icon is required');
    }
    else {
      $summary[] = $this->t('Icon is not required');
    }

    if ((bool) $settings['icon_position']) {
      $summary[] = $this->t('Can set icon display');
    }

    if (!empty($settings['show_settings'])) {
      $summary[] = $this->t('Icon settings enabled');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $settings = $this->getSettings();

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];

    $icon_id = NULL;
    $options = $item->get('options')->getValue() ?? [];
    if (isset($options['value']['icon']) && $options['value']['icon'] instanceof IconDefinitionInterface) {
      $icon = $options['value']['icon'];
      $icon_id = $icon->getId();
    }

    $icon_display = $options['icon_display'] ?? 'icon_only';
    $allowed_iconset = array_filter($this->getSetting('allowed_iconset') ?? []);
    $label = $this->fieldDefinition->getLabel() ?? $this->t('Link');
    $field_name = $this->fieldDefinition->getName();

    $element['value'] = [
      '#type' => 'ui_icon_autocomplete',
      '#title' => $this->t('@name icon', ['@name' => $label]),
      '#description' => $this->t('Pick an Icon for this link.'),
      '#default_value' => $icon_id,
      '#allowed_iconset' => $allowed_iconset,
      '#show_settings' => $settings['show_settings'],
      '#required' => $element['#required'] ? $settings['icon_required'] : FALSE,
      // Put the parent to allow saving under `options`.
      '#parents' => array_merge($element['#field_parents'], [
        $field_name,
        $delta,
        'options',
        'value',
      ]),
    ];

    if ($icon_id) {
      $element['value']['#default_value'] = $icon_id;
      if (TRUE == $settings['show_settings'] && isset($options['value']['settings'])) {
        $element['value']['#default_settings'] = $options['value']['settings'];
      }
    }

    if (TRUE == $settings['icon_position']) {
      $element['icon_display'] = [
        '#type' => 'select',
        '#title' => $this->t('@name icon display', ['@name' => $label]),
        '#description' => $this->t('Choose display for this icon link.'),
        '#default_value' => $icon_display,
        '#options' => $this->getDisplayPositions(),
        '#states' => [
          'visible' => [
            ':input[name="' . $field_name . '[' . $delta . '][options][icon]"]' => ['empty' => FALSE],
          ],
        ],
        // Put the parent to allow saving under `options`.
        '#parents' => array_merge($element['#field_parents'], [
          $field_name,
          $delta,
          'options',
          'icon_display',
        ]),
      ];
    }

    return $element;
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

}
