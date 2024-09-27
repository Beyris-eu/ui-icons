<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\SvgExtractor;

/**
 * Tests ui_icons svg extractor plugin.
 *
 * @group ui_icons
 */
class SvgExtractorTest extends UnitTestCase {

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionSource(): void {
    $svgExtractorPlugin = new SvgExtractor(
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
    $svgExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionSourceEmpty(): void {
    $svgExtractorPlugin = new SvgExtractor(
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
    $svgExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionRelativePath(): void {
    $svgExtractorPlugin = new SvgExtractor(
      [
        'config' => ['sources' => ['foo/bar']],
        'definition_relative_path' => '',
        'definition_absolute_path' => '',
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
    $svgExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsInvalid(): void {
    $iconFinder = $this->createMock(IconFinder::class);

    $icons_list = [
      'baz' => [
        'name' => 'baz',
        'icon_id' => 'baz',
        'source' => 'web/modules/my_module/foo/bar/baz.svg',
        'absolute_path' => '/_ROOT_/web/modules/my_module/foo/bar/baz.svg',
        'group' => NULL,
      ],
    ];
    $iconFinder->method('getFilesFromSources')->willReturn($icons_list);
    $svg_data = 'Not valid svg';
    $iconFinder->method('getFileContents')->willReturn($svg_data);

    $svgExtractorPlugin = new SvgExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'svg',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );

    $icons = $svgExtractorPlugin->discoverIcons();
    $this->assertSame("Start tag expected, '<' not found", trim($icons['svg:baz']->getContent()));
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsEmpty(): void {
    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn([]);

    $svgExtractorPlugin = new SvgExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'svg',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );
    $icons = $svgExtractorPlugin->discoverIcons();

    $this->assertEmpty($icons);
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIcons(): void {
    $iconFinder = $this->createMock(IconFinder::class);

    $icons_list = [
      'baz' => [
        'name' => 'baz',
        'icon_id' => 'baz',
        'source' => 'web/modules/my_module/foo/bar/baz.svg',
        'absolute_path' => '/_ROOT_/web/modules/my_module/foo/bar/baz.svg',
        'group' => NULL,
      ],
    ];
    $iconFinder->method('getFilesFromSources')->willReturn($icons_list);

    $svg_expected = '<title>test</title><g><path d="M8 15a.5.5 0 0 0"/></g>';
    $svg_data = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">' . $svg_expected . '</svg>';
    $iconFinder->method('getFileContents')->willReturn($svg_data);

    $svgExtractorPlugin = new SvgExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'svg',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );
    $icons = $svgExtractorPlugin->discoverIcons();

    $this->assertArrayHasKey('svg:baz', $icons);
    $this->assertInstanceOf(IconDefinitionInterface::class, $icons['svg:baz']);
    $this->assertSame($svg_expected, $icons['svg:baz']->getContent());
  }

}
