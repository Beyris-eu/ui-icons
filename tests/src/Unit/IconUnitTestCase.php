<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Tests IconUnitTestCase Controller class.
 */
abstract class IconUnitTestCase extends UnitTestCase {

  /**
   * Creates icon data array.
   *
   * @param string|null $icon_pack_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $icon_name
   *   The name of the icon.
   * @param string|null $iconPack_label
   *   The label of the icon set.
   *
   * @return array
   *   The icon data array.
   */
  protected static function createIconData(?string $icon_pack_id = NULL, ?string $icon_id = NULL, ?string $icon_name = NULL, ?string $iconPack_label = NULL): array {
    return [
      ($icon_pack_id ?? 'foo') . ':' . ($icon_id ?? 'bar') => [
        'name' => $icon_name ?? 'Bar',
        'source' => 'qux/corge',
        'icon_pack_id' => $icon_pack_id ?? 'foo',
        'icon_pack_label' => $iconPack_label ?? 'Baz',
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
   * @param array $iconData
   *   The icon data to create.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface
   *   The icon mocked.
   */
  protected function createIcon(array $iconData): IconDefinitionInterface {
    return IconDefinition::create(
      $iconData['name'],
      $iconData['source'],
      [
        'icon_pack_id' => $iconData['icon_pack_id'],
        'icon_pack_label' => $iconData['icon_pack_label'],
      ]
    );
  }

}
