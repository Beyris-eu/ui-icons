<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Provides a form element to select an icon.
 *
 * Properties:
 * - #size: The size of the input element in characters.
 * - #default_value: Optional default icon id as icon_pack_id:icon_id.
 * - #allowed_icon_pack: Optional array of icon pack to limit the selection.
 * - #show_settings: Boolean to enable extractor settings, default FALSE.
 * - #default_settings: Optional array of settings for the extractor settings.
 * - #settings_title: Optional extractor settings title.
 *
 * Usage example:
 * @code
 * $form['icon_autocomplete'] = [
 *   '#type' => 'icon_autocomplete',
 *   '#title' => $this->t('Select icon'),
 *   '#default_value' => 'my_icon_pack:my_default_icon',
 *   '#allowed_icon_pack' => [
 *     'my_icon_pack,
 *     'other_icon_pack',
 *   ],
 *   '#show_settings' => TRUE,
 * ];
 * @endcode
 *
 * @todo create a base class to allow easier creation of other form element.
 */
#[FormElement('icon_autocomplete')]
class IconAutocomplete extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#element_validate' => [
        [$class, 'validateIcon'],
      ],
      '#process' => [
        [$class, 'processIcon'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#theme' => 'input__icon',
      '#theme_wrappers' => ['form_element'],
      '#allowed_icon_pack' => [],
      '#show_settings' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state): mixed {
    $icon = NULL;
    if ($input !== FALSE && !empty($input['icon_id'])) {
      $return = $input;

      /** @var \Drupal\ui_icons\IconDefinitionInterface $icon */
      $icon = \Drupal::service('plugin.manager.ui_icons_pack')->getIcon($input['icon_id']);
      if (NULL === $icon) {
        return $return;
      }

      // Settings filtered to store only the current icon values. Keep indexed
      // with the icon pack id to match the forms default settings parameter.
      $icon_pack_id = $icon->getIconPackId();
      if (isset($input['icon_settings'][$icon_pack_id])) {
        $return['icon_settings'] = [$icon_pack_id => $input['icon_settings'][$icon_pack_id]];
      }
    }
    else {
      if (!empty($element['#default_value'])) {
        /** @var \Drupal\ui_icons\IconDefinitionInterface $icon */
        $icon = \Drupal::service('plugin.manager.ui_icons_pack')->getIcon($element['#default_value']);
      }
    }

    $return['object'] = $icon;

    return $return;
  }

  /**
   * Callback for #options form element property.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element with added extractor plugin setting forms.
   */
  public static function processIcon(&$element, FormStateInterface $form_state, &$complete_form): array {
    $element['#tree'] = TRUE;

    $element['icon_id'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Icon'),
      '#placeholder' => $element['#placeholder'] ?? new TranslatableMarkup('Start typing icon name'),
      '#title_display' => 'invisible',
      '#autocomplete_route_name' => 'ui_icons.autocomplete',
      '#attributes' => $element['#attributes'] ?? [],
      '#required' => $element['#required'] ?? FALSE,
      '#size' => $element['#size'] ?? 60,
      '#maxlength' => 128,
      '#error_no_message' => TRUE,
    ];

    if (isset($element['#allowed_icon_pack']) && !empty($element['#allowed_icon_pack'])) {
      $element['icon_id']['#autocomplete_query_parameters']['allowed_icon_pack'] = implode('+', $element['#allowed_icon_pack']);
    }

    // Set the textfield value.
    $icon = NULL;
    if (isset($element['#value']['object']) && $element['#value']['object'] instanceof IconDefinitionInterface) {
      $icon = $element['#value']['object'];
      $element['icon_id']['#value'] = $icon->getId();
    }

    // If no settings stop here.
    if (!isset($element['#show_settings']) || FALSE === (bool) $element['#show_settings']) {
      return $element;
    }

    // @todo settings as ajax to avoid validation errors on hidden fields.
    $element['icon_settings'] = [
      '#type' => 'details',
      '#title' => $element['#settings_title'] ?? new TranslatableMarkup('Settings'),
    ];

    $default_settings = [];
    if (isset($element['#default_settings'])) {
      $default_settings = $element['#default_settings'];
    }

    $pluginManagerIconPack = \Drupal::service('plugin.manager.ui_icons_pack');
    $pluginManagerIconPack->getExtractorPluginForms(
      $element['icon_settings'],
      $form_state,
      $default_settings,
      $allowed_icon_pack = $element['#allowed_icon_pack'] ?? []
    );

    return $element;
  }

  /**
   * Form element validation extractor for ui_icons_autocomplete elements.
   *
   * @param array $element
   *   The element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The complete form array.
   */
  public static function validateIcon(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input_exists = FALSE;
    $values = $form_state->getValues();
    if (!$values) {
      return;
    }
    $input = NestedArray::getValue($values, $element['#parents'], $input_exists);
    if (!$input_exists) {
      return;
    }

    $title = FormElementHelper::getElementTitle($element);

    if (empty($input['icon_id']) && !$element['#required']) {
      $form_state->setValueForElement($element, NULL);
      return;
    }

    /** @var \Drupal\ui_icons\IconDefinitionInterface $icon */
    $icon = \Drupal::service('plugin.manager.ui_icons_pack')->getIcon($input['icon_id']);
    if (NULL === $icon || !$icon instanceof IconDefinitionInterface) {
      $form_state->setError($element['icon_id'], new TranslatableMarkup('Icon for %title is invalid: %icon.', [
        '%title' => $title,
        '%icon' => $input['icon_id'],
      ]));
      return;
    }

    $icon_pack_id = $icon->getIconPackId();
    if (!empty($element['#allowed_icon_pack']) && !in_array($icon_pack_id, $element['#allowed_icon_pack'])) {
      $form_state->setError($element['icon_id'], new TranslatableMarkup('Icon for %title is not valid anymore because it is part of icon pack: %icon_pack_id. This field limit icon pack to: %limit.', [
        '%title' => $title,
        '%icon_pack_id' => $icon_pack_id,
        '%limit' => implode(', ', $element['#allowed_icon_pack']),
      ]));
      return;
    }

    $settings = [];
    if (isset($input['icon_settings'][$icon_pack_id])) {
      $settings[$icon_pack_id] = $input['icon_settings'][$icon_pack_id];
    }

    $form_state->setValueForElement($element, ['icon' => $icon, 'settings' => $settings]);
  }

}
