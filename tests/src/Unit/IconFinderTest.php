<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\IconFinder;

/**
 * Tests IconFinder class.
 *
 * @group ui_icons
 */
class IconFinderTest extends UnitTestCase {

  private const TEST_ICONS_PATH = 'modules/custom/ui_icons/tests/modules/ui_icons_test';

  /**
   * The IconFinder instance.
   *
   * @var \Drupal\ui_icons\IconFinder
   */
  private IconFinder $iconFinder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->iconFinder = new IconFinder(
      $this->createMock(FileUrlGeneratorInterface::class),
      DRUPAL_ROOT,
    );
  }

  /**
   * Test the getFileFromHttpUrl method.
   *
   * @param string $url
   *   The url to test.
   * @param string|null $expected_source
   *   The source expected.
   * @param string|null $expected_icon_id
   *   The expected icon id.
   *
   * @dataProvider providerGetFileFromHttpUrl
   */
  public function testGetFileFromHttpUrl(string $url, ?string $expected_source = NULL, ?string $expected_icon_id = NULL): void {
    $result = $this->iconFinder->getFilesFromSources([$url], '');

    if ($expected_icon_id) {
      $expected = [
        $expected_icon_id => [
          'icon_id' => $expected_icon_id,
          'source' => $expected_source,
          'absolute_path' => $expected_source,
        ],
      ];
      $this->assertEquals($expected, $result);
    }
    else {
      $this->assertEmpty($result);
    }
  }

  /**
   * Provider for the testGetFileFromHttpUrl method.
   *
   * @return array
   *   The data to test.
   */
  public static function providerGetFileFromHttpUrl(): array {
    return [
      'url http' => [
        'http://example.com/icons/icon.svg',
        'http://example.com/icons/icon.svg',
        'icon',
      ],
      'url https' => [
        'https://example.com/icons/icon.svg',
        'https://example.com/icons/icon.svg',
        'icon',
      ],
      'url encoded with params' => [
        'https://example.com/fOO%20folder%20%C3%B9/FoO%21%20BaR%3D%281%29%20iCo-n.svg%3Ftest%3DfOO%23bAz',
        'https://example.com/fOO folder ù/FoO! BaR=(1) iCo-n.svg?test=fOO#bAz',
        'foo_bar_1_ico-n',
      ],
      'url not encoded with params' => [
        'https://example.com/fOO folder ù/FoO! BaR=(1) iCo-n.svg?test=fOO#bAz',
        'https://example.com/fOO folder ù/FoO! BaR=(1) iCo-n.svg?test=fOO#bAz',
        'foo_bar_1_ico-n',
      ],
      'relative path' => ['path/to/icon.svg'],
      'absolute path' => ['/path/to/icon.svg'],
    ];
  }

  /**
   * Test the getFilesFromPath method.
   *
   * @param string $path
   *   The path to test.
   * @param array $expected_icon
   *   The expected icon as id => path.
   *
   * @dataProvider providerGetFilesFromPath
   */
  public function testGetFilesFromPath(string $path, array $expected_icon = []): void {
    $results = $this->iconFinder->getFilesFromSources([$path], self::TEST_ICONS_PATH);

    $expected = [];
    foreach ($expected_icon as $icon_id => $data) {
      $expected[$icon_id] = [
        'icon_id' => $icon_id,
        // @todo test the source value.
        // 'source' => self::TEST_ICONS_PATH . '/' . $filename,
        'source' => '',
        'absolute_path' => DRUPAL_ROOT . '/' . self::TEST_ICONS_PATH . '/' . $data[0],
        'group' => $data[1] ?? '',
      ];
    }

    $this->assertEquals($expected, $results);
  }

  /**
   * Provider for the testGetFilesFromPath method.
   *
   * @return array
   *   The data to test.
   */
  public static function providerGetFilesFromPath(): array {
    return [
      'file name' => [
        'icons/flat/foo-1.svg',
        ['foo-1' => ['icons/flat/foo-1.svg']],
      ],
      'file extension wildcard' => [
        'icons/flat/foo-1.*',
        [
          'foo-1' => ['icons/flat/foo-1.svg'],
        ],
      ],
      'files wildcard' => [
        'icons/flat/*',
        [
          'foo-1' => ['icons/flat/foo-1.svg'],
          'foo' => ['icons/flat/foo.png'],
          'bar' => ['icons/flat/bar.png'],
          'bar-2' => ['icons/flat/bar-2.svg'],
          'baz-1' => ['icons/flat/baz-1.png'],
          'baz-2' => ['icons/flat/baz-2.svg'],
        ],
      ],
      'files wildcard increment name' => [
        'icons/flat_same_name/*',
        [
          'foo' => ['icons/flat_same_name/foo.gif'],
          'foo__1' => ['icons/flat_same_name/foo.png'],
          'foo__2' => ['icons/flat_same_name/foo.svg'],

        ],
      ],
      'files wildcard name' => [
        'icons/flat/*.svg',
        [
          'foo-1' => ['icons/flat/foo-1.svg'],
          'bar-2' => ['icons/flat/bar-2.svg'],
          'baz-2' => ['icons/flat/baz-2.svg'],
        ],
      ],
      'files multiple extension' => [
        'icons/flat/*.{svg, png}',
        [
          'foo-1' => ['icons/flat/foo-1.svg'],
          'bar-2' => ['icons/flat/bar-2.svg'],
          'baz-2' => ['icons/flat/baz-2.svg'],
          'foo' => ['icons/flat/foo.png'],
          'bar' => ['icons/flat/bar.png'],
          'baz-1' => ['icons/flat/baz-1.png'],
        ],
      ],
      'test path wildcard' => [
        '*/flat/*',
        [
          'foo-1' => ['icons/flat/foo-1.svg'],
          'foo' => ['icons/flat/foo.png'],
          'bar' => ['icons/flat/bar.png'],
          'bar-2' => ['icons/flat/bar-2.svg'],
          'baz-1' => ['icons/flat/baz-1.png'],
          'baz-2' => ['icons/flat/baz-2.svg'],
        ],
      ],
      'test group no result' => [
        'icons/group/*',
        [],
      ],
      'test group wildcard' => [
        'icons/group/*/*',
        [
          'foo_group_1' => ['icons/group/group_1/foo_group_1.svg'],
          'bar_group_1' => ['icons/group/group_1/bar_group_1.png'],
          'baz_group_1' => ['icons/group/group_1/baz_group_1.png'],
          'corge_group_1' => ['icons/group/group_1/corge_group_1.svg'],
          'foo_group_2' => ['icons/group/group_2/foo_group_2.svg'],
          'bar_group_2' => ['icons/group/group_2/bar_group_2.png'],
          'baz_group_2' => ['icons/group/group_2/baz_group_2.png'],
          'corge_group_2' => ['icons/group/group_2/corge_group_2.svg'],
        ],
      ],
      'test sub group wildcard' => [
        'icons/group/*/sub_sub_group_1/*',
        [
          'foo_sub_group_1' => ['icons/group/sub_group_1/sub_sub_group_1/foo_sub_group_1.png'],
          'bar_sub_group_1' => ['icons/group/sub_group_1/sub_sub_group_1/bar_sub_group_1.svg'],
        ],
      ],
      'test sub group wildcard name' => [
        'icons/group/*/sub_sub_group_*/*',
        [
          'foo_sub_group_1' => ['icons/group/sub_group_1/sub_sub_group_1/foo_sub_group_1.png'],
          'bar_sub_group_1' => ['icons/group/sub_group_1/sub_sub_group_1/bar_sub_group_1.svg'],
          'baz_sub_group_2' => ['icons/group/sub_group_2/sub_sub_group_2/baz_sub_group_2.svg'],
          'corge_sub_group_2' => ['icons/group/sub_group_2/sub_sub_group_2/corge_sub_group_2.png'],
        ],
      ],
      'test sub group multiple wildcard' => [
        'icons/group/*/*/*',
        [
          'foo_sub_group_1' => ['icons/group/sub_group_1/sub_sub_group_1/foo_sub_group_1.png'],
          'bar_sub_group_1' => ['icons/group/sub_group_1/sub_sub_group_1/bar_sub_group_1.svg'],
          'baz_sub_group_2' => ['icons/group/sub_group_2/sub_sub_group_2/baz_sub_group_2.svg'],
          'corge_sub_group_2' => ['icons/group/sub_group_2/sub_sub_group_2/corge_sub_group_2.png'],
        ],
      ],
      // Test a name with special characters and spaces.
      'test special chars' => [
        'icons/name_special_chars/*',
        [
          'foo_1_2_3_b_a_r' => ['icons/name_special_chars/FoO !?1:èç 2 "#3 B*;**a,ù$R|~¹&{[].svg'],
        ],
      ],
      // Start tests for the {group} placeholder.
      'test group extracted' => [
        'icons/group/{group}/*',
        [
          'foo_group_1' => [
            'icons/group/group_1/foo_group_1.svg',
            'group_1',
          ],
          'bar_group_1' => [
            'icons/group/group_1/bar_group_1.png',
            'group_1',
          ],
          'baz_group_1' => [
            'icons/group/group_1/baz_group_1.png',
            'group_1',
          ],
          'corge_group_1' => [
            'icons/group/group_1/corge_group_1.svg',
            'group_1',
          ],
          'foo_group_2' => [
            'icons/group/group_2/foo_group_2.svg',
            'group_2',
          ],
          'bar_group_2' => [
            'icons/group/group_2/bar_group_2.png',
            'group_2',
          ],
          'baz_group_2' => [
            'icons/group/group_2/baz_group_2.png',
            'group_2',
          ],
          'corge_group_2' => [
            'icons/group/group_2/corge_group_2.svg',
            'group_2',
          ],
        ],
      ],
      'test group extracted wildcard after' => [
        'icons/group/{group}/*/*',
        [
          'foo_sub_group_1' => [
            'icons/group/sub_group_1/sub_sub_group_1/foo_sub_group_1.png',
            'sub_group_1',
          ],
          'bar_sub_group_1' => [
            'icons/group/sub_group_1/sub_sub_group_1/bar_sub_group_1.svg',
            'sub_group_1',
          ],
          'baz_sub_group_2' => [
            'icons/group/sub_group_2/sub_sub_group_2/baz_sub_group_2.svg',
            'sub_group_2',
          ],
          'corge_sub_group_2' => [
            'icons/group/sub_group_2/sub_sub_group_2/corge_sub_group_2.png',
            'sub_group_2',
          ],
        ],
      ],
      'test group extracted wildcard before' => [
        'icons/group/*/{group}/*',
        [
          'foo_sub_group_1' => [
            'icons/group/sub_group_1/sub_sub_group_1/foo_sub_group_1.png',
            'sub_sub_group_1',
          ],
          'bar_sub_group_1' => [
            'icons/group/sub_group_1/sub_sub_group_1/bar_sub_group_1.svg',
            'sub_sub_group_1',
          ],
          'baz_sub_group_2' => [
            'icons/group/sub_group_2/sub_sub_group_2/baz_sub_group_2.svg',
            'sub_sub_group_2',
          ],
          'corge_sub_group_2' => [
            'icons/group/sub_group_2/sub_sub_group_2/corge_sub_group_2.png',
            'sub_sub_group_2',
          ],
        ],
      ],
      'test group extracted wildcard both' => [
        'icons/*/{group}/*/*',
        [
          'foo_sub_group_1' => [
            'icons/group/sub_group_1/sub_sub_group_1/foo_sub_group_1.png',
            'sub_group_1',
          ],
          'bar_sub_group_1' => [
            'icons/group/sub_group_1/sub_sub_group_1/bar_sub_group_1.svg',
            'sub_group_1',
          ],
          'baz_sub_group_2' => [
            'icons/group/sub_group_2/sub_sub_group_2/baz_sub_group_2.svg',
            'sub_group_2',
          ],
          'corge_sub_group_2' => [
            'icons/group/sub_group_2/sub_sub_group_2/corge_sub_group_2.png',
            'sub_group_2',
          ],
        ],
      ],
      'test group same name' => [
        'icons/group_same_name/{group}/*',
        [
          'foo' => [
            'icons/group_same_name/group_1/foo.gif',
            'group_1',
          ],
          'foo__1' => [
            'icons/group_same_name/group_2/foo.gif',
            'group_2',
          ],
          'foo__2' => [
            'icons/group_same_name/group_3/foo.gif',
            'group_3',
          ],
          'foo__3' => [
            'icons/group_same_name/group_1/foo.png',
            'group_1',
          ],
          'foo__4' => [
            'icons/group_same_name/group_2/foo.png',
            'group_2',
          ],
          'foo__5' => [
            'icons/group_same_name/group_3/foo.png',
            'group_3',
          ],
          'foo__6' => [
            'icons/group_same_name/group_1/foo.svg',
            'group_1',
          ],
          'foo__7' => [
            'icons/group_same_name/group_2/foo.svg',
            'group_2',
          ],
          'foo__8' => [
            'icons/group_same_name/group_3/foo.svg',
            'group_3',
          ],
        ],
      ],
      // Start tests for the {icon_id} placeholder.
      'test icon_id extracted' => [
        'icons/prefix_suffix/{icon_id}.svg',
        [
          'foo' => ['icons/prefix_suffix/foo.svg'],
          'foo_suffix' => ['icons/prefix_suffix/foo_suffix.svg'],
          'prefix_foo' => ['icons/prefix_suffix/prefix_foo.svg'],
          'prefix_foo_suffix' => ['icons/prefix_suffix/prefix_foo_suffix.svg'],
        ],
      ],
      'test icon_id extracted prefix' => [
        'icons/prefix_suffix/prefix_{icon_id}.svg',
        [
          'foo' => ['icons/prefix_suffix/prefix_foo.svg'],
          'foo_suffix' => ['icons/prefix_suffix/prefix_foo_suffix.svg'],
        ],
      ],
      'test icon_id extracted suffix' => [
        'icons/prefix_suffix/{icon_id}_suffix.svg',
        [
          'foo' => ['icons/prefix_suffix/foo_suffix.svg'],
          'prefix_foo' => ['icons/prefix_suffix/prefix_foo_suffix.svg'],
        ],
      ],
      'test icon_id extracted both' => [
        'icons/prefix_suffix/prefix_{icon_id}_suffix.svg',
        [
          'foo' => ['icons/prefix_suffix/prefix_foo_suffix.svg'],
        ],
      ],
      'test icon_id extracted with group' => [
        'icons/prefix_suffix/{group}/{icon_id}.svg',
        [
          'foo_group' => ['icons/prefix_suffix/group/foo_group.svg', 'group'],
          'foo_group_suffix' => ['icons/prefix_suffix/group/foo_group_suffix.svg', 'group'],
          'prefix_foo_group' => ['icons/prefix_suffix/group/prefix_foo_group.svg', 'group'],
          'prefix_foo_group_suffix' => ['icons/prefix_suffix/group/prefix_foo_group_suffix.svg', 'group'],
        ],
      ],
      'test icon_id extracted with group and wildcard' => [
        'icons/*/{group}/{icon_id}.svg',
        [
          'foo_group' => [
            'icons/prefix_suffix/group/foo_group.svg',
            'group',
          ],
          'foo_group_suffix' => [
            'icons/prefix_suffix/group/foo_group_suffix.svg',
            'group',
          ],
          'prefix_foo_group' => [
            'icons/prefix_suffix/group/prefix_foo_group.svg',
            'group',
          ],
          'prefix_foo_group_suffix' => [
            'icons/prefix_suffix/group/prefix_foo_group_suffix.svg',
            'group',
          ],
          'foo_group_1' => [
            'icons/group/group_1/foo_group_1.svg',
            'group_1',
          ],
          'corge_group_1' => [
            'icons/group/group_1/corge_group_1.svg',
            'group_1',
          ],
          'foo_group_2' => [
            'icons/group/group_2/foo_group_2.svg',
            'group_2',
          ],
          'corge_group_2' => [
            'icons/group/group_2/corge_group_2.svg',
            'group_2',
          ],
          'foo' => [
            'icons/group_same_name/group_1/foo.svg',
            'group_1',
          ],
          'foo__1' => [
            'icons/group_same_name/group_2/foo.svg',
            'group_2',
          ],
          'foo__2' => [
            'icons/group_same_name/group_3/foo.svg',
            'group_3',
          ],
        ],
      ],
      'test icon_id extracted all extension, increment name' => [
        'icons/flat_same_name/f{icon_id}o.*',
        [
          'o' => ['icons/flat_same_name/foo.gif'],
          'o__1' => ['icons/flat_same_name/foo.png'],
          'o__2' => ['icons/flat_same_name/foo.svg'],
        ],
      ],
    ];
  }

  /**
   * Test the extractIconIdFromFilename method.
   *
   * @param string $filename
   *   The filename found to match against.
   * @param string $filename_pattern
   *   The path with {icon_id}.
   * @param string|null $expected
   *   The expected result.
   *
   * @dataProvider providerTestExtractIconIdFromFilename
   */
  public function testExtractIconIdFromFilename(string $filename, string $filename_pattern, ?string $expected): void {
    $method = new \ReflectionMethod(IconFinder::class, 'extractIconIdFromFilename');
    $method->setAccessible(TRUE);

    $this->assertEquals($expected, $method->invoke($this->iconFinder, $filename, $filename_pattern));
  }

  /**
   * Provider for the testExtractIconIdFromFilename method.
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestExtractIconIdFromFilename(): array {
    return [
      'test filename' => [
        'icon',
        '{icon_id}',
        'icon',
      ],
      'test filename prefix' => [
        'prefix-icon',
        'prefix-{icon_id}',
        'icon',
      ],
      'test filename suffix' => [
        'icon-suffix',
        '{icon_id}-suffix',
        'icon',
      ],
      'test filename both' => [
        'prefix-icon-suffix',
        'prefix-{icon_id}-suffix',
        'icon',
      ],
      'test no id' => [
        'foo-icon-bar',
        '',
        NULL,
      ],
      'test no icon_id pattern' => [
        'foo-icon-bar',
        'foo bar',
        NULL,
      ],
    ];
  }

}
