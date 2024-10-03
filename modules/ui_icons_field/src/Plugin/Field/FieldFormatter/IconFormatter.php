<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'icon_formatter' formatter.
 */
#[FieldFormatter(
  id: 'icon_formatter',
  label: new TranslatableMarkup('Icon'),
  field_types: [
    'ui_icon',
  ],
)]
class IconFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an IconFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The Plugin settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
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
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected IconPackManagerInterface $pluginManagerIconPack,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );
    $this->pluginManagerIconPack = $pluginManagerIconPack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.ui_icons_pack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'icon_settings' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    if ($this->getSetting('icon_settings')) {
      $summary[] = $this->t('Specific icon settings saved');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $this->pluginManagerIconPack->getExtractorPluginForms(
      $elements,
      $form_state,
      $this->getSetting('icon_settings') ?: [],
      // @todo views do not retrieve FieldType value saved.
      $this->fieldDefinition->getSetting('allowed_icon_pack') ?: [],
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
   * Validation callback for extractor settings element.
   *
   * All extractor settings form values are serialized in a single declared
   * icon_settings form key.
   * This form can be included in different forms: Field UI, Views UI, Layout
   * Builder... to avoid an implementation for each structure we try to be
   * generic by looking for 'icon_settings' key, when encountered it means we
   * are at the level of the settings array to save, ie:
   * foo
   *   bar
   *     settings:
   *       pack_id_1: settings array
   *       pack_id_2: settings array
   *       icon_settings: ... this element key
   * This method isolate the 'settings', remove icon_settings part and save it
   * by setting it as value to the element.
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

    // Set the value for the element in the form state to b saved.
    $form_state->setValueForElement($element, reset($filtered_values));
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $icon_id = $item->get('target_id')->getValue();
      if (!$icon = $this->pluginManagerIconPack->getIcon((string) $icon_id)) {
        continue;
      }

      $pack_id = $icon->getPackId();

      $settings = [];
      $formatter_settings = $this->getSetting('icon_settings') ?? [];
      if (isset($formatter_settings[$pack_id])) {
        $settings = $formatter_settings[$pack_id];
      }

      $elements[$delta] = $icon->getRenderable($settings);
    }

    return $elements;
  }

}
