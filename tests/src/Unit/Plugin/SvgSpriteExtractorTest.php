<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\SvgSpriteExtractor;

/**
 * @coversDefaultClass \Drupal\ui_icons\Plugin\IconExtractor\SvgSpriteExtractor
 *
 * @group ui_icons
 */
class SvgSpriteExtractorTest extends IconUnitTestCase {

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_svg_sprite';

  /**
   * Data provider for ::testDiscoverIconsSvgSprite().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerDiscoverIconsSvgSprite(): iterable {
    yield 'empty files' => [
      [],
      [],
      [],
    ];

    yield 'svg not sprite is ignored' => [
      [
        [
          'source' => 'source/baz',
          'absolute_path' => '/path/baz.svg',
        ],
      ],
      [
        ['/path/baz.svg', '<svg><path d="M8 15a.5.5 0 0 0"/></svg>'],
      ],
      [],
    ];

    yield 'svg sprite with one symbol' => [
      [
        [
          'source' => 'source/baz',
          'absolute_path' => '/path/baz.svg',
        ],
      ],
      [
        ['/path/baz.svg', '<svg><symbol id="foo"></symbol></svg>'],
      ],
      ['foo'],
    ];

    yield 'single file with multiple symbol' => [
      [
        [
          'absolute_path' => '/path/baz.svg',
          'source' => 'source/baz',
        ],
      ],
      [
        ['/path/baz.svg', '<svg><symbol id="foo"></symbol><symbol id="bar"></symbol></svg>'],
      ],
      ['foo', 'bar'],
    ];

    yield 'single file with multiple symbol in defs' => [
      [
        [
          'absolute_path' => '/path/baz.svg',
          'source' => 'source/baz',
        ],
      ],
      [
        ['/path/baz.svg', '<svg><defs><symbol id="foo"></symbol><symbol id="bar"></symbol></defs></svg>'],
      ],
      ['foo', 'bar'],
    ];
  }

  /**
   * Test the SvgSpriteExtractor::discoverIcons() method.
   *
   * @param array<array<string, string>> $icons
   *   The icons to test.
   * @param array<int, array<int, mixed>> $contents_map
   *   The content returned by fileGetContents() based on absolute_path.
   * @param array<string> $expected
   *   The icon ids expected.
   *
   * @dataProvider providerDiscoverIconsSvgSprite
   */
  public function testDiscoverIconsSvgSprite(array $icons, array $contents_map, array $expected): void {
    $return_list = [];
    foreach ($icons as $icon) {
      $return_list[] = $this->createIconData($icon);
    }
    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn($return_list);

    $iconFinder->method('getFileContents')
      ->willReturnMap($contents_map);

    $svgExtractorPlugin = new SvgSpriteExtractor(
      [
        'id' => $this->pluginId,
        'config' => ['sources' => ['foo/bar/{icon_id}.svg']],
        'template' => '_foo_',
        'relative_path' => 'modules/my_module',
      ],
      $this->pluginId,
      [],
      $iconFinder,
    );
    $result = $svgExtractorPlugin->discoverIcons();

    if (empty($expected)) {
      $this->assertEmpty($result);
      return;
    }

    foreach ($icons as $index => $expected_icon) {
      // Main test is to ensure the icon id is extracted.
      $this->assertSame($expected[$index], $result[$index]->getIconId());
    }

    // Basic data are not altered and can be compared directly.
    foreach ($result as $icon) {
      $this->assertSame($icons[$index]['source'], $icon->getSource());
      $this->assertSame($icons[$index]['group'] ?? NULL, $icon->getGroup());
    }
  }

  /**
   * Test the SvgSpriteExtractor::discoverIcons() method with invalid svg.
   */
  public function testDiscoverIconsSvgSpriteInvalid(): void {
    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn([
      $this->createIconData(),
    ]);
    $iconFinder->method('getFileContents')->willReturn('Not valid svg');

    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
      [
        'id' => $this->pluginId,
        'config' => ['sources' => ['foo/bar/{icon_id}.svg']],
        'template' => '_foo_',
        'relative_path' => 'modules/my_module',
      ],
      $this->pluginId,
      [],
      $iconFinder,
    );

    $icons = $svgSpriteExtractorPlugin->discoverIcons();
    $this->assertEmpty($icons);
    foreach (libxml_get_errors() as $error) {
      $this->assertSame("Start tag expected, '<' not found", trim($error->message));
    }
  }

}
