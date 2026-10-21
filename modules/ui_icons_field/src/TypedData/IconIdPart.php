<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\TypedData;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * Computes one half of an icon's full ID: its `pack_id` or its `icon_id`.
 *
 * The `ui_icon` field type stores a single `target_id` column holding the full
 * icon ID (`pack_id:icon_id`). Consumers that expect the icon as structured
 * data — the standard `{pack_id, icon_id}` object shape used by the Field API,
 * the Render API and SDC — need the two halves as separate properties. This
 * computed property exposes them without changing storage.
 *
 * The name of the property being computed (`pack_id` or `icon_id`) is used as
 * the key into the parsed icon ID.
 *
 * Writing happens the other way around: `IconType::setValue()` recomposes
 * `target_id` when it is handed `pack_id` + `icon_id`, so these properties are
 * read-only.
 *
 * @see \Drupal\Core\Theme\Icon\IconDefinition::getIconDataFromId()
 * @see \Drupal\ui_icons_field\Plugin\Field\FieldType\IconType::propertyDefinitions()
 * @see \Drupal\ui_icons_field\Plugin\Field\FieldType\IconType::setValue()
 */
final class IconIdPart extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue(): ?string {
    $item = $this->getParent();
    if (!$item instanceof FieldItemInterface) {
      return NULL;
    }
    $target_id = $item->get('target_id')->getValue();
    if (!is_string($target_id) || $target_id === '') {
      return NULL;
    }
    $parts = IconDefinition::getIconDataFromId($target_id);

    return $parts[$this->getName()] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    // Computed: the value is always derived from `target_id`.
  }

}
