<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\IconDefinitionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a form element to select an icon.
 *
 * Properties:
 * - #default_value: (string) Icon value as icon_pack_id:icon_id.
 * - #show_settings: (bool) Enable extractor settings, default FALSE.
 * - #default_settings: (array) Settings for the extractor settings.
 * - #settings_title: (string) Extractor settings details title.
 * - #allowed_icon_pack: (array) Icon pack to limit the selection.
 * - #return_id: (bool) Form return icon id instead of icon object as default.
 *
 * Some base properties from FormElementBase.
 * - #description: (string) Help or description text for the input element.
 * - #placeholder: (string) Placeholder text for the input, default to
 *   'Start typing icon name'.
 * - #required: (bool) Whether or not input is required on the element.
 * - #size: (int): Textfield size, default 55.
 *
 * Global properties applied to the parent element:
 * - #attributes': (array) Attributes to the global element.
 *
 * @see web/core/lib/Drupal/Core/Render/Element/FormElementBase.php
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
        [$class, 'processIconAjaxForm'],
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
      '#default_settings' => [],
      '#settings_title' => new TranslatableMarkup('Settings'),
      '#return_id' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(mixed &$element, mixed $input, FormStateInterface $form_state): mixed {
    $icon = NULL;

    if ($input !== FALSE) {
      if (empty($input['icon_id'])) {
        // In case of default value we need to be able to delete.
        unset($element['#default_value']);
        return [];
      }
      $return = $input;

      /** @var \Drupal\ui_icons\IconDefinitionInterface $icon */
      $icon = self::iconPack()->getIcon($input['icon_id']);
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
      if (!empty($element['#default_value']) && is_string($element['#default_value'])) {
        /** @var \Drupal\ui_icons\IconDefinitionInterface $icon */
        $icon = self::iconPack()->getIcon($element['#default_value']);
      }
    }

    if ($icon) {
      $return['object'] = $icon;
      return $return;
    }

    return $input;
  }

  /**
   * Ajax callback for icon_autocomplete forms.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax icon.
   */
  public static function buildSettingsAjaxCallback(array &$form, FormStateInterface &$form_state, Request $request): AjaxResponse {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $form_parents = explode('/', $request->query->get('element_parents'));

    // Sanitize form parents before using them.
    $form_parents = array_filter($form_parents, [Element::class, 'child']);

    // Retrieve the element to be rendered.
    $form = NestedArray::getValue($form, $form_parents);

    $status_messages = ['#type' => 'status_messages'];
    $form['#prefix'] .= $renderer->renderRoot($status_messages);
    $output = $renderer->renderRoot($form);

    $response = new AjaxResponse();
    $response->setAttachments($form['#attached']);

    return $response->addCommand(new ReplaceCommand(NULL, $output));
  }

  /**
   * Callback for creating form sub element icon_id.
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
   *   The processed element with icon_id element.
   */
  public static function processIcon(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element['#tree'] = TRUE;

    if (isset($element['#value']) && $element['#value'] instanceof \Stringable) {
      $element['#value'] = [];
    }

    $element['icon_id'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Icon'),
      '#placeholder' => $element['#placeholder'] ?? new TranslatableMarkup('Start typing icon name'),
      '#title_display' => 'invisible',
      '#autocomplete_route_name' => 'ui_icons.autocomplete',
      '#required' => $element['#required'] ?? FALSE,
      '#size' => $element['#size'] ?? 55,
      '#maxlength' => 128,
      '#value' => $element['#value']['icon_id'] ?? $element['#default_value'] ?? '',
      '#error_no_message' => TRUE,
      // Ensure the validateIcon is run.
      '#limit_validation_errors' => [$element['#parents']],
    ];

    // Clean unwanted values on parent.
    unset($element['#size'], $element['#placeholder']);

    if (!empty($element['#allowed_icon_pack'])) {
      $element['icon_id']['#autocomplete_query_parameters']['allowed_icon_pack'] = implode('+', $element['#allowed_icon_pack']);
    }

    return $element;
  }

  /**
   * Callback for #ajax and settings form element.
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
   *   The processed element with added #ajax and extractor setting forms.
   */
  public static function processIconAjaxForm(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Generate a unique wrapper HTML ID.
    $ajax_wrapper_id = Html::getUniqueId('ajax-wrapper');

    // Prefix and suffix used for Ajax replacement.
    $element['#prefix'] = '<div id="' . $ajax_wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $parents_prefix = implode('_', $element['#parents']);

    $element['icon_id']['#attributes']['data-wrapper-id'] = $ajax_wrapper_id;

    $element['icon_id']['#ajax'] = [
      'callback' => [static::class, 'buildSettingsAjaxCallback'],
      'options' => [
        'query' => [
          'element_parents' => implode('/', $element['#array_parents']),
        ],
      ],
      // Autocomplete is already doing the ajax progress.
      'progress' => [
        'type' => 'none',
      ],
      'disable-refocus' => TRUE,
      'wrapper' => $ajax_wrapper_id,
      'effect' => 'none',
      // As we used autocomplete we want matching events.
      'event' => 'autocompleteclose change paste',
    ];

    // ProcessIcon will handle #value or #default_value.
    $icon_full_id = $element['#value']['icon_id'] ?? $element['icon_id']['#value'] ?? NULL;
    if (!$icon_full_id || !is_string($icon_full_id) || FALSE === strpos($icon_full_id, ':') || NULL === self::iconPack()->getIcon($icon_full_id)) {
      // If a default value based on a disabled icon pack exist, clear it.
      unset($element['icon_id']['#value']);
      return $element;
    }

    // If no settings or no value found.
    if (FALSE === (bool) $element['#show_settings']) {
      return $element;
    }

    $element['icon_settings'] = [
      '#type' => 'details',
      '#name' => 'icon[' . $parents_prefix . ']',
      '#title' => $element['#settings_title'],
    ];

    $icon_full_id = explode(':', $icon_full_id);
    $icon_pack_id = $icon_full_id[0];

    if (!empty($element['#allowed_icon_pack'])) {
      if (!in_array($icon_pack_id, $element['#allowed_icon_pack'])) {
        unset($element['icon_settings']);
        return $element;
      }
    }

    // Track the array size before adding forms, if no change it means we have
    // no extractors form.
    $settings_empty_count = count($element['icon_settings']);

    self::iconPack()->getExtractorPluginForms(
      $element['icon_settings'],
      $form_state,
      $element['#default_settings'] ?? [],
     [$icon_pack_id => $icon_pack_id],
    );

    // Remove if no extractor form is found.
    if ($settings_empty_count === count($element['icon_settings'])) {
      unset($element['icon_settings']);
    }

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
   *
   * @todo reduce complexity.
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

    if (empty($input['icon_id']) && !$element['#required']) {
      $form_state->setValueForElement($element, NULL);
      return;
    }

    /** @var \Drupal\ui_icons\IconDefinitionInterface $icon */
    $icon = self::iconPack()->getIcon($input['icon_id']);
    if (NULL === $icon || !$icon instanceof IconDefinitionInterface) {
      $form_state->setError($element['icon_id'], new TranslatableMarkup('Icon for %title is invalid: %icon.<br>Please search again and select a result in the list.', [
        '%title' => FormElementHelper::getElementTitle($element),
        '%icon' => $input['icon_id'],
      ]));
      return;
    }

    $icon_pack_id = $icon->getIconPackId();
    if (!empty($element['#allowed_icon_pack']) && !in_array($icon_pack_id, $element['#allowed_icon_pack'])) {
      $form_state->setError($element['icon_id'], new TranslatableMarkup('Icon for %title is not valid anymore because it is part of icon pack: %icon_pack_id. This field limit icon pack to: %limit.', [
        '%title' => FormElementHelper::getElementTitle($element),
        '%icon_pack_id' => $icon_pack_id,
        '%limit' => implode(', ', $element['#allowed_icon_pack']),
      ]));
      return;
    }

    $settings = [];
    if (isset($input['icon_settings'][$icon_pack_id])) {
      $settings[$icon_pack_id] = $input['icon_settings'][$icon_pack_id];
      // @todo validateConfigurationForm from extractor plugin.
    }

    if (isset($element['#return_id']) && TRUE === $element['#return_id']) {
      $form_state->setValueForElement($element, ['target_id' => $icon->getId(), 'settings' => $settings]);
      return;
    }

    $form_state->setValueForElement($element, ['icon' => $icon, 'settings' => $settings]);
  }

  /**
   * Wraps the icon pack service.
   *
   * @return \Drupal\ui_icons\Plugin\IconPackManagerInterface
   *   The icon pack manager service.
   */
  protected static function iconPack() {
    return \Drupal::service('plugin.manager.ui_icons_pack');
  }

}
