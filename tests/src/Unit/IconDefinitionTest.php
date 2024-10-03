<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

// cspell:ignore corge grault quux
use Drupal\ui_icons\Exception\IconDefinitionInvalidDataException;
use Drupal\ui_icons\IconDefinition;

/**
 * @coversDefaultClass \Drupal\ui_icons\IconDefinition
 *
 * @group ui_icons
 */
class IconDefinitionTest extends IconUnitTestCase {

  /**
   * Data provider for ::testCreateIcon().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerCreateIcon(): iterable {
    yield 'minimal icon' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
      ],
    ];

    yield 'icon with source' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'source' => 'foo/bar',
      ],
    ];

    yield 'icon with empty source' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'source' => '',
      ],
    ];

    yield 'icon with empty data' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'source' => '',
        'group' => '',
        'data' => [
          'content' => '',
        ],
      ],
    ];

    yield 'icon with null data' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'source' => NULL,
        'group' => NULL,
        'data' => [
          'content' => NULL,
        ],
      ],
    ];

    yield 'icon with data' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'source' => 'foo/bar',
        'group' => 'quux',
        'data' => [
          'content' => 'corge',
          'pack_label' => 'Qux',
        ],
      ],
    ];
  }

  /**
   * Test the createIcon method.
   *
   * @param array $data
   *   The icon data.
   *
   * @dataProvider providerCreateIcon
   */
  public function testCreateIcon(array $data): void {
    $icon_data = $data['data'] ?? NULL;

    if ($icon_data) {
      $actual = IconDefinition::create(
        $data['pack_id'],
        $data['icon_id'],
        $data['template'],
        $data['source'] ?? NULL,
        $data['group'] ?? NULL,
        $icon_data,
      );
    }
    else {
      $actual = IconDefinition::create(
        $data['pack_id'],
        $data['icon_id'],
        $data['template'],
        $data['source'] ?? NULL,
        $data['group'] ?? NULL,
      );
    }

    $icon_full_id = IconDefinition::createIconId($data['pack_id'], $data['icon_id']);

    $this->assertEquals($icon_full_id, $actual->getId());

    $this->assertEquals(ucfirst($data['icon_id']), $actual->getLabel());

    $this->assertEquals($data['icon_id'], $actual->getIconId());
    $this->assertEquals($data['pack_id'], $actual->getPackId());
    $this->assertEquals($data['template'], $actual->getTemplate());

    $this->assertEquals($data['source'] ?? NULL, $actual->getSource());
    $this->assertEquals($data['group'] ?? NULL, $actual->getGroup());

    if ($icon_data) {
      $this->assertEquals($icon_data['pack_label'] ?? NULL, $actual->getData('pack_label'));
      $this->assertEquals($icon_data['content'] ?? NULL, $actual->getData('content'));
    }
  }

  /**
   * Test the create method with errors.
   */
  public function testCreateIconError(): void {
    $this->expectException(IconDefinitionInvalidDataException::class);
    $this->expectExceptionMessage('Empty pack_id provided! Empty icon_id provided! Empty template provided!');

    IconDefinition::create('', '', '');
  }

  /**
   * Test the getRenderable method.
   */
  public function testGetRenderable(): void {
    $icon = IconDefinition::create('foo', 'bar', 'baz');

    $expected = [
      '#type' => 'ui_icon',
      '#icon_pack' => 'foo',
      '#icon' => 'bar',
      '#settings' => [
        'baz' => 'corge',
      ],
    ];

    $actual = $icon->getRenderable(['baz' => 'corge']);

    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider for ::testGetPreview().
   *
   * @return \Generator
   *   Provide test data as icon data and expected result.
   */
  public static function providerGetPreview(): iterable {
    yield 'minimal icon' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
      ],
      [],
      [
        '#icon_label' => 'Bar',
        '#icon_id' => 'bar',
        '#pack_id' => 'foo',
        '#extractor' => NULL,
        '#source' => NULL,
        '#library' => NULL,
        '#settings' => [],
      ],
    ];

    yield 'minimal icon with settings' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
      ],
      [
        'baz' => 'corge',
        0 => 1,
        'grault', // phpcs:disable
      ],
      [
        '#icon_label' => 'Bar',
        '#icon_id' => 'bar',
        '#pack_id' => 'foo',
        '#extractor' => NULL,
        '#source' => NULL,
        '#library' => NULL,
        '#settings' => [
          'baz' => 'corge',
          0 => 1,
          1 => 'grault',
        ],
      ],
    ];

    yield 'icon with data and settings' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'data' => [
          'extractor' => 'qux',
        ],
      ],
      ['baz' => 'corge'],
      [
        '#icon_label' => 'Bar',
        '#icon_id' => 'bar',
        '#pack_id' => 'foo',
        '#extractor' => 'qux',
        '#source' => NULL,
        '#library' => NULL,
        '#settings' => ['baz' => 'corge'],
      ],
    ];

    yield 'icon with data' => [
      [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
        'template' => 'baz',
        'source' => 'quux',
        'data' => [
          'extractor' => 'qux',
          'library' => 'corge',
        ],
      ],
      ['baz' => 'corge'],
      [
        '#icon_label' => 'Bar',
        '#icon_id' => 'bar',
        '#pack_id' => 'foo',
        '#extractor' => 'qux',
        '#source' => 'quux',
        '#library' => 'corge',
        '#settings' => ['baz' => 'corge'],
      ],
    ];
  }

  /**
   * Test the getPreview method.
   *
   * @param array $data
   *   The icon data.
   * @param array $settings
   *   The settings data.
   * @param array $expected
   *   The expected result.
   *
   * @dataProvider providerGetPreview
   */
  public function testGetPreview(array $data, array $settings, array $expected): void {
    $icon = IconDefinition::create(
      $data['pack_id'],
      $data['icon_id'],
      $data['template'],
      $data['source'] ?? NULL,
      $data['group'] ?? NULL,
      $data['data'] ?? NULL,
    );

    $actual = $icon->getPreview($settings);
    $expected['#theme'] = 'icon_preview';

    $this->assertEquals($expected, $actual);
  }

  /**
   * Test the getPreview method with custom `preview`.
   */
  public function testGetPreviewDefinition(): void {
    $data = [
      'pack_id' => 'foo',
      'icon_id' => 'bar',
      'template' => 'baz',
      'source' => 'quux',
      'group' => NULL,
      'preview' => '<span></span>',
    ];

    $expected = [
      '#type' => 'inline_template',
      '#template' => $data['preview'],
      '#context' => [
        'pack_id' => $data['pack_id'],
        'icon_id' => $data['icon_id'],
        'label' => 'Bar',
        'source' => $data['source'],
        'extractor' => NULL,
        'content' => NULL,
        'size' => 48,
      ],
    ];

    $icon = $this->createTestIcon($data);

    $actual = $icon->getPreview(['foo' => 'bar']);
    $this->assertEquals($expected, $actual);

    $data['library'] = 'test_library';
    $data['content'] = 'test_content';
    $expected['#attached'] = ['library' => [$data['library']]];
    $expected['#context']['content'] = $data['content'];

    $icon = $this->createTestIcon($data);
    $actual = $icon->getPreview(['foo' => 'bar']);
    $this->assertEquals($expected, $actual);

    $actual = $icon->getPreview(['foo' => 'bar', 'size' => 24]);
    $expected['#context']['size'] = 24;
    $this->assertEquals($expected, $actual);
  }

}
