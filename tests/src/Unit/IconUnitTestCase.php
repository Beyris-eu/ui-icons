<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Tests IconUnitTestCase Controller class.
 */
abstract class IconUnitTestCase extends UnitTestCase {

  /**
   * Creates icon data search result array.
   *
   * @param string|null $icon_pack_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $icon_pack_label
   *   The label of the icon set.
   *
   * @return array
   *   The icon data array.
   */
  protected static function createIconResultData(?string $icon_pack_id = NULL, ?string $icon_id = NULL, ?string $icon_pack_label = NULL): array {
    $label = ucfirst(str_replace(['-', '_', '.'], ' ', ($icon_id ?? 'bar')));
    return [
      'value' => ($icon_pack_id ?? 'foo') . ':' . ($icon_id ?? 'bar'),
      'label' => new FormattableMarkup('<span class="ui-menu-icon">@icon</span> @name', [
        '@icon' => '_rendered_',
        '@name' => $label . ' (' . ($icon_pack_label ?? 'Baz') . ')',
      ]),
    ];
  }

  /**
   * Creates icon data array.
   *
   * @param string|null $icon_pack_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $icon_pack_label
   *   The label of the icon set.
   *
   * @return array
   *   The icon data array.
   */
  protected static function createIconData(?string $icon_pack_id = NULL, ?string $icon_id = NULL, ?string $icon_pack_label = NULL): array {
    return [
      ($icon_pack_id ?? 'foo') . ':' . ($icon_id ?? 'bar') => [
        'icon_id' => $icon_id ?? 'bar',
        'source' => 'qux/corge',
        'icon_pack_id' => $icon_pack_id ?? 'foo',
        'icon_pack_label' => $icon_pack_label ?? 'Baz',
      ],
    ];
  }

  /**
   * Create a mock icon.
   *
   * @param array|null $iconData
   *   The icon data to create.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface
   *   The icon mocked.
   */
  protected function createMockIcon(?array $iconData = NULL): IconDefinitionInterface {
    if (NULL === $iconData) {
      $iconData = [
        'icon_pack_id' => 'foo',
        'icon_id' => 'bar',
      ];
    }

    $icon = $this->prophesize(IconDefinitionInterface::class);
    $icon
      ->getRenderable(['width' => $iconData['width'] ?? '', 'height' => $iconData['height'] ?? ''])
      ->willReturn(['#markup' => '<svg></svg>']);

    $id = $iconData['icon_pack_id'] . ':' . $iconData['icon_id'];
    $icon
      ->getId()
      ->willReturn($id);

    return $icon->reveal();
  }

  /**
   * Create an icon.
   *
   * @param array $data
   *   The icon data to create.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface
   *   The icon mocked.
   */
  protected function createTestIcon(array $data): IconDefinitionInterface {
    $filtered_data = [];
    $keys = ['icon_pack_id', 'icon_pack_label', 'template', 'config', 'library', 'content', 'extractor', 'preview'];
    foreach ($keys as $key) {
      if (isset($data[$key])) {
        $filtered_data[$key] = $data[$key];
      }
    }

    return IconDefinition::create(
      $data['icon_id'] ?? '',
      $filtered_data,
      $data['source'] ?? '',
      $data['group'] ?? NULL,
    );
  }

}
