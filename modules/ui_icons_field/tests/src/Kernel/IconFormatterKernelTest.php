<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests which icon settings the IconFormatter renders.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconFormatter::class)]
#[Group('ui_icons')]
class IconFormatterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'system',
    'user',
    'entity_test',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * Tests per-value settings override the formatter settings when allowed.
   */
  #[DataProvider('providerViewElementsSettings')]
  public function testViewElementsSettings(bool $allow_settings, array $item_settings, array $expected): void {
    $this->installEntitySchema('entity_test');
    FieldStorageConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => 'entity_test',
      'type' => 'ui_icon',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => ['allow_settings' => $allow_settings],
    ])->save();

    $entity = EntityTest::create([
      'field_icon' => ['target_id' => 'test_settings:foo', 'settings' => $item_settings],
    ]);
    $build = $entity->get('field_icon')->view([
      'type' => 'icon_formatter',
      'settings' => ['icon_settings' => ['test_settings' => ['width' => 32]]],
    ]);

    $this->assertSame($expected, $build[0]['#settings']);
  }

  /**
   * Data provider for ::testViewElementsSettings().
   */
  public static function providerViewElementsSettings(): array {
    return [
      'item settings win' => [TRUE, ['test_settings' => ['width' => 64]], ['width' => 64]],
      'no item settings' => [TRUE, [], ['width' => 32]],
      'item settings not allowed' => [FALSE, ['test_settings' => ['width' => 64]], ['width' => 32]],
      'undeclared item settings dropped' => [TRUE, ['test_settings' => ['width' => 64, 'foo' => 'bar']], ['width' => 64]],
    ];
  }

}
