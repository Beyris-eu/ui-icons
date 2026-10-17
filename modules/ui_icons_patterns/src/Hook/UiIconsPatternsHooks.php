<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;

/**
 * Hook implementations for ui_icons_patterns.
 */
class UiIconsPatternsHooks {

  public function __construct(
    protected readonly IconPackManagerInterface $iconPackManager,
  ) {}

  /**
   * Implements hook_ui_patterns_source_info_alter().
   *
   * The icon sources have nothing to offer when no icon pack has an icon.
   */
  #[Hook('ui_patterns_source_info_alter')]
  public function uiPatternsSourceInfoAlter(array &$definitions): void {
    if ($this->iconPackManager->getIcons() !== []) {
      return;
    }
    unset($definitions['icon'], $definitions['icon_renderable']);
  }

}
