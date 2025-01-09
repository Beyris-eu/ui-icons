<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_iconify_api\Unit\Plugin\IconExtractor;

@class_alias('Drupal\ui_icons_backport\Exception\IconPackConfigErrorException', 'Drupal\Core\Theme\Icon\Exception\IconPackConfigErrorException');
@class_alias('Drupal\ui_icons_backport\IconDefinition', 'Drupal\Core\Theme\Icon\IconDefinition');
@class_alias('Drupal\ui_icons_backport\IconExtractorBase', 'Drupal\Core\Theme\Icon\IconExtractorBase');

use Drupal\Core\Theme\Icon\Exception\IconPackConfigErrorException;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_iconify_api\Plugin\IconExtractor\IconifyExtractor;

/**
 * @coversDefaultClass \Drupal\ui_icons_iconify_api\Plugin\IconExtractor\IconifyExtractor
 *
 * @group ui_icons
 */
class IconifyExtractorTest extends UnitTestCase {

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_iconify';

  /**
   * The Iconify API service.
   *
   * @var \Drupal\ui_icons_iconify_api\IconifyApiInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $iconifyApi;

  /**
   * The Iconify extractor plugin.
   *
   * @var \Drupal\ui_icons_iconify_api\Plugin\IconExtractor\IconifyExtractor
   */
  protected $iconifyExtractor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->iconifyApi = $this->getMockBuilder('Drupal\ui_icons_iconify_api\IconifyApiInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $configuration = [
      'id' => $this->pluginId,
      'template' => '_bar_',
      'config' => [
        'collections' => ['test-collection-1', 'test-collection-2'],
      ],
    ];

    $this->iconifyExtractor = new IconifyExtractor(
      $configuration,
      $this->pluginId,
      [],
      $this->iconifyApi
    );
  }

  /**
   * Test the discoverIcons method.
   */
  public function testDiscoverIconsSuccess(): void {
    $this->iconifyApi
      ->expects($this->exactly(2))
      ->method('getIconsByCollection')
      ->willReturnCallback(function ($collection) {
        return match ($collection) {
          'test-collection-1' => ['icon-1', 'icon-2'],
          'test-collection-2' => ['icon-3', 'icon-4'],
          default => [],
        };
      });

    $icons = $this->iconifyExtractor->discoverIcons();

    $prefix = $this->pluginId . IconDefinition::ICON_SEPARATOR;
    $expected_icons = [
      $prefix . 'icon-1' => [
        'source' => 'https://api.iconify.design/test-collection-1/icon-1.svg',
      ],
      $prefix . 'icon-2' => [
        'source' => 'https://api.iconify.design/test-collection-1/icon-2.svg',
      ],
      $prefix . 'icon-3' => [
        'source' => 'https://api.iconify.design/test-collection-2/icon-3.svg',
      ],
      $prefix . 'icon-4' => [
        'source' => 'https://api.iconify.design/test-collection-2/icon-4.svg',
      ],
    ];

    $this->assertEquals($expected_icons, $icons);
  }

  /**
   * Test the discoverIcons method with empty collection.
   */
  public function testDiscoverIconsEmptyCollections(): void {
    $this->iconifyApi
      ->expects($this->exactly(2))
      ->method('getIconsByCollection')
      ->willReturn([]);

    $icons = $this->iconifyExtractor->discoverIcons();
    $this->assertEquals([], $icons);
  }

  /**
   * Test the discoverIcons method with not string.
   */
  public function testDiscoverIconsNotStringCollections(): void {
    $this->iconifyApi
      ->expects($this->exactly(2))
      ->method('getIconsByCollection')
      ->willReturn([1, 2]);

    $icons = $this->iconifyExtractor->discoverIcons();
    $this->assertEquals([], $icons);
  }

  /**
   * Test the discoverIcons method with missing collection.
   */
  public function testDiscoverIconsMissingCollections(): void {
    $configuration = [
      'id' => 'test_icon_pack',
    ];
    $plugin_id = 'iconify';
    $plugin_definition = [];

    $iconifyExtractor = new IconifyExtractor(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->iconifyApi
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: collections` in your definition, extractor iconify require this value.');
    $iconifyExtractor->discoverIcons();
  }

}
