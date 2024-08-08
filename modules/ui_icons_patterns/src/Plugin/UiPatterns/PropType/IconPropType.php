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
      'icon' => ['$ref' => 'ui-patterns://machine_name'],
      'iconset' => ['type' => 'string'],
      'options' => ['type' => 'object'],
    ],
    'required' => [
      'icon',
      'iconset',
    ],
  ],
  priority: 10
)]

/**
 *
 */
class IconPropType extends PropTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public static function normalize(mixed $value): mixed {
    return $value;
  }

}
