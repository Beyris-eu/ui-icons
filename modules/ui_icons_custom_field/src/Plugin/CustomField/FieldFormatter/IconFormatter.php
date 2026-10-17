<?php

declare(strict_types=1);

namespace Drupal\ui_icons_custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'ui_icon' custom field formatter.
 */
#[FieldFormatter(
  id: 'ui_icon',
  label: new TranslatableMarkup('Icon'),
  field_types: [
    'ui_icon',
  ],
)]
class IconFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed {
    if (!is_string($value) || NULL === IconDefinition::getIconDataFromId($value)) {
      return NULL;
    }

    return IconDefinition::getRenderable($value);
  }

}
