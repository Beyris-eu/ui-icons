<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\ui_icons_field\Plugin\Field\FieldType\IconType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconType field validation.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconType::class)]
#[Group('ui_icons')]
class IconTypeKernelTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('state')->set('entity_test.additional_base_field_definitions', [
      'icon' => BaseFieldDefinition::create('ui_icon')->setName('icon'),
    ]);

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Data provider for ::testIconIdValidation().
   */
  public static function iconIdProvider(): array {
    return [
      'valid' => ['test_minimal:foo', 0],
      'no pack' => ['foo', 1],
      'empty icon' => ['test_minimal:', 1],
      'uppercase pack' => ['Test_Minimal:foo', 1],
      'space in pack' => ['test minimal:foo', 1],
    ];
  }

  /**
   * Tests the icon id format constraint.
   */
  #[DataProvider('iconIdProvider')]
  public function testIconIdValidation(string $icon_id, int $expected_count): void {
    $entity = EntityTest::create([
      'name' => 'sample entity',
      'icon' => ['target_id' => $icon_id],
    ]);

    $violations = $entity->validate()->getByField('icon');

    $this->assertCount($expected_count, $violations);
    if ($expected_count) {
      $this->assertSame('icon.0.target_id', $violations[0]->getPropertyPath());
    }
  }

}
