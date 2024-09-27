<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\PathExtractor;

/**
 * Tests ui_icons path extractor plugin.
 *
 * @group ui_icons
 */
class PathExtractorTest extends UnitTestCase {

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionSource(): void {
    $pathExtractorPlugin = new PathExtractor(
      [],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $this->createMock(IconFinder::class),
    );
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_extractor require this value.');
    $pathExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionSourceEmpty(): void {
    $pathExtractorPlugin = new PathExtractor(
      [
        'config' => ['sources' => []],
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $this->createMock(IconFinder::class),
    );
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_extractor require this value.');
    $pathExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionRelativePath(): void {
    $pathExtractorPlugin = new PathExtractor(
      [
        'config' => ['sources' => ['foo/bar']],
        'definition_relative_path' => '',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $this->createMock(IconFinder::class),
    );
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Empty relative path for extractor test_extractor.');
    $pathExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIcons(): void {
    $icons_list = [
      'baz' => [
        'name' => 'baz',
        'icon_id' => 'baz',
        'source' => 'web/modules/my_module/foo/bar/baz.svg',
        'absolute_path' => '/_ROOT_/web/modules/my_module/foo/bar/baz.svg',
        'group' => NULL,
      ],
    ];

    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn($icons_list);

    $pathExtractorPlugin = new PathExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'path',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );
    $icons = $pathExtractorPlugin->discoverIcons();

    $this->assertIsArray($icons);
    $this->assertArrayHasKey('path:baz', $icons);

    $this->assertInstanceOf(IconDefinitionInterface::class, $icons['path:baz']);
  }

}
