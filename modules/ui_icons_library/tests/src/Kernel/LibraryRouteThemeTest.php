<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_library\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the icon library renders with the front theme.
 *
 * Pack settings can print classes only the front theme styles, as the
 * component library of UI Patterns does for the same reason.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversNothing]
#[Group('ui_icons')]
class LibraryRouteThemeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_library',
    'user',
  ];

  /**
   * Tests the library pages are not admin routes.
   */
  public function testLibraryUsesTheFrontTheme(): void {
    $this->container->get('router.builder')->rebuild();
    $route_provider = $this->container->get('router.route_provider');
    $admin_context = $this->container->get('router.admin_context');

    foreach (['ui_icons_library.index', 'ui_icons_library.pack'] as $route_name) {
      $this->assertFalse($admin_context->isAdminRoute($route_provider->getRouteByName($route_name)), $route_name);
    }
  }

}
