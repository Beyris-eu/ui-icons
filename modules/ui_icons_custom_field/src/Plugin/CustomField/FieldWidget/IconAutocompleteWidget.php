<?php

declare(strict_types=1);

namespace Drupal\ui_icons_custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;

/**
 * Plugin implementation of the 'ui_icon_autocomplete' custom field widget.
 */
#[CustomFieldWidget(
  id: 'ui_icon_autocomplete',
  label: new TranslatableMarkup('Icon autocomplete'),
  category: new TranslatableMarkup('Icon'),
  field_types: [
    'ui_icon',
  ],
)]
class IconAutocompleteWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'allowed_icon_pack' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    return [
      '#type' => 'icon_autocomplete',
      '#return_id' => TRUE,
      '#allowed_icon_pack' => array_filter($settings['allowed_icon_pack']),
    ] + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['allowed_icon_pack'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed icon packs'),
      '#description' => $this->t('If none are selected, all will be allowed.'),
      // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      '#options' => \Drupal::service('plugin.manager.icon_pack')->listIconPackOptions(TRUE),
      '#default_value' => $settings['allowed_icon_pack'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    // The element returns the icon id with its settings, the column holds the
    // id only.
    if (is_array($value)) {
      $value = $value['target_id'] ?? NULL;
    }

    return parent::massageFormValue($value, $column);
  }

}
