<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Handle UI Icons extractor settings form.
 */
class IconExtractorSettingsForm {

  /**
   * Create the form API element from the settings.
   *
   * @param array $settings
   *   The settings from the icon pack definition.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The from state used to get values if no form context.
   *
   * @return array
   *   The form API generated.
   */
  public static function generateSettingsForm(array $settings, ?FormStateInterface $form_state = NULL): array {
    $saved_values = $form_state ? $form_state->getCompleteFormState()->getValue('saved_values') ?? [] : [];
    $form = [];

    foreach ($settings as $setting_id => $setting) {

      if (isset($setting['enum']) && is_array($setting['enum']) && !empty($setting['enum'])) {
        $form[$setting_id] = self::buildEnumForm($setting_id, $setting, $saved_values);
        continue;
      }

      // Settings format is a subset of JSON Schema, with only the scalars.
      $form[$setting_id] = match ($setting['type']) {
        'boolean' => self::buildBooleanForm($setting_id, $setting, $saved_values),
        'number' => self::buildNumberForm($setting_id, $setting, $saved_values),
        'integer' => self::buildNumberForm($setting_id, $setting, $saved_values),
        'string' => self::buildStringForm($setting_id, $setting, $saved_values),
        // @todo not supported scalar could be removed when JSON validation of definition is done.
        'null' => [],
        'object' => [],
        'array' => [],
        default => [],
      };
    }

    return array_filter($form);
  }

  /**
   * Init setting from from common JSON Schema properties.
   *
   * @param string $setting_id
   *   The setting id from the icon pack definition.
   * @param array $setting
   *   The settings from the icon pack definition.
   * @param array $saved_values
   *   The default saved values if any.
   *
   * @return array
   *   The form API generated with minimal keys.
   */
  protected static function initSettingForm(string $setting_id, array $setting, array $saved_values): array {
    $form = [
      '#title' => $setting['title'] ?? $setting_id,
    ];

    if (isset($setting['description'])) {
      $form['#description'] = $setting['description'];
    }

    if (isset($setting['default'])) {
      $form['#default_value'] = $setting['default'];
    }

    if (isset($saved_values[$setting_id])) {
      $form['#default_value'] = $saved_values[$setting_id];
    }

    return $form;
  }

  /**
   * Build Drupal form for an enumerations.
   *
   * @param string $setting_id
   *   The setting id from the icon pack definition.
   * @param array $setting
   *   The settings from the icon pack definition.
   * @param array $saved_values
   *   The default saved values if any.
   *
   * @return array
   *   The form API generated for enum as select.
   */
  protected static function buildEnumForm(string $setting_id, array $setting, array $saved_values): array {
    $form = self::initSettingForm($setting_id, $setting, $saved_values);
    $form['#type'] = 'select';
    $form['#options'] = self::getOptions($setting);
    return $form;
  }

  /**
   * Get option list for enumerations.
   *
   * @param array $setting
   *   The settings from the icon pack definition.
   *
   * @return array
   *   The enum options for select.
   */
  protected static function getOptions(array $setting): array {
    $options = array_combine($setting['enum'], $setting['enum']);
    foreach ($options as $key => $label) {
      if (is_string($label)) {
        $options[$key] = ucwords($label);
      }
    }
    if (!isset($setting['meta:enum'])) {
      return $options;
    }
    $meta = $setting['meta:enum'];

    // Remove meta:enum items not found in options.
    return array_intersect_key($meta, $options);
  }

  /**
   * Build Drupal form for a boolean setting.
   *
   * @param string $setting_id
   *   The setting id from the icon pack definition.
   * @param array $setting
   *   The settings from the icon pack definition.
   * @param array $saved_values
   *   The default saved values if any.
   *
   * @return array
   *   The form API generated for enum as checkbox.
   */
  protected static function buildBooleanForm(string $setting_id, array $setting, array $saved_values): array {
    $form = self::initSettingForm($setting_id, $setting, $saved_values);
    $form['#type'] = 'checkbox';
    return $form;
  }

  /**
   * Build Drupal form for a string setting.
   *
   * @param string $setting_id
   *   The setting id from the icon pack definition.
   * @param array $setting
   *   The settings from the icon pack definition.
   * @param array $saved_values
   *   The default saved values if any.
   *
   * @return array
   *   The form API generated for enum as textfield.
   */
  protected static function buildStringForm(string $setting_id, array $setting, array $saved_values): array {
    $form = self::initSettingForm($setting_id, $setting, $saved_values);

    if (isset($setting['format']) && $setting['format'] === 'color') {
      $form['#type'] = 'color';
      return $form;
    }

    $form['#type'] = 'textfield';

    if (isset($setting['pattern']) && !empty($setting['pattern'])) {
      $form['#pattern'] = $setting['pattern'];
    }

    if (isset($setting['maxLength'])) {
      $form['#maxlength'] = $setting['maxLength'];
      $form['#size'] = $setting['maxLength'];
    }

    // We don't support minLength and pattern together because it is not
    // possible to safely merge regular expressions.
    if (!isset($setting['pattern']) && isset($setting['minLength'])) {
      $form['#pattern'] = '^.{' . $setting['minLength'] . ',}$';
    }

    if (isset($setting['examples']) && is_array($setting['examples']) && !empty($setting['examples'])) {
      $form['#placeholder'] = $setting['examples'][0];
    }

    return $form;
  }

  /**
   * Build Drupal form for a number or integer setting.
   *
   * @param string $setting_id
   *   The setting id from the icon pack definition.
   * @param array $setting
   *   The settings from the icon pack definition.
   * @param array $saved_values
   *   The default saved values if any.
   *
   * @return array
   *   The form API generated for enum as number.
   */
  protected static function buildNumberForm(string $setting_id, array $setting, array $saved_values): array {
    $form = self::initSettingForm($setting_id, $setting, $saved_values);

    $form['#type'] = 'number';

    if ($setting['type'] === 'integer') {
      $form['#step'] = 1;
    }

    if (isset($setting['multipleOf'])) {
      $form['#step'] = $setting['multipleOf'];
    }

    if (isset($setting['minimum'])) {
      $form['#min'] = $setting['minimum'];
    }

    if (isset($setting['maximum'])) {
      $form['#max'] = $setting['maximum'];
    }

    return $form;
  }

}
