<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_menu\Kernel;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_menu\Hook\UiIconsMenuHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests an icon declared on a static menu link.
 *
 * A link from a *.links.menu.yml file keeps its options in the URL, where the
 * icon of a menu link content entity is read, so the same keys work there.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsMenuHooks::class)]
#[Group('ui_icons')]
class StaticMenuLinkIconTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'link',
    'ui_icons',
    'ui_icons_menu',
    'ui_icons_test',
    'ui_icons_menu_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->container->get('plugin.manager.menu.link')->rebuild();
  }

  /**
   * Tests the static link renders the icon it declares.
   */
  public function testStaticLinkIcon(): void {
    $menu_tree = $this->container->get('menu.link_tree');
    $build = $menu_tree->build($menu_tree->load('tools', new MenuTreeParameters()));

    $variables = ['items' => $build['#items'], 'theme_hook_original' => 'menu__tools'];
    $this->container->get(UiIconsMenuHooks::class)->preprocessMenu($variables);

    $title = (string) $variables['items']['ui_icons_menu_test.icon_link']['title'];
    $this->assertStringContainsString('foo.png', $title);
    $this->assertStringContainsString('Icon link', $title);
  }

}
