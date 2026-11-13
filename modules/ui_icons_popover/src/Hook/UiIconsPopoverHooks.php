<?php

declare(strict_types=1);

namespace Drupal\ui_icons_popover\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\ui_icons_picker\Element\IconPickerTrigger;
use Drupal\ui_icons_popover\Element\IconPopoverTrigger;

/**
 * Hook implementations for ui_icons_popover.
 */
class UiIconsPopoverHooks {

  /**
   * Implements hook_element_info_alter().
   *
   * The popover is a lighter way to browse icons than the picker modal, so
   * when both modules are enabled the popover takes over the preview box of
   * 'icon_autocomplete'. Running last guarantees the picker callback is
   * already in the chain to be removed. Naming its class does not autoload it,
   * so this is safe when ui_icons_picker is off.
   */
  #[Hook('element_info_alter', order: Order::Last)]
  public function elementInfoAlter(array &$info): void {
    if (!isset($info['icon_autocomplete'])) {
      return;
    }

    $info['icon_autocomplete']['#process'] = array_values(array_filter(
      $info['icon_autocomplete']['#process'] ?? [],
      static fn ($callback): bool => $callback !== [IconPickerTrigger::class, 'processPickerTrigger'],
    ));
    $info['icon_autocomplete']['#process'][] = [IconPopoverTrigger::class, 'processPopoverTrigger'];
  }

}
