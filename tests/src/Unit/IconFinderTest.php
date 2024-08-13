<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\ManualExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Tests IconFinder class used by extractor plugin.
 *
 * @group ui_icons
 */
class IconFinderTest extends TestCase {

  /**
   * Test method getFilesFromSources.
   *
   * @param string $source
   *   The source path.
   * @param array $files
   *   The file path, name and filename.
   * @param array $expected
   *   The result expected for all the files.
   *
   * @dataProvider providerFilesFromSource
   */
  public function testGetFilesFromSources(string $source, array $files, array $expected): void {
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileUrlGenerator->method('generateString')->willReturnCallback(fn ($arg) => $arg);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $file_objects = [];
    foreach ($files as $file) {
      $file_objects[] = new DummySplFileFinfo($file[0], $file[1], $file[2]);
    }
    $fileSystem->method('scanDirectory')->willReturn($file_objects);

    $iconFinder = new IconFinder($fileSystem, $fileUrlGenerator);
    $extractorPlugin = new ManualExtractor(
      [],
      'test_extractor',
      [],
      $iconFinder,
    );

    $paths = [
      'drupal_root' => '/_ROOT_/web',
      'absolute_path' => '/_ROOT_/web/modules/my_module',
      'relative_path' => 'modules/my_module',
    ];
    $actual = $extractorPlugin->getFilesFromSources(
      [$source], $paths
    );
    $this->assertEquals($expected, $actual);
  }

  /**
   * Provides data for testGetFilesFromSource.
   *
   * @return array
   *   Provide test data as:
   *   - source path
   *   - files with paths, name and filename
   *   - result array of files data expected
   */
  public static function providerFilesFromSource(): array {
    return [
      'no files' => [
        'foo/{icon_id}.svg',
        [],
        [],
      ],
      'file relative to definition without icon_id' => [
        'foo/BAR.svg',
        [
          [
            '/_ROOT_/web/modules/my_module/foo/BAR.svg',
            'BAR.svg',
            'BAR',
          ],
        ],
        [
          'BAR' => [
            'name' => 'BAR',
            'icon_id' => 'BAR',
            'relative_path' => 'modules/my_module/foo/BAR.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/BAR.svg',
            'group' => NULL,
          ],
        ],
      ],
      'file relative to definition' => [
        'foo/{icon_id}.svg',
        [
          [
            '/_ROOT_/web/modules/my_module/foo/BAR.svg',
            'BAR.svg',
            'BAR',
          ],
          [
            '/_ROOT_/web/modules/my_module/foo/BAZ.svg',
            'BAZ.svg',
            'BAZ',
          ],
        ],
        [
          'BAR' => [
            'name' => 'BAR',
            'icon_id' => 'BAR',
            'relative_path' => 'modules/my_module/foo/BAR.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/BAR.svg',
            'group' => NULL,
          ],
          'BAZ' => [
            'name' => 'BAZ',
            'icon_id' => 'BAZ',
            'relative_path' => 'modules/my_module/foo/BAZ.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/BAZ.svg',
            'group' => NULL,
          ],
        ],
      ],
      'file with group relative to definition' => [
        'foo/{group}/{icon_id}.svg',
        [
          [
            '/_ROOT_/web/modules/my_module/foo/GROUP/BAR.svg',
            'BAR.svg',
            'BAR',
          ],
          [
            '/_ROOT_/web/modules/my_module/foo/GROUP/BAZ.svg',
            'BAZ.svg',
            'BAZ',
          ],
        ],
        [
          'BAR' => [
            'name' => 'BAR',
            'icon_id' => 'BAR',
            'relative_path' => 'modules/my_module/foo/GROUP/BAR.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/GROUP/BAR.svg',
            'group' => 'GROUP',
          ],
          'BAZ' => [
            'name' => 'BAZ',
            'icon_id' => 'BAZ',
            'relative_path' => 'modules/my_module/foo/GROUP/BAZ.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/GROUP/BAZ.svg',
            'group' => 'GROUP',
          ],
        ],
      ],
      'file relative to drupal root' => [
        '/foo/{icon_id}.svg',
        [
          [
            '/_ROOT_/web/foo/BAR.svg',
            'BAR.svg',
            'BAR',
          ],
        ],
        [
          'BAR' => [
            'name' => 'BAR',
            'icon_id' => 'BAR',
            'relative_path' => '/foo/BAR.svg',
            'absolute_path' => '/_ROOT_/web/foo/BAR.svg',
            'group' => NULL,
          ],
        ],
      ],
      'file with group relative to drupal root' => [
        '/foo/{group}/{icon_id}.svg',
        [
          [
            '/_ROOT_/web/foo/GROUP/BAR.svg',
            'BAR.svg',
            'BAR',
          ],
        ],
        [
          'BAR' => [
            'name' => 'BAR',
            'icon_id' => 'BAR',
            'relative_path' => '/foo/GROUP/BAR.svg',
            'absolute_path' => '/_ROOT_/web/foo/GROUP/BAR.svg',
            'group' => 'GROUP',
          ],
        ],
      ],
      'file with name suffix' => [
        'foo/{icon_id}-24.svg',
        [
          [
            '/_ROOT_/web/modules/my_module/foo/bar-24.svg',
            'bar-24.svg',
            'bar-24',
          ],
        ],
        [
          'bar-24' => [
            'name' => 'bar',
            'icon_id' => 'bar',
            'relative_path' => 'modules/my_module/foo/bar-24.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/bar-24.svg',
            'group' => NULL,
          ],
        ],
      ],
      'file with group higher in parent' => [
        'foo/{group}/bar/{icon_id}.svg',
        [
          [
            '/_ROOT_/web/modules/my_module/foo/GROUP/bar/BAR.svg',
            'BAR.svg',
            'BAR',
          ],
          [
            '/_ROOT_/web/modules/my_module/foo/GROUP/bar/BAZ.svg',
            'BAZ.svg',
            'BAZ',
          ],
        ],
        [
          'BAR' => [
            'name' => 'BAR',
            'icon_id' => 'BAR',
            'relative_path' => 'modules/my_module/foo/GROUP/bar/BAR.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/GROUP/bar/BAR.svg',
            'group' => 'GROUP',
          ],
          'BAZ' => [
            'name' => 'BAZ',
            'icon_id' => 'BAZ',
            'relative_path' => 'modules/my_module/foo/GROUP/bar/BAZ.svg',
            'absolute_path' => '/_ROOT_/web/modules/my_module/foo/GROUP/bar/BAZ.svg',
            'group' => 'GROUP',
          ],
        ],
      ],
    ];
  }

