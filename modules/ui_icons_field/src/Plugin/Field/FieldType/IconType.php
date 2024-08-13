<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Plugin implementation of the 'ui_icon' field type.
 */
#[FieldType(
  id: "ui_icon",
  label: new TranslatableMarkup("UI Icon"),
  description: new TranslatableMarkup("Reference an Icon from icon pack definition."),
  default_widget: "icon_widget",
  default_formatter: "icon_formatter",
  list_class: IconFieldItemList::class,

)]
class IconType extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'Icon id.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
        ],
        'settings' => [
          'description' => 'The serialized settings of the Icon.',
          'type' => 'blob',
          'not null' => FALSE,
          'serialize' => TRUE,
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];
    $properties['target_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Icon ID'))
      ->setRequired(TRUE);
    $properties['settings'] = MapDataDefinition::create()
      ->setLabel(new TranslatableMarkup('Settings'));
    return $properties;

  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('target_id')->getValue();
    return $value === NULL || $value === '';
  }

}
