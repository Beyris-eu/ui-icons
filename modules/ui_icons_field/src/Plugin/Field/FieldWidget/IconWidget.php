<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'icon_widget' widget.
 */
#[FieldWidget(
  id: 'icon_widget',
  label: new TranslatableMarkup('Icon'),
  field_types: ['ui_icon'],
)]
class IconWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a IconWidget object.
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
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
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
      $container->get('plugin.manager.ui_icons_pack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'allowed_icon_pack' => [],
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
      '#title' => $this->t('Icon set selection'),
      '#description' => $this->t('Select icon pack to make available. Default to all if none selected.'),
      // @todo is there a way to have this without DI?
      '#options' => $this->pluginManagerIconPack->listIconPackWithDescriptionOptions(),
      '#default_value' => $this->getSetting('allowed_icon_pack'),
      '#multiple' => TRUE,
    ];

    $elements['show_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow icon settings form'),
      '#description' => $this->t('If selected, icon settings will be made available for all enabled Icon set above. Note that display setting on these field will be ignored.'),
      '#default_value' => $this->getSetting('show_settings'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
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

    if (!empty($settings['show_settings'])) {
      $summary[] = $this->t('Icon settings enabled');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $settings = $this->getSettings();

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];

    $element['value'] = [
      '#type' => 'icon_autocomplete',
      '#default_value' => NULL,
      '#title' => $cardinality === 1 ? $this->fieldDefinition->getLabel() : $this->t('Icon'),
      '#allowed_icon_pack' => array_filter($settings['allowed_icon_pack'] ?? []),
      '#show_settings' => $settings['show_settings'],
      '#required' => $element['#required'] ?? FALSE,
    ];

    if ($item && $item->target_id) {
      $element['value']['#default_value'] = $item->target_id;
      if (TRUE == $settings['show_settings'] && $item->settings) {
        // @todo default from formatter before definition?
        $element['value']['#default_settings'] = $item->settings;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$item) {
      if (empty($item['value']['icon']) || !$item['value']['icon'] instanceof IconDefinitionInterface) {
        // @todo should we set null or unset?
        $item['target_id'] = NULL;
        $item['settings'] = [];
        continue;
      }

      $icon = $item['value']['icon'];
      $item['target_id'] = $icon->getId();
      $settings = [];
      if (isset($item['value']['settings'])) {
        $settings = $item['value']['settings'];
      }
      $item['settings'] = $settings;
    }

    return $values;
  }

}
