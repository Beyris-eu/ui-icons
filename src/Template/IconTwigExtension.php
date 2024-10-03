<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Template;

use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for UI Icons.
 */
class IconTwigExtension extends AbstractExtension {

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('icon', [$this, 'getIconRenderable']),
      new TwigFunction('icon_preview', [$this, 'getIconPreview']),
    ];
  }

  /**
   * Get an icon renderable.
   *
   * @param string $pack_id
   *   The icon set ID.
   * @param string $icon_id
   *   The icon ID.
   * @param array $settings
   *   An array of settings for the icon.
   *
   * @return array
   *   The icon renderable.
   */
  public function getIconRenderable(string $pack_id, string $icon_id, ?array $settings = []): array {
    $icon_full_id = IconDefinition::createIconId($pack_id, $icon_id);
    $icon = $this->pluginManagerIconPack->getIcon($icon_full_id);
    if (!$icon) {
      return [];
    }

    return $icon->getRenderable($settings ?? []);
  }

  /**
   * Get an icon preview.
   *
   * @param string $pack_id
   *   The icon set ID.
   * @param string $icon_id
   *   The icon ID.
   * @param array $settings
   *   An array of settings for the icon.
   *
   * @return array
   *   The icon renderable.
   */
  public function getIconPreview(string $pack_id, string $icon_id, ?array $settings = []): array {
    $icon_full_id = IconDefinition::createIconId($pack_id, $icon_id);
    $icon = $this->pluginManagerIconPack->getIcon($icon_full_id);
    if (!$icon) {
      return [];
    }

    return $icon->getPreview($settings ?? ['size' => 32]);
  }

}
