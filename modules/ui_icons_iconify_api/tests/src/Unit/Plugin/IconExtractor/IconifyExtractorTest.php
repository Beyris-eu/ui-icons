<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_iconify_api\Unit\Plugin\IconExtractor;

use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons_iconify_api\IconifyApi;
use Drupal\ui_icons_iconify_api\IconifyApiInterface;
use Drupal\ui_icons_iconify_api\Plugin\IconExtractor\IconifyExtractor;

/**
 * Tests for the Iconify extractor plugin.
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

    $this->iconifyApi = $this->createMock(IconifyApiInterface::class);

    $configuration = [
      'icon_pack_id' => 'test_icon_pack',
      'icon_pack_label' => 'Test Icon Pack',
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
      'test_icon_pack:icon-1' => $this->createTestIcon([
        'icon_id' => 'icon-1',
        'source' => IconifyApi::API_ENDPOINT . '/test-collection-1/icon-1.svg',
        'icon_pack_id' => 'test_icon_pack',
        'icon_pack_label' => 'Test Icon Pack',
      ]),
      'test_icon_pack:icon-2' => $this->createTestIcon([
        'icon_id' => 'icon-2',
        'source' => IconifyApi::API_ENDPOINT . '/test-collection-1/icon-2.svg',
        'icon_pack_id' => 'test_icon_pack',
        'icon_pack_label' => 'Test Icon Pack',
      ]),
      'test_icon_pack:icon-3' => $this->createTestIcon([
        'icon_id' => 'icon-3',
        'source' => IconifyApi::API_ENDPOINT . '/test-collection-2/icon-3.svg',
        'icon_pack_id' => 'test_icon_pack',
        'icon_pack_label' => 'Test Icon Pack',
      ]),
      'test_icon_pack:icon-4' => $this->createTestIcon([
        'icon_id' => 'icon-4',
        'source' => IconifyApi::API_ENDPOINT . '/test-collection-2/icon-4.svg',
        'icon_pack_id' => 'test_icon_pack',
        'icon_pack_label' => 'Test Icon Pack',
      ]),
    ];

    $this->assertEquals($expected_icons, $icons);
  }

  /**
   * Test the discoverIcons method.
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
   * Test the discoverIcons method.
   */
  public function testDiscoverIconsMissingCollections(): void {
    $configuration = [
      'icon_pack_id' => 'test_icon_pack',
      'icon_pack_label' => 'Test Icon Pack',
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
