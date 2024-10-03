<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\PathExtractor;

/**
 * @coversDefaultClass \Drupal\ui_icons\Plugin\IconExtractor\PathExtractor
 *
 * @group ui_icons
 */
class PathExtractorTest extends IconUnitTestCase {

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_path';

  /**
   * The PathExtractor instance.
   *
   * @var \Drupal\ui_icons\Plugin\IconExtractor\PathExtractor
   */
  private PathExtractor $pathExtractorPlugin;

  /**
   * The IconFinder instance.
   *
   * @var \Drupal\ui_icons\IconFinder|\PHPUnit\Framework\MockObject\MockObject
   */
  private IconFinder $iconFinder;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();
    $this->iconFinder = $this->createMock(IconFinder::class);
    $this->pathExtractorPlugin = new PathExtractor(
      [
        'id' => $this->pluginId,
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'template' => '_foo_',
        'relative_path' => 'modules/my_module',

      ],
      $this->pluginId,
      [],
      $this->iconFinder,
    );
  }

  /**
   * Data provider for ::testDiscoverIconsPath().
   *
   * @return \Generator
   *   The test cases, icons data with expected result.
   */
  public static function providerDiscoverIconsPath(): iterable {
    yield 'empty files' => [
      [],
      FALSE,
    ];

    yield 'single file' => [
      [
        [
          'icon_id' => 'foo',
          'source' => 'source/foo',
        ],
      ],
    ];

    yield 'multiple files with group' => [
      [
        [
          'icon_id' => 'foo',
          'source' => 'source/foo',
          'group' => 'baz',
        ],
        [
          'icon_id' => 'bar',
          'source' => 'source/bar',
          'group' => 'baz',
        ],
        [
          'icon_id' => 'baz',
          'source' => 'source/baz',
          'group' => 'qux',
        ],
      ],
    ];
  }

  /**
   * Test the PathExtractor::discoverIcons() method.
   *
   * @param array<array<string, string>> $icons
   *   The icons to test.
   * @param bool $expected
   *   Has icon result, default TRUE.
   *
   * @dataProvider providerDiscoverIconsPath
   */
  public function testDiscoverIconsPath(array $icons, bool $expected = TRUE): void {
    $return_list = [];
    foreach ($icons as $icon) {
      $return_list[] = $this->createIconData($icon);
    }
    $this->iconFinder->method('getFilesFromSources')->willReturn($return_list);

    $result = $this->pathExtractorPlugin->discoverIcons();
    if (FALSE === $expected) {
      $this->assertEmpty($result);
      return;
    }

    foreach ($result as $index => $icon) {
      $this->assertSame($this->pluginId . ':' . $icons[$index]['icon_id'], $icon->getId());
      $this->assertSame($icons[$index]['source'], $icon->getSource());
      $this->assertSame($icons[$index]['group'] ?? NULL, $icon->getGroup());
    }
  }

  /**
   * Test the PathExtractor::discoverIcons() method with invalid file.
   */
  public function testDiscoverIconsPathInvalid(): void {
    $this->iconFinder->method('getFilesFromSources')->willReturn([['icon' => 'foo:bar']]);
    $result = $this->pathExtractorPlugin->discoverIcons();
    $this->assertEmpty($result);
  }

}
