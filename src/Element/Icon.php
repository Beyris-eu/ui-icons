<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element to display an ui icon.
 *
 * Properties:
 * - #icon_pack: Icon Pack provider plugin id.
 * - #icon: Name of the icon.
 * - #context: Optional, values sent to the inline Twig template.
 *
 * Usage Example:
 * @code
 * $build['ui_icon'] = [
 *   '#type' => 'ui_icon',
 *   '#icon_pack' => 'material_symbols',
 *   '#icon' => 'home',
 *   '#context' => [],
 * ];
 * @endcode
 */
#[RenderElement('ui_icon')]
class Icon extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#pre_render' => [
        [self::class, 'preRenderIcon'],
      ],
      '#icon_pack' => '',
      '#icon' => '',
      '#context' => [],
    ];
  }

  /**
   * Ui icon element pre render callback.
   *
   * @param array $element
   *   An associative array containing the properties of the ui_icon element.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderIcon(array $element): array {
    /** @var \Drupal\ui_icons\Plugin\IconPackManagerInterface $pluginManagerIconPack */
    $pluginManagerIconPack = \Drupal::service('plugin.manager.ui_icons_pack');

    $icon = $pluginManagerIconPack->getIcon($element['#icon_pack'] . ':' . $element['#icon']);
    if (!$icon) {
      return $element;
    }
    $element['inline-template'] = $icon->getRenderable($element['#context'] ?? []);

    return $element;
  }

}
