<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Handle UI Icons extractor settings form.
 */
class IconExtractorSettingsForm {

  /**
   * Create the form API element from extractor options.
   *
   * @param array $options
   *   The options from extractor definition.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The from state used to get values if n form context.
   *
   * @return array
   *   The form API generated.
   */
  public static function generateSettingsForm(array $options, ?FormStateInterface $form_state = NULL): array {
    $saved_values = $form_state ? $form_state->getCompleteFormState()->getValue('saved_values') ?? [] : [];
    $form = [];

    foreach ($options as $key => $definition) {
      $type = self::determineFieldType($definition['type'] ?? 'string');
      $form[$key] = [
        '#type' => isset($definition['enum']) ? 'select' : $type,
        '#title' => $definition['title'] ?? $key,
      ];

      self::setFieldProperties($form[$key], $definition, $saved_values[$key] ?? NULL);

      if (in_array($type, ['number', 'range'])) {
        self::setNumberFieldProperties($form[$key], $definition);
      }
    }

    return $form;
  }

  /**
   * Determine the field type based on the provided type.
   *
   * @param string $type
   *   The type provided in the definition.
   *
   * @return string
   *   The corresponding form field type.
   */
  private static function determineFieldType(string $type): string {
    switch ($type) {
      case 'string':
        return 'textfield';

      case 'number':
      case 'integer':
      case 'float':
      case 'decimal':
        return 'number';

      case 'boolean':
        return 'checkbox';

      case 'color':
        return 'color';

      case 'range':
        return 'range';

      default:
        return 'textfield';
    }
  }

  /**
   * Set common properties for each form field.
   *
   * @param array &$field
   *   The form field array to be modified.
   * @param array $definition
   *   The definition array containing field properties.
   * @param mixed $saved_value
   *   The saved value for the field, if any.
   */
  private static function setFieldProperties(array &$field, array $definition, mixed $saved_value): void {
    $properties = [
      'description' => '#description',
      'enum' => '#options',
      'size' => '#size',
      'maxlength' => '#maxlength',
      'pattern' => '#pattern',
      'required' => '#required',
      'prefix' => '#field_prefix',
      'suffix' => '#field_suffix',
      'placeholder' => '#placeholder',
    ];

    foreach ($properties as $key => $property) {
      if (isset($definition[$key])) {
        $field[$property] = $key === 'enum' ? array_combine($definition[$key], $definition[$key]) : $definition[$key];
      }
    }

    if ($saved_value !== NULL) {
      $field['#default_value'] = $saved_value;
    }
    elseif (isset($definition['default'])) {
      $field['#default_value'] = $definition['default'];
    }
  }

  /**
   * Set properties specific to number and range fields.
   *
   * @param array &$field
   *   The form field array to be modified.
   * @param array $definition
   *   The definition array containing field properties.
   */
  private static function setNumberFieldProperties(array &$field, array $definition): void {
    $numberProperties = ['min', 'max', 'step'];

    foreach ($numberProperties as $property) {
      if (isset($definition[$property])) {
        $field['#' . $property] = $definition[$property];
      }
    }

    // Avoid form validation error if step do not match default.
    if (!isset($field['#step']) && isset($definition['default']) && is_float($definition['default'])) {
      $decimalPart = explode('.', (string) $definition['default'])[1] ?? '';
      $digit = strlen($decimalPart);
      $field['#step'] = ($digit === 2) ? 0.01 : (($digit === 3) ? 0.001 : 0.1);
    }
  }

}
