<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Base test IconUnitTestCase Controller class.
 */
abstract class IconUnitTestCase extends UnitTestCase {

  /**
   * Creates icon data array.
   *
   * @param array<string, string> $data
   *   The icon data to create for test.
   *
   * @return array<string, string|null>
   *   The icon data array.
   */
  protected static function createIconData(array $data = []): array {
    $icon_id = $data['icon_id'] ?? 'foo';
    return [
      'icon_id' => $icon_id,
      'source' => $data['source'] ?? sprintf('foo/bar/%s.svg', $icon_id),
      'absolute_path' => $data['absolute_path'] ?? sprintf('/_ROOT_/web/modules/my_module/foo/bar/%s.svg', $icon_id),
      'group' => $data['group'] ?? NULL,
    ];
  }

  /**
   * Create a mock icon.
   *
   * @param array<string, string>|null $iconData
   *   The icon data to create.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface
   *   The icon mocked.
   */
  protected function createMockIcon(?array $iconData = NULL): IconDefinitionInterface {
    if (NULL === $iconData) {
      $iconData = [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
      ];
    }

    $icon = $this->prophesize(IconDefinitionInterface::class);
    $icon
      ->getRenderable(['width' => $iconData['width'] ?? '', 'height' => $iconData['height'] ?? ''])
      ->willReturn(['#markup' => '<svg></svg>']);

    $icon_full_id = IconDefinition::createIconId($iconData['pack_id'], $iconData['icon_id']);
    $icon
      ->getId()
      ->willReturn($icon_full_id);

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
    $filtered_data = $data;
    $keys = ['pack_id', 'icon_id', 'template', 'source', 'group'];
    foreach ($keys as $key) {
      unset($filtered_data[$key]);
    }
    return IconDefinition::create(
      $data['pack_id'] ?? 'foo',
      $data['icon_id'] ?? 'bar',
      $data['template'] ?? 'baz',
      $data['source'] ?? NULL,
      $data['group'] ?? NULL,
      $filtered_data,
    );
  }

}
