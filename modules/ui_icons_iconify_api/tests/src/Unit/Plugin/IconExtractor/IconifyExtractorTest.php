<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_iconify_api\Unit\Plugin\IconExtractor;

use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons_iconify_api\Plugin\IconExtractor\IconifyExtractor;

/**
 * @coversDefaultClass \Drupal\ui_icons_iconify_api\Plugin\IconExtractor\IconifyExtractor
 *
 * @group ui_icons
 */
class IconifyExtractorTest extends IconUnitTestCase {

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
      'id' => 'foo',
      'template' => '_bar_',
      'config' => [
        'collections' => ['test-collection-1', 'test-collection-2'],
      ],
    ];
    $plugin_id = 'iconify';
    $plugin_definition = [];

    $this->iconifyExtractor = new IconifyExtractor(
      $configuration,
      $plugin_id,
      $plugin_definition,
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

    $expected_icons = [
      IconDefinition::create(
        'foo',
        'icon-1',
        '_bar_',
        'https://api.iconify.design/test-collection-1/icon-1.svg',
        NULL,
        [
          'id' => 'foo',
          'template' => '_bar_',
        ],
      ),
      IconDefinition::create(
        'foo',
        'icon-2',
        '_bar_',
        'https://api.iconify.design/test-collection-1/icon-2.svg',
        NULL,
        [
          'id' => 'foo',
          'template' => '_bar_',
        ],
      ),
      IconDefinition::create(
        'foo',
        'icon-3',
        '_bar_',
        'https://api.iconify.design/test-collection-2/icon-3.svg',
        NULL,
        [
          'id' => 'foo',
          'template' => '_bar_',
        ],
      ),
      IconDefinition::create(
        'foo',
        'icon-4',
        '_bar_',
        'https://api.iconify.design/test-collection-2/icon-4.svg',
        NULL,
        [
          'id' => 'foo',
          'template' => '_bar_',
        ],
      ),
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
