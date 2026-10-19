<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_library\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_icons_library\Controller\LibraryIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cached cards of the icon library index.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(LibraryIndex::class)]
#[Group('ui_icons')]
class LibraryIndexCacheTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_library',
    'ui_icons_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser();
  }

  /**
   * Tests the cards are rebuilt when the icon packs change.
   */
  public function testCardsFollowIconPacks(): void {
    LibraryIndex::create($this->container)->index();

    $cache = $this->container->get('cache.default')->get('icon_library_card');
    $this->assertNotFalse($cache);
    $this->assertContains('icon_pack_plugin', $cache->tags);
    $this->assertContains('icon_pack_collector', $cache->tags);

    $this->container->get('cache_tags.invalidator')->invalidateTags(['icon_pack_plugin']);
    $this->assertFalse($this->container->get('cache.default')->get('icon_library_card'));
  }

  /**
   * Tests a card cached before its pack lost an icon still renders.
   */
  public function testStaleCardSkipsMissingIcon(): void {
    $this->container->get('cache.default')->set('icon_library_card', [
      'test_path' => [
        'pack_definition' => ['id' => 'test_path', 'label' => 'Test path'],
        'total_icons' => 2,
        'icons' => ['test_path:foo' => [], 'test_path:missing' => []],
      ],
    ]);

    $build = LibraryIndex::create($this->container)->index();

    $this->assertCount(1, $build['grid']['content']['test_path']['#icons']);
  }

}
