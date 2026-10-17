<?php

declare(strict_types=1);

namespace Drupal\ui_icons_custom_field\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_icons_custom_field.
 */
class UiIconsCustomFieldHooks {

  /**
   * Implements hook_config_schema_info_alter().
   *
   * Custom Field keeps the settings of every subfield widget in one mapping.
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    $definitions['custom_field.field.*']['mapping'] += [
      'allowed_icon_pack' => [
        'type' => 'sequence',
        'label' => 'Allowed icon packs',
        'sequence' => [
          'type' => 'string',
          'label' => 'Icon pack ID',
        ],
      ],
    ];
  }

}
