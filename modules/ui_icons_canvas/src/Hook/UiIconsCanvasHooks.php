<?php

declare(strict_types=1);

namespace Drupal\ui_icons_canvas\Hook;

use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropShape\CandidateStorablePropShape;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Drupal Canvas integration of UI Icons.
 */
class UiIconsCanvasHooks {

  /**
   * Implements hook_field_widget_info_alter().
   *
   * Declares the Canvas client-side transform for ui_icons' native
   * `icon_widget`, exactly how Canvas integrates its own widgets
   * (string_textfield, link_default, media_library_widget, …). Canvas refuses
   * (with a LogicException) to render any widget that lacks this metadata, so
   * without it the `ui_icon` field type cannot be edited as a component input.
   *
   * Both of the widget's selectors (`icon_autocomplete`, and `icon_picker`
   * which extends it) nest their form output under `value[icon_id]` — too deep
   * for Canvas's stock `mainProperty` transform — so the custom `uiIcon`
   * transform builds the `{target_id, settings}` item the field type stores.
   * Its library (canvas.transform.uiIcon) is auto-attached to the builder UI
   * by Canvas.
   *
   * @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::fieldWidgetInfoAlter()
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    if (isset($info['icon_widget'])) {
      $info['icon_widget']['canvas']['transforms'] = [
        'uiIcon' => [],
      ];
    }
  }

  /**
   * Implements hook_canvas_storable_prop_shape_alter().
   *
   * Completes the Canvas integration: hook_field_widget_info_alter() teaches
   * Canvas how to *edit* the `icon_widget`, but Canvas only offers that widget
   * once a prop is mapped to the `ui_icon` field type.
   *
   * This maps the *standard* UI Icons prop shape — the `{pack_id, icon_id}`
   * object also used by UI Patterns and by ui_icons' own SDC integration — onto
   * the `ui_icon` field type's `pack_id` and `icon_id` properties, edited with
   * ui_icons' native `icon_widget`. Its optional `settings` member carries the
   * icon pack's extractor settings, and is mapped only when the component asks
   * for it.
   *
   * Matching is deliberately structural rather than keyed off a Canvas-specific
   * JSON Schema keyword or a `$ref` name, so component authors write one icon
   * prop that works inside and outside Canvas, however they spell it.
   *
   * @see \Drupal\canvas\Hook\ShapeMatchingHooks
   */
  #[Hook('canvas_storable_prop_shape_alter')]
  public function canvasStorablePropShapeAlter(CandidateStorablePropShape $shape): void {
    // Use the resolved schema, so a prop reaching this hook behind a `$ref` is
    // matched on the same footing as one written inline.
    $schema = $shape->shape->resolvedSchema;
    if (($schema['type'] ?? NULL) !== 'object') {
      return;
    }
    $properties = $schema['properties'] ?? [];
    foreach (['pack_id', 'icon_id'] as $required_property) {
      if (($properties[$required_property]['type'] ?? NULL) !== 'string') {
        return;
      }
    }
    // The `ui_icon` field type would drop any other member, and a `settings`
    // member that is not an object: leave such an object to Canvas, which then
    // reports the prop as unsupported.
    if (array_diff_key($properties, array_flip(['pack_id', 'icon_id', 'settings']))) {
      return;
    }
    if (isset($properties['settings']) && ($properties['settings']['type'] ?? NULL) !== 'object') {
      return;
    }

    // TRICKY: `pack_id` and `icon_id` are computed properties on the `ui_icon`
    // field type, both derived from the single stored `target_id` column that
    // `icon_widget` writes.
    // @see \Drupal\ui_icons_field\Plugin\Field\FieldType\IconType::propertyDefinitions()
    $object_props = [
      'pack_id' => new FieldTypePropExpression('ui_icon', 'pack_id'),
      'icon_id' => new FieldTypePropExpression('ui_icon', 'icon_id'),
    ];
    // `settings` is optional in the standard shape: only map it for components
    // that actually declare it, so the expression stays minimal for the rest.
    if (isset($properties['settings'])) {
      $object_props['settings'] = new FieldTypePropExpression('ui_icon', 'resolved_settings');
      // Canvas cannot pass field *widget* settings from a prop shape, but it
      // does apply field *instance* settings to the conjured field definition —
      // which is how `icon_widget` learns to render the icon pack's settings
      // sub-form for this prop.
      // @see \Drupal\canvas\PropSource\StaticPropSource::conjureFieldItemList()
      // @see \Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget::formElement()
      $shape->fieldInstanceSettings = ['allow_settings' => TRUE];
    }
    $shape->fieldTypeProp = new FieldTypeObjectPropsExpression('ui_icon', $object_props);
    $shape->fieldWidget = 'icon_widget';
  }

}
