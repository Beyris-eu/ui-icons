<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_popover\Kernel;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_popover\Controller\IconPopoverController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the icon index and previews the popover loads.
 *
 * The packs come from
 * tests/modules/ui_icons_popover_test/ui_icons_popover_test.icons.yml.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPopoverController::class)]
#[Group('ui_icons')]
class IconPopoverControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_icons',
    'ui_icons_popover',
    'ui_icons_popover_test',
    'ui_icons_test',
  ];

  /**
   * Requests the index for some icon packs.
   *
   * @param string $allowed_icon_pack
   *   The pack ids, joined with `+` as the element sends them.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The index response.
   */
  private function index(string $allowed_icon_pack): CacheableJsonResponse {
    $request = Request::create('/ui-icons/ajax/popover/index', 'GET', ['allowed_icon_pack' => $allowed_icon_pack]);
    return IconPopoverController::create($this->container)->index($request);
  }

  /**
   * Tests the groups of different packs merge on the same name.
   */
  public function testIndexMergesGroups(): void {
    $data = json_decode((string) $this->index('popover_one+popover_two+popover_flat')->getContent(), TRUE);

    $this->assertSame([
      'popover_one' => 'Popover one',
      'popover_two' => 'Popover two',
      'popover_flat' => 'Popover flat',
    ], $data['packs']);
    // 'arrows' and 'Arrows' are one group, the ungrouped icon makes none.
    $this->assertSame([
      ['key' => 'arrows', 'label' => 'Arrows'],
      ['key' => 'weather', 'label' => 'Weather'],
    ], $data['groups']);
    $this->assertSame([
      ['popover_one:arrow-down', 'Arrow Down', 'arrows'],
      ['popover_two:arrow-left', 'Arrow Left', 'arrows'],
      ['popover_one:arrow-up', 'Arrow Up', 'arrows'],
      ['popover_two:dot.name', 'Dot Name', 'arrows'],
      ['popover_flat:star', 'Star', NULL],
      ['popover_one:sun', 'Sun', 'weather'],
    ], $data['icons']);
  }

  /**
   * Tests a pack the element allows but that no longer exists is skipped.
   */
  public function testIndexIgnoresMissingPack(): void {
    $data = json_decode((string) $this->index('popover_flat+missing_pack')->getContent(), TRUE);

    $this->assertSame([['popover_flat:star', 'Star', NULL]], $data['icons']);
    $this->assertSame([], $data['groups']);
  }

  /**
   * Tests the index is cached until the icon packs change.
   */
  public function testIndexCacheability(): void {
    $metadata = $this->index('popover_one')->getCacheableMetadata();

    $this->assertContains('icon_pack_plugin', $metadata->getCacheTags());
    $this->assertContains('icon_pack_collector', $metadata->getCacheTags());
    $this->assertContains('url.query_args:allowed_icon_pack', $metadata->getCacheContexts());
  }

}
