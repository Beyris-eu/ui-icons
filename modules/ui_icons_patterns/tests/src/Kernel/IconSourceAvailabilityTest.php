<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_patterns\Hook\UiIconsPatternsHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the icon sources are offered only when there are icons to pick.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsPatternsHooks::class)]
#[Group('ui_icons')]
class IconSourceAvailabilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_patterns',
    'ui_patterns',
    'user',
  ];

  /**
   * Tests the sources follow the icons available.
   */
  public function testSourcesFollowIcons(): void {
    $sources = $this->container->get('plugin.manager.ui_patterns_source')->getDefinitions();
    $this->assertArrayNotHasKey('icon', $sources);
    $this->assertArrayNotHasKey('icon_renderable', $sources);

    $this->enableModules(['ui_icons_test']);
    $this->container->get('plugin.manager.icon_pack')->clearCachedDefinitions();
    $source_manager = $this->container->get('plugin.manager.ui_patterns_source');
    $source_manager->clearCachedDefinitions();
    $sources = $source_manager->getDefinitions();
    $this->assertArrayHasKey('icon', $sources);
    $this->assertArrayHasKey('icon_renderable', $sources);
  }

}
