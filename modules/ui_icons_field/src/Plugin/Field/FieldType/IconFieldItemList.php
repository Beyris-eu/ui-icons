<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Represents a configurable icon field.
 */
class IconFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state): array {
    if (!empty($this->getFieldDefinition()->getDefaultValueCallback())) {
      return [];
    }

    $default_value = $this->getFieldDefinition()->getDefaultValueLiteral();

    $icon_id = NULL;
    if (isset($default_value[0]['default_icon']['target_id'])) {
      $icon_id = $default_value[0]['default_icon']['target_id'];
    }

    $settings = NULL;
    if (isset($default_value[0]['default_icon']['settings']) && is_array($default_value[0]['default_icon']['settings'])) {
      $settings = $default_value[0]['default_icon']['settings'];
    }

    $element = [
      '#parents' => ['default_value_input'],
      'default_icon' => [
        '#type' => 'icon_autocomplete',
        '#title' => $this->t('Default icon'),
        '#show_settings' => TRUE,
      ],
    ];

    if ($icon_id) {
      $element['default_icon']['#default_value'] = $icon_id;
      if ($settings) {
        $element['default_icon']['#default_settings'] = $settings;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state): array {
    if ($form_state->getValue('default_value_input')) {
      $values = $form_state->getValue('default_value_input');
      $icon = $values['default_icon']['icon'] ?? NULL;
      $settings = $values['default_icon']['settings'] ?? [];

      $value = [
        'target_id' => $icon ? $icon->getId() : '',
        'settings' => $settings,
      ];
      $form_state->setValueForElement($element['default_icon'], $value);

      return [$form_state->getValue('default_value_input')];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition): array {
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    if (isset($default_value[0]['default_icon']['target_id'])) {
      $default_value = [
        [
          'target_id' => $default_value[0]['default_icon']['target_id'],
          'settings' => $default_value[0]['default_icon']['settings'] ?? [],
        ],
      ];
    }

    return $default_value;
  }

}
