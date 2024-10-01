<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons\Plugin\IconExtractor\SvgSpriteExtractor;

/**
 * Tests ui_icons svg_sprite extractor plugin.
 *
 * @group ui_icons
 */
class SvgSpriteExtractorTest extends UnitTestCase {

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionSource(): void {
    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
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
    $svgSpriteExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionSourceEmpty(): void {
    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
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
    $svgSpriteExtractorPlugin->discoverIcons();
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsExceptionRelativePath(): void {
    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
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
    $svgSpriteExtractorPlugin->discoverIcons();
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

    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'svg_sprite',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );

    $icons = $svgSpriteExtractorPlugin->discoverIcons();
    $this->assertArrayHasKey("svg_sprite:Start tag expected, '<' not found", $icons);
  }

  /**
   * Test the getIcons method.
   */
  public function testDiscoverIconsEmpty(): void {
    $iconFinder = $this->createMock(IconFinder::class);
    $iconFinder->method('getFilesFromSources')->willReturn([]);

    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'svg_sprite',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );
    $icons = $svgSpriteExtractorPlugin->discoverIcons();

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

    $svg_expected = '<title>test</title><symbol id="foo"></symbol><symbol id="bar"></symbol>';
    $svg_data = '<svg xmlns="http://www.w3.org/2000/svg">' . $svg_expected . '</svg>';
    $iconFinder->method('getFileContents')->willReturn($svg_data);

    $svgSpriteExtractorPlugin = new SvgSpriteExtractor(
      [
        'config' => ['sources' => ['foo/bar/baz.svg']],
        'definition_relative_path' => 'modules/my_module',
        'definition_absolute_path' => '/_ROOT_/web/modules/my_module',
        'icon_pack_id' => 'svg_sprite',
      ],
      'test_extractor',
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $iconFinder,
    );
    $icons = $svgSpriteExtractorPlugin->discoverIcons();

    $this->assertCount(2, $icons);
    $this->assertArrayHasKey('svg_sprite:foo', $icons);
    $this->assertArrayHasKey('svg_sprite:bar', $icons);

    $this->assertInstanceOf(IconDefinitionInterface::class, $icons['svg_sprite:foo']);
    $this->assertInstanceOf(IconDefinitionInterface::class, $icons['svg_sprite:bar']);

    $this->assertSame('foo', $icons['svg_sprite:foo']->getIconId());
    $this->assertSame('web/modules/my_module/foo/bar/baz.svg', $icons['svg_sprite:foo']->getSource());
  }

}
