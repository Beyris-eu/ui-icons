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
      // TRICKY: deliberately a plain string, not `ui-patterns://identifier`.
      // UI Patterns' schema compatibility check requires an identical
      // `pattern` on both sides, so referencing `identifier` here would mean
      // only components that copy that exact regex are recognized as icons —
      // making the documented `{pack_id, icon_id, settings}` shape unusable
      // for everyone else, Drupal Canvas included.
      // @see \Drupal\ui_patterns\SchemaManager\ScalarCompatibilityTrait::isStringPatternCompatible()
      'pack_id' => ['type' => 'string'],
      'icon_id' => ['type' => 'string'],
      'settings' => ['type' => 'object'],
    ],
    'required' => [
      'pack_id',
      'icon_id',
    ],
  ],
  priority: 10
)]
class IconPropType extends PropTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getSummary(array $definition): array {
    $summary = parent::getSummary($definition);
    if (isset($definition['properties']['pack_id']['enum'])) {
      $icon_packs = $definition['properties']['pack_id']['enum'];
      $summary[] = $this->t('Allowed icon packs: @values', ['@values' => implode(',', $icon_packs)]);
    }

    return $summary;
  }

}
