<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element to display an ui icon.
 *
 * Properties:
 * - #icon_pack: (string) Icon Pack provider plugin id.
 * - #icon: (string) Name of the icon.
 * - #settings: (array) Settings sent to the inline Twig template.
 *
 * Usage Example:
 * @code
 * $build['icon'] = [
 *   '#type' => 'ui_icon',
 *   '#icon_pack' => 'material_symbols',
 *   '#icon' => 'home',
 *   '#settings' => [
 *     'width' => 64,
 *   ],
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
      '#settings' => [],
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

    $context = [
      'icon_id' => $icon->getIconId(),
      'source' => $icon->getSource(),
    ];

    if ($content = $icon->getContent()) {
      $context['content'] = new FormattableMarkup($content, []);
    }

    // @todo do we need all data?
    $element['inline-template'] = [
      '#type' => 'inline_template',
      '#template' => $icon->getTemplate(),
      // @todo array_merge to define priority?
      '#context' => $context + $element['#settings'],
    ];

    if ($icon->getLibrary()) {
      $element['inline-template']['#attached'] = ['library' => [$icon->getLibrary()]];
    }

    return $element;
  }

}
