<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\Exception\IconDefinitionInvalidDataException;
use Drupal\ui_icons\IconDefinitionInterface;

/**
 * Tests IconDefinition class used by extractor plugin.
 *
 * @group ui_icons
 */
class IconDefinitionTest extends IconUnitTestCase {

  /**
   * Test the getRenderable method.
   */
  public function testGetRenderable(): void {
    $icon = self::createIcon([
      'icon_id' => 'test_icon_pack:test',
      'source' => '/foo/bar',
      'icon_pack_id' => 'test_icon_pack',
      'icon_pack_label' => 'Baz',
      'template' => 'test_template',
      'library' => 'test_library',
      'content' => 'test_content',
      'group' => 'test_group',
    ]);

    $expected = [
      '#type' => 'inline_template',
      '#template' => 'test_template',
      '#attached' => ['library' => ['test_library']],
      '#context' => [
        'icon_id' => 'test_icon_pack:test',
        'source' => '/foo/bar',
        'content' => 'test_content',
        'icon_pack_label' => 'Baz',
        'foo' => 'bar',
        'icon_label' => 'Test icon pack:test',
        'icon_full_id' => 'test_icon_pack:test_icon_pack:test',
      ],
    ];

    $actual = $icon->getRenderable(['foo' => 'bar']);

    $this->assertEquals($expected, $actual);
  }

  /**
   * Test the create method.
   *
   * @param array $icon_data
   *   The icon data.
   *
   * @dataProvider providerCreateIcon
   */
  public function testCreateIcon(array $icon_data): void {
    $actual = self::createIcon($icon_data);

    $this->assertInstanceOf(IconDefinitionInterface::class, $actual);

    $this->assertEquals($icon_data['icon_pack_id'] . ':' . $icon_data['icon_id'], $actual->getId());
    $this->assertEquals($icon_data['icon_pack_id'], $actual->getIconPackId());
    $this->assertEquals($icon_data['icon_pack_label'] ?? '', $actual->getIconPackLabel());
    $this->assertEquals($icon_data['content'], $actual->getContent());
    $this->assertEquals($icon_data['icon_id'], $actual->getIconId());
    $this->assertEquals($icon_data['source'], $actual->getSource());
    $this->assertEquals($icon_data['group'], $actual->getGroup());
  }

  /**
   * Provides data for testCreateIcon.
   *
   * @return array
   *   Provide test data as icon data.
   */
  public static function providerCreateIcon(): array {
    return [
      [
        [
          'icon_id' => 'foo',
          'source' => 'foo/bar',
          'icon_pack_id' => 'baz',
          'content' => NULL,
          'group' => NULL,
        ],
      ],
      [
        [
          'icon_id' => 'foo',
          'source' => 'foo/bar',
          'icon_pack_id' => 'baz',
          'icon_pack_label' => 'Qux',
          'content' => 'corge',
          'group' => 'quux',
        ],
      ],
    ];
  }

  /**
   * Test the create method with errors.
   */
  public function testCreateIconError(): void {
    $this->expectException(IconDefinitionInvalidDataException::class);
    $this->expectExceptionMessage('Empty icon_id provided. Empty source provided. Missing Icon Pack Id in data.');

    self::createIcon([
      'icon_id' => '',
      'source' => '',
      'data' => [],
      'group' => NULL,
    ]);
  }

}
