<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_field\Plugin\Field\FieldType\IconType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconType class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconType::class)]
#[Group('ui_icons')]
class IconTypeTest extends UnitTestCase {

  /**
   * The field type.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldType\IconType
   */
  private IconType $iconType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $definition->method('getPropertyDefinitions')->willReturn([]);

    $this->iconType = new IconType(
      $definition,
      'test',
    );
  }

  /**
   * Test the schema method.
   */
  public function testSchema(): void {
    $schema = $this->iconType::schema($this->createStub(FieldStorageDefinitionInterface::class));

    $this->assertCount(2, $schema['columns']);
    $this->assertArrayHasKey('target_id', $schema['columns']);
    $this->assertArrayHasKey('settings', $schema['columns']);
    $this->assertTrue($schema['columns']['settings']['serialize']);
  }

  /**
   * Test the propertyDefinitions method.
   */
  public function testPropertyDefinitions(): void {
    $properties = $this->iconType::propertyDefinitions($this->createStub(FieldStorageDefinitionInterface::class));

    $this->assertSame('string', $properties['target_id']->getDataType());
    $this->assertSame('map', $properties['settings']->getDataType());

    // The standard `{pack_id, icon_id, settings}` icon shape, computed from the
    // stored `target_id` and pack-keyed `settings`.
    foreach (['pack_id', 'icon_id', 'resolved_settings'] as $computed) {
      $this->assertTrue($properties[$computed]->isComputed(), "$computed is computed");
    }
    $this->assertSame('string', $properties['pack_id']->getDataType());
    $this->assertSame('string', $properties['icon_id']->getDataType());
    $this->assertSame('map', $properties['resolved_settings']->getDataType());
  }

  /**
   * Test the mainPropertyName method.
   */
  public function testMainPropertyName(): void {
    $this->assertSame('target_id', $this->iconType::mainPropertyName());
  }

}
