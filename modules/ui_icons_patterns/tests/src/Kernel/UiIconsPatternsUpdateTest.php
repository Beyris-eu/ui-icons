<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ui_icons_patterns update functions.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversFunction('ui_icons_patterns_update_11201')]
#[Group('ui_icons')]
class UiIconsPatternsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_patterns',
    'ui_patterns',
  ];

  /**
   * Test the selector setting is added once, and never overwritten.
   */
  public function testUpdate11201(): void {
    $this->container->get('module_handler')->loadInclude('ui_icons_patterns', 'install');

    $this->assertSame('Added the icon selector setting.', ui_icons_patterns_update_11201());
    $this->assertSame('icon_popover', $this->config('ui_icons_patterns.settings')->get('icon_selector'));

    $this->config('ui_icons_patterns.settings')->set('icon_selector', 'icon_picker')->save();
    $this->assertSame('The icon selector setting already exists.', ui_icons_patterns_update_11201());
    $this->assertSame('icon_picker', $this->config('ui_icons_patterns.settings')->get('icon_selector'));
  }

}
