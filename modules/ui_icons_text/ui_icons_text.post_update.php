<?php

/**
 * @file
 * Post update functions for the ui_icons_text module.
 */

declare(strict_types=1);

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\filter\FilterFormatInterface;

/**
 * Keep icons wrapped in a span in the text formats embedding them.
 */
function ui_icons_text_post_update_icon_embed_wrapper(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'filter_format', static function (FilterFormatInterface $format): bool {
    $filters = $format->get('filters');
    if (!isset($filters['icon_embed']) || isset($filters['icon_embed']['settings']['wrapper'])) {
      return FALSE;
    }
    $filters['icon_embed']['settings']['wrapper'] = TRUE;
    $format->set('filters', $filters);
    return TRUE;
  });
}
