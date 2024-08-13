<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_field\Plugin\Field\FieldType\IconType;

/**
 * Defines a test for the IconType field-type.
 *
 * @group ui_icons
 */
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
    $schema = $this->iconType::schema($this->createMock(FieldStorageDefinitionInterface::class));

    $this->assertCount(2, $schema['columns']);
    $this->assertArrayHasKey('target_id', $schema['columns']);
    $this->assertArrayHasKey('settings', $schema['columns']);
  }

  /**
   * Test the propertyDefinitions method.
   */
  public function testPropertyDefinitions(): void {
    $properties = $this->iconType::propertyDefinitions($this->createMock(FieldStorageDefinitionInterface::class));

    $this->assertSame('string', $properties['target_id']->getDataType());
    $this->assertSame('map', $properties['settings']->getDataType());
  }

}