  /**
   * Test method getFilesFromSources for a missing dir.
   */
  public function testGetFilesFromSourcesMissingDir(): void {
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileUrlGenerator->method('generateString')->willReturnCallback(fn ($arg) => $arg);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('scanDirectory')->will($this->throwException(new \Exception()));

    $iconFinder = new IconFinder($fileSystem, $fileUrlGenerator);
    $extractorPlugin = new ManualExtractor(
      [],
      'test_extractor',
      [],
      $iconFinder,
    );

    $paths = [
      'drupal_root' => '/_ROOT_/web',
      'absolute_path' => '/_ROOT_/web/modules/my_module',
      'relative_path' => 'modules/my_module',
    ];
    $actual = $extractorPlugin->getFilesFromSources(
      ['/foo/bar.svg'], $paths
    );

    $this->assertEquals([], $actual);
  }

  /**
   * Test method getFilesFromSources for a missing sources.
   */
  public function testGetFilesFromSourcesExceptionSources(): void {
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $iconFinder = new IconFinder($fileSystem, $fileUrlGenerator);

    $extractorPlugin = new ManualExtractor(
      [],
      'test_extractor',
      [],
      $iconFinder,
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_extractor require this value.');
    $extractorPlugin->getFilesFromSources(
      [], []
    );
  }

  /**
   * Test method getFilesFromSources for a missing path.
   */
  public function testGetFilesFromSourcesExceptionPath(): void {
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileSystem = $this->createMock(FileSystemInterface::class);

    $iconFinder = new IconFinder($fileSystem, $fileUrlGenerator);
    $extractorPlugin = new ManualExtractor(
      [],
      'test_extractor',
      [],
      $iconFinder,
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Could not retrieve paths for extractor test_extractor.');
    $extractorPlugin->getFilesFromSources(
      ['/foo/bar.svg'], []
    );
  }

}

/**
 * Dummy class to simulate SPL.
 *
 * @codeCoverageIgnore
 */
class DummySplFileFinfo {

  public function __construct(
    public string $uri,
    public string $filename,
    public string $name,
  ) {}

  /**
   * Get uri.
   */
  public function uri(): string {
    return $this->uri;
  }

  /**
   * Get name.
   */
  public function name(): string {
    return $this->name;
  }

  /**
   * Get filename.
   */
  public function filename(): string {
    return $this->filename;
  }

}
