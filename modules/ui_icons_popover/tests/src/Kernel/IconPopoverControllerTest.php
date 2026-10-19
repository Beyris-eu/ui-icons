<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_popover\Kernel;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Theme\Icon\IconCollector;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\IconPreview;
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
   * Tests a pack without groups is grouped by the first word of its names.
   *
   * Trailing digits go, a plural joins its singular, and a group needs at
   * least 3 icons, the floor for a pack this small.
   */
  public function testIndexDerivesGroupsFromNames(): void {
    $data = json_decode((string) $this->index('popover_names')->getContent(), TRUE);

    $this->assertSame([
      ['key' => 'arrow', 'label' => 'Arrow'],
      ['key' => 'cloud', 'label' => 'Cloud'],
    ], $data['groups']);
    $this->assertSame([
      ['popover_names:7-circle', '7 Circle', NULL],
      // Natural sorting skips spaces: "ArrowsExpand" comes before "ArrowUp".
      ['popover_names:arrow-down', 'Arrow Down', 'arrow'],
      ['popover_names:arrows-expand', 'Arrows Expand', 'arrow'],
      ['popover_names:arrow-up', 'Arrow Up', 'arrow'],
      ['popover_names:cloud2-rain', 'Cloud2 Rain', 'cloud'],
      ['popover_names:cloud3', 'Cloud3', 'cloud'],
      ['popover_names:cloud-sun', 'Cloud Sun', 'cloud'],
      ['popover_names:solo-one', 'Solo One', NULL],
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

  /**
   * Requests the previews of some icons.
   *
   * @param array $ids
   *   The icon full ids.
   * @param string $allowed_icon_pack
   *   The pack ids, joined with `+` as the element sends them.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The previews response.
   */
  private function previews(array $ids, string $allowed_icon_pack): CacheableJsonResponse {
    $request = Request::create('/ui-icons/ajax/popover/previews', 'GET', ['ids' => $ids, 'allowed_icon_pack' => $allowed_icon_pack]);
    return IconPopoverController::create($this->container)->previews($request);
  }

  /**
   * Tests each preview is the one the other selectors show.
   */
  public function testPreviewsRenderPreview(): void {
    $ids = ['popover_one:sun', 'popover_two:dot.name'];
    $data = json_decode((string) $this->previews($ids, 'popover_one+popover_two')->getContent(), TRUE);

    foreach ($ids as $id) {
      $icon = $this->container->get('ui_icons.search')->loadIcon($id);
      $preview = IconPreview::getPreview($icon, ['size' => 24]);
      $expected = (string) $this->container->get('renderer')->renderInIsolation($preview);
      $this->assertSame($expected, $data[$id]);
    }
  }

  /**
   * Tests a pack declaring its own preview gets that, not its template.
   */
  public function testPreviewsUsePreviewKey(): void {
    $data = json_decode((string) $this->previews(['test_preview:foo'], 'test_preview')->getContent(), TRUE);

    $this->assertStringContainsString('_preview_ foo', $data['test_preview:foo']);
    $this->assertStringNotContainsString('_template_', $data['test_preview:foo']);
  }

  /**
   * Tests icons outside the allowed packs and unknown icons are left out.
   */
  public function testPreviewsRejectOutsideAndUnknown(): void {
    $ids = ['popover_two:arrow-left', 'popover_one:missing', 'popover_one:sun'];
    $data = json_decode((string) $this->previews($ids, 'popover_one')->getContent(), TRUE);

    $this->assertSame(['popover_one:sun'], array_keys($data));
  }

  /**
   * Tests one request renders a bounded number of previews.
   */
  public function testPreviewsCap(): void {
    $ids = array_map(static fn (int $i): string => 'popover_one:missing-' . $i, range(1, 132));
    $ids[] = 'popover_one:sun';
    $data = json_decode((string) $this->previews($ids, 'popover_one')->getContent(), TRUE);

    $this->assertSame([], $data);
  }

  /**
   * Tests browsing previews leaves the icon collector cache alone.
   *
   * The collector keeps every icon it ever returned in one cache item, loaded
   * by any icon render, so scrolling a large pack must not grow it.
   */
  public function testPreviewsDoNotGrowIconCollector(): void {
    $this->previews(['popover_one:sun'], 'popover_one');
    $this->container->get(IconCollector::class)->destruct();

    $this->assertFalse($this->container->get('cache.default')->get('icon_info'));
  }

  /**
   * Tests the previews are cached per icon list and pack limit.
   */
  public function testPreviewsCacheability(): void {
    $metadata = $this->previews(['popover_one:sun'], 'popover_one')->getCacheableMetadata();

    $this->assertContains('icon_pack_plugin', $metadata->getCacheTags());
    $this->assertContains('url.query_args:ids', $metadata->getCacheContexts());
    $this->assertContains('url.query_args:allowed_icon_pack', $metadata->getCacheContexts());
  }

}
