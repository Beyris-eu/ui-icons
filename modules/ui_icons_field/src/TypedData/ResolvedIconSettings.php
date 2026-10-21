<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\TypedData;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\ui_icons\IconSettings;

/**
 * Computes the icon pack settings that apply to this item's icon.
 *
 * Stored settings are keyed by icon pack — the structure `icon_autocomplete`
 * emits and `ui_icons.icon_value` config schema types, so that each pack's
 * options can be validated against its own schema. Consumers of a single icon
 * only ever want the entry belonging to that icon's pack, which is what the
 * standard `{pack_id, icon_id, settings}` shape means by `settings`.
 *
 * @see \Drupal\ui_icons\Element\IconAutocomplete::buildValue()
 * @see \Drupal\ui_icons_field\Plugin\Field\FieldType\IconType::propertyDefinitions()
 */
final class ResolvedIconSettings extends Map {

  /**
   * {@inheritdoc}
   */
  public function getValue(): array {
    $item = $this->getParent();
    if (!$item instanceof FieldItemInterface) {
      return [];
    }
    $target_id = $item->get('target_id')->getValue();
    if (!is_string($target_id) || $target_id === '') {
      return [];
    }
    $pack_id = IconDefinition::getIconDataFromId($target_id)['pack_id'] ?? NULL;
    if ($pack_id === NULL) {
      return [];
    }
    $settings = $item->get('settings')->getValue();

    // Stored values can come from Canvas, REST or JSON:API, not only the
    // widget, and Canvas passes them to the component as is.
    return IconSettings::filter($target_id, is_array($settings) ? ($settings[$pack_id] ?? []) : []);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE): void {
    // Computed: settings are written through the `settings` property.
  }

}
