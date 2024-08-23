<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UiPatterns\PropType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\PropType;
use Drupal\ui_patterns\PropTypePluginBase;

/**
 * Provides a 'Icon' PropType.
 */
#[PropType(
  id: 'icon',
  label: new TranslatableMarkup('Icon'),
  default_source: 'icon',
  schema: [
    'type' => 'object',
    'properties' => [
      'icon' => ['type' => 'string'],
      'icon_pack' => ['$ref' => 'ui-patterns://machine_name'],
      'settings' => ['type' => 'object'],
    ],
    'required' => [
      'icon',
      'icon_pack',
    ],
  ],
  priority: 10
)]
class IconPropType extends PropTypePluginBase {

}
