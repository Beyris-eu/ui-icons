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
      $this->createMock(FileUrlGeneratorInterface::class)
    );
  }

  /**
   * Test the getFileFromHttpUrl method.
   *
   * @param string $url
   *   The url to test.
   * @param bool $expected_result
   *   The expected result.
   * @param string $expected_icon_id
   *   The expected icon id.
   *
   * @dataProvider providerGetFileFromHttpUrl
   */
  public function testGetFileFromHttpUrl(string $url, bool $expected_result, string $expected_icon_id = ''): void {
    $result = $this->iconFinder->getFilesFromSource($url, '', '', '');

    if ($expected_result) {
      $this->assertArrayHasKey('icon', $result);
      $this->assertEquals($expected_icon_id, $result['icon']['icon_id']);
      $this->assertEquals($url, $result['icon']['absolute_path']);
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
      ['http://example.com/icons/icon.svg', TRUE, 'icon'],
      ['https://example.com/icons/icon.svg', TRUE, 'icon'],
      ['path/to/icon.svg', FALSE],
      ['/path/to/icon.svg', FALSE],
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
  public function testGetFilesFromPath(
    string $path,
    array $expected_icon = [],
  ) {
    $definition_absolute_path = DRUPAL_ROOT . '/' . self::TEST_ICONS_PATH;
    $results = $this->iconFinder->getFilesFromSource($path, DRUPAL_ROOT, $definition_absolute_path, self::TEST_ICONS_PATH);

    $expected = [];
    foreach ($expected_icon as $icon_id => $data) {
      $expected[$icon_id] = self::createIcon($icon_id, $data[0], $data[1] ?? '');
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
          'foo' => ['icons/flat_same_name/foo.svg'],
          'foo__1' => ['icons/flat_same_name/foo.png'],
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
          'foo_1_2_3_b_a_r_' => ['icons/name_special_chars/FoO !?1:èç 2 "#3 B*;**a,ù$R|~¹&{[].svg'],
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
        ],
      ],
    ];
  }

  /**
   * Test the determineIconId method.
   *
   * @param string $mask
   *   The path with {icon_id}.
   * @param string $filename
   *   The filename found to match against.
   * @param string|null $expected
   *   The expected result.
   *
   * @dataProvider providerTestDetermineIconId
   */
  public function testDetermineIconId(string $mask, string $filename, ?string $expected): void {
    $method = new \ReflectionMethod(IconFinder::class, 'determineIconId');
    $method->setAccessible(TRUE);

    $this->assertEquals($expected, $method->invoke($this->iconFinder, $mask, $filename));
  }

  /**
   * Provider for the testDetermineIconId method.
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestDetermineIconId(): array {
    return [
      'test filename' => [
        '{icon_id}.svg',
        'icon.svg',
        'icon',
      ],
      'test filename prefix' => [
        'prefix-{icon_id}.svg',
        'prefix-icon.svg',
        'icon',
      ],
      'test filename suffix' => [
        '{icon_id}-suffix.svg',
        'icon-suffix.svg',
        'icon',
      ],
      'test filename both' => [
        'prefix-{icon_id}-suffix.svg',
        'prefix-icon-suffix.svg',
        'icon',
      ],
      'test no id' => [
        '',
        'foo-icon-bar.svg',
        NULL,
      ],
      'test no id name' => [
        'foo bar',
        'foo-icon-bar.svg',
        NULL,
      ],
    ];
  }

  /**
   * Test the determineGroupPosition method.
   *
   * @param string $path
   *   The path to test.
   * @param bool $is_absolute
   *   The file source is absolute, ie: relative to Drupal core.
   * @param string $definition_relative_path
   *   The definition file relative path.
   * @param int|null $expected_position
   *   The expected position.
   *
   * @dataProvider providerTestDetermineGroupPosition
   */
  public function testDetermineGroupPosition(string $path, bool $is_absolute, string $definition_relative_path, ?int $expected_position): void {
    $method = new \ReflectionMethod(IconFinder::class, 'determineGroupPosition');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->iconFinder, ['dirname' => $path], $is_absolute, $definition_relative_path);
    $this->assertEquals($expected_position, $result);
  }

  /**
   * Provider for the testDetermineGroupPosition method.
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestDetermineGroupPosition(): array {
    return [
      'test absolute path' => [
        '/path/to/{group}/icon.svg',
        TRUE,
        '',
        2,
      ],
      'test relative path' => [
        'path/to/{group}/icon.svg',
        FALSE,
        '/foo/bar/',
        5,
      ],
      'test no group' => [
        '/path/to/icon.svg',
        TRUE,
        '',
        NULL,
      ],
      'test relative path no group' => [
        'path/to/some/icon.svg',
        FALSE,
        '/foo/bar/',
        NULL,
      ],
    ];
  }

  /**
   * Create an Icon definition array.
   *
   * @param string $id
   *   The icon id.
   * @param string $filename
   *   The icon filename.
   * @param string $group
   *   The icon group (optional).
   *
   * @return array
   *   The icon definition array as in IconFinder.
   */
  private static function createIcon(string $id, string $filename, string $group = '') {
    return [
      'icon_id' => $id,
      'relative_path' => '',
      'absolute_path' => DRUPAL_ROOT . '/' . self::TEST_ICONS_PATH . '/' . $filename,
      'group' => $group,
    ];
  }

}
