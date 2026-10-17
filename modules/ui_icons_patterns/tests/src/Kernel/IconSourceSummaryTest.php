<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\IconRenderableSource;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\IconSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings summary of the icon sources.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconSource::class)]
#[CoversClass(IconRenderableSource::class)]
#[Group('ui_icons')]
class IconSourceSummaryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_patterns',
    'ui_icons_test',
    'ui_patterns',
    'user',
  ];

  /**
   * Tests both sources summarize an icon the same way.
   */
  #[DataProvider('providerSettingsSummary')]
  public function testSettingsSummary(string $source_id, mixed $value, array $expected): void {
    $source = $this->container->get('plugin.manager.ui_patterns_source')
      ->createInstance($source_id, ['settings' => ['value' => $value]]);

    $this->assertSame($expected, array_map('strval', $source->settingsSummary()));
  }

  /**
   * Data provider for ::testSettingsSummary().
   */
  public static function providerSettingsSummary(): array {
    return [
      'prop' => ['icon', ['target_id' => 'test_path:foo'], ['foo (test_path)']],
      'slot' => ['icon_renderable', ['target_id' => 'test_path:foo'], ['foo (test_path)']],
      'separator in icon id' => ['icon', ['target_id' => 'test_path:foo:bar'], ['foo:bar (test_path)']],
      'legacy icon_id key' => ['icon_renderable', ['icon_id' => 'test_path:foo'], ['foo (test_path)']],
      'no icon' => ['icon', NULL, []],
      'not an icon id' => ['icon_renderable', ['target_id' => 'foo'], []],
    ];
  }

}
