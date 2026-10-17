<?php

declare(strict_types=1);

namespace Drupal\ui_icons_custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'ui_icon' custom field type.
 *
 * Stores the icon full id, `pack_id:icon_id`.
 */
#[CustomFieldType(
  id: 'ui_icon',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('An icon from the icon packs of the site.'),
  category: new TranslatableMarkup('General'),
  default_widget: 'ui_icon_autocomplete',
  default_formatter: 'ui_icon',
)]
class IconType extends CustomFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    return [
      $name => [
        'type' => 'varchar',
        'length' => 255,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    return [
      $name => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
        ->setRequired(FALSE),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): mixed {
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $icons = array_keys(\Drupal::service('plugin.manager.icon_pack')->getIcons());
    return $icons ? $icons[array_rand($icons)] : NULL;
  }

}
