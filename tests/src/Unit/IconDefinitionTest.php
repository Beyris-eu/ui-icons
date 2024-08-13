<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\Exception\IconDefinitionInvalidDataException;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconDefinitionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests IconDefinition class used by extractor plugin.
 *
 * @group ui_icons
 */
class IconDefinitionTest extends TestCase {

  /**
   * Test the getRenderable method.
   */
  public function testGetRenderable(): void {
    $icon = IconDefinition::create(
      'test_icon_pack:test',
      '/foo/bar',
      [
        'icon_pack_id' => 'test_icon_pack',
        'icon_pack_label' => 'Baz',
        'template' => 'test_template',
        'library' => 'test_library',
        'content' => 'test_content',
      ],
      'test_group',
    );

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
    $actual = IconDefinition::create(
      $icon_data['name'] ?? '',
      $icon_data['source'] ?? '',
      $icon_data['data'] ?? [],
      $icon_data['group'] ?? NULL,
    );

    $this->assertInstanceOf(IconDefinitionInterface::class, $actual);

    $this->assertEquals($icon_data['data']['icon_pack_id'] . ':' . $icon_data['name'], $actual->getId());
    $this->assertEquals($icon_data['data']['icon_pack_id'], $actual->getIconPackId());
    $this->assertEquals($icon_data['data']['icon_pack_label'] ?? '', $actual->getIconPackLabel());
    $this->assertEquals($icon_data['data']['content'], $actual->getContent());
    $this->assertEquals($icon_data['name'], $actual->getName());
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
          'name' => 'foo',
          'source' => 'foo/bar',
          'data' => [
            'icon_pack_id' => 'baz',
            'content' => NULL,
          ],
          'group' => NULL,
        ],
      ],
      [
        [
          'name' => 'foo',
          'source' => 'foo/bar',
          'data' => [
            'icon_pack_id' => 'baz',
            'icon_pack_label' => 'Qux',
            'content' => 'corge',
          ],
          'group' => 'quux',
        ],
      ],
    ];
  }

  /**
   * Test the create method.
   *
   * @param array $icon_data
   *   The icon data.
   * @param array $errors
   *   The error messages expected.
   *
   * @dataProvider providerCreateIconError
   */
  public function testCreateIconError(array $icon_data, array $errors): void {
    $this->expectException(IconDefinitionInvalidDataException::class);
    $this->expectExceptionMessage(implode('. ', $errors));

    IconDefinition::create(
      $icon_data['name'] ?? '',
      $icon_data['source'] ?? '',
      $icon_data['data'] ?? [],
      $icon_data['group'] ?? NULL,
    );
  }

  /**
   * Provides data for testCreateIconError.
   *
   * @return array
   *   Provide test data as icon data.
   */
  public static function providerCreateIconError(): array {
    return [
      [
        [
          'name' => '',
          'source' => '',
          'data' => [],
        ],
        [
          'Empty name provided',
          'Empty source provided',
          'Missing Icon Pack Id in data',
        ],
      ],
    ];
  }

}
