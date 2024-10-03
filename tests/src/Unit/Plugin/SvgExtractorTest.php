<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\SvgExtractor;

// cspell:ignore corge

/**
 * @coversDefaultClass \Drupal\ui_icons\Plugin\IconExtractor\SvgExtractor
 *
 * @group ui_icons
 */
class SvgExtractorTest extends IconUnitTestCase {

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_svg';

  /**
   * Data provider for ::testDiscoverIconsSvg().
   *
   * @return \Generator
   *   The test cases, icons data with content map and expected content.
   */
  public static function providerDiscoverIconsSvg() {
    yield 'empty files' => [
      [],
      [],
      [],
    ];

    yield 'svg file empty' => [
      [
        [
          'icon_id' => 'foo',
          'source' => 'source/foo',
          'absolute_path' => '/path/foo.svg',
        ],
      ],
      [
        ['/path/foo.svg', FALSE],
      ],
      [],
    ];

    yield 'svg file' => [
      [
        [
          'icon_id' => 'foo',
          'source' => 'source/foo',
          'absolute_path' => '/path/foo.svg',
        ],
      ],
      [
        ['/path/foo.svg', '<svg><g><path d="M8 15a.5.5 0 0 0"/></g></svg>'],
      ],
      [
        '<g><path d="M8 15a.5.5 0 0 0"/></g>',
      ],
    ];

    yield 'svg sprite is ignored' => [
      [
        [
          'icon_id' => 'foo',
          'source' => 'source/foo',
          'absolute_path' => '/path/foo.svg',
        ],
      ],
      [
        ['/path/foo.svg', '<svg><symbol id="foo"><g><path d="M8 15a.5.5 0 0 0"/></g></symbol>/svg>'],
      ],
      [],
    ];

    yield 'multiple files with group' => [
      [
        [
          'icon_id' => 'foo',
          'source' => 'source/foo',
          'absolute_path' => '/path/foo.svg',
          'group' => 'qux',
        ],
        [
          'icon_id' => 'bar',
          'source' => 'source/bar',
          'absolute_path' => '/path/bar.svg',
          'group' => 'corge',
        ],
        [
          'icon_id' => 'empty',
          'source' => 'source/empty',
          'absolute_path' => '/path/empty.svg',
        ],
      ],
      [
        [
          '/path/foo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><g><path d="M8 15a.5.5 0 0 0"/></g></svg>',
        ],
        [
          '/path/bar.svg', '<svg data-foo="bar"><path d="M8 15a.5.5 0 0 0"/></svg>',
        ],
        // Valid but dummy content.
        [
          '/path/empty.svg', '<svg data-foo="bar"><title>Foo</title><defs><g foo="bar"><bar/></g></defs></svg>',
        ],
      ],
      [
        '<g><path d="M8 15a.5.5 0 0 0"/></g>',
        '<path d="M8 15a.5.5 0 0 0"/>',
        '<title>Foo</title><defs><g foo="bar"><bar/></g></defs>',
      ],
    ];
  }

  /**
   * Test the SvgExtractor::discoverIcons() method.
   *
   * @param array<array<string, string>> $icons
   *   The icons data to test.
   * @param array<int, array<int, mixed>> $contents_map
   *   The content returned by fileGetContents() based on absolute_path.
   * @param array<string> $expected
   *   The icons expected.
   *
   * @dataProvider providerDiscoverIconsSvg
   */
  public function testDiscoverIconsSvg(array $icons, array $contents_map, array $expected): void {
    $return_list = [];
    foreach ($icons as $icon) {
      $return_list[] = $this->createIconData($icon);
    }
    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn($return_list);

    $iconFinder->method('getFileContents')
      ->willReturnMap($contents_map);

    $svgExtractorPlugin = new SvgExtractor(
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

    foreach ($result as $index => $icon) {
      $this->assertSame($expected[$index], $icon->getData('content'));
      // Basic data are not altered and can be compared directly.
      $this->assertSame($icons[$index]['icon_id'], $icon->getIconId());
      $this->assertSame($icons[$index]['source'], $icon->getSource());
      $this->assertSame($icons[$index]['group'] ?? NULL, $icon->getGroup());
    }
  }

  /**
   * Test theSvgExtractor::discoverIcons() method with invalid svg.
   */
  public function testDiscoverIconsInvalid(): void {
    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn([
      $this->createIconData(),
    ]);

    $iconFinder->method('getFileContents')->willReturn('Not valid svg');

    $svgExtractorPlugin = new SvgExtractor(
      [
        'id' => $this->pluginId,
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'relative_path' => 'modules/my_module',
        'template' => '_foo_',
      ],
      $this->pluginId,
      [],
      $iconFinder,
    );

    $icons = $svgExtractorPlugin->discoverIcons();

    $this->assertEmpty($icons);
    foreach (libxml_get_errors() as $error) {
      $this->assertSame("Start tag expected, '<' not found", trim($error->message));
    }
  }

}
