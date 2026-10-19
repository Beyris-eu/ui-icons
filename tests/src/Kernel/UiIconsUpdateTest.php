<?php

declare(strict_types=1);

// cspell:ignore iconify
namespace Drupal\Tests\ui_icons\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ui_icons update functions.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversFunction('ui_icons_update_11201')]
#[Group('ui_icons')]
class UiIconsUpdateTest extends KernelTestBase {

  private const OBSOLETE = ['ui_icons_backport', 'ui_icons_iconify_api'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
  ];

  /**
   * Test the obsolete modules are dropped from the extension list.
   */
  public function testUpdate11201(): void {
    $this->container->get('module_handler')->loadInclude('ui_icons', 'install');
    $extension = $this->config('core.extension');
    $schema = $this->container->get('keyvalue')->get('system.schema');
    $expected_modules = $extension->get('module');
    $expected_schema = $schema->getAll();
    ksort($expected_schema);

    // The update runs once the modules are gone from the disk, so only their
    // extension and schema entries are left.
    foreach (self::OBSOLETE as $module) {
      $extension->set('module.' . $module, 0);
      $schema->set($module, 8000);
    }
    $extension->save();

    $this->assertSame('Removed obsolete module(s): ui_icons_backport, ui_icons_iconify_api.', ui_icons_update_11201());
    $this->assertSame($expected_modules, $this->config('core.extension')->get('module'));
    $actual_schema = $schema->getAll();
    ksort($actual_schema);
    $this->assertSame($expected_schema, $actual_schema);

    $extension_data = $this->config('core.extension')->getRawData();
    $this->assertSame('No obsolete UI Icons module to remove.', ui_icons_update_11201());
    $this->assertSame($extension_data, $this->config('core.extension')->getRawData());
  }

}
