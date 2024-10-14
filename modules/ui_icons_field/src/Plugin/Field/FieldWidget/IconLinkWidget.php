<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'link field' widget with ui icons support.
 */
#[FieldWidget(
  id: 'icon_link_widget',
  label: new TranslatableMarkup('Link icon'),
  field_types: ['link'],
)]
class IconLinkWidget extends LinkWidget implements ContainerFactoryPluginInterface {

  /**
   * Constructs an IconLinkWidget instance.
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
   * @param \Drupal\ui_icons\Plugin\IconPackManagerInterface $pluginManagerIconPack
   *   The ui icons pack manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected IconPackManagerInterface $pluginManagerIconPack,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
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
      $configuration['third_party_settings'],
      $container->get('plugin.manager.icon_pack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'allowed_icon_pack' => [],
      'icon_selector' => 'icon_autocomplete',
      'icon_required' => TRUE,
      'icon_position' => FALSE,
      // Show settings is used by menu link implementation.
      // there is no settings form visible as it must be set only in the field
      // definition.
      'show_settings' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['allowed_icon_pack'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed icon packs'),
      '#description' => $this->t('If none are selected, all will be allowed.'),
      // @todo is there a way to have this without DI?
      '#options' => $this->pluginManagerIconPack->listIconPackOptions(TRUE),
      '#default_value' => $this->getSetting('allowed_icon_pack'),
      '#multiple' => TRUE,
    ];

    $elements['icon_selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon selector'),
      '#options' => $this->getPickerOptions(),
      '#default_value' => $this->getSetting('icon_selector'),
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

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $settings = $this->getSettings();

    $allowed_icon_pack = array_filter($settings['allowed_icon_pack']);

    if (!empty($allowed_icon_pack)) {
      // @todo is there a way to have this without DI?
      $labels = $this->pluginManagerIconPack->listIconPackOptions();
      $list = array_intersect_key($labels, $allowed_icon_pack);
      $summary[] = $this->t('With Icon set: @set', ['@set' => implode(', ', $list)]);
    }
    else {
      $summary[] = $this->t('All icon sets available for selection');
    }

    $icon_selector = $this->getSetting('icon_selector');
    $summary[] = $this->t('Selector: @type', ['@type' => $this->getPickerOptions()[$icon_selector]]);

    if (TRUE === (bool) $settings['icon_required']) {
      $summary[] = $this->t('Icon is required');
    }
    else {
      $summary[] = $this->t('Icon is not required');
    }

    if ((bool) $settings['icon_position']) {
      $summary[] = $this->t('Can set icon display');
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

    $icon_full_id = NULL;
    $options = $item->get('options')->getValue() ?? [];
    if (isset($options['icon']['target_id'])) {
      $icon = $this->pluginManagerIconPack->getIcon($options['icon']['target_id']);
      if ($icon instanceof IconDefinitionInterface) {
        $icon_full_id = $icon->getId();
      }
    }

    $icon_display = $options['icon_display'] ?? 'icon_only';
    $allowed_icon_pack = array_filter($this->getSetting('allowed_icon_pack') ?? []);
    $label = $this->fieldDefinition->getLabel() ?? $this->t('Link');
    $field_name = $this->fieldDefinition->getName();

    $icon_selector = $this->getSetting('icon_selector');
    $element['icon'] = [
      '#type' => $icon_selector,
      '#title' => $this->t('@name icon', ['@name' => $label]),
      '#description' => $this->t('Pick an Icon for this link.'),
      '#return_id' => TRUE,
      '#default_value' => $icon_full_id,
      '#allowed_icon_pack' => $allowed_icon_pack,
      // Show settings is used by menu link implementation.
      '#show_settings' => $settings['show_settings'] ?? FALSE,
      '#required' => $element['#required'] ? $settings['icon_required'] : FALSE,
      // Put the parent to allow saving under `options`.
      '#parents' => array_merge($element['#field_parents'], [
        $field_name,
        $delta,
        'options',
        'icon',
      ]),
    ];

    if (isset($options['icon']['settings'])) {
      $element['icon']['#default_settings'] = $options['icon']['settings'];
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

  /**
   * Get the icon selector options.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of options for selectors options.
   */
  private function getPickerOptions(): array {
    return [
      'icon_autocomplete' => $this->t('Autocomplete'),
      'icon_picker' => $this->t('Picker'),
    ];
  }

}
