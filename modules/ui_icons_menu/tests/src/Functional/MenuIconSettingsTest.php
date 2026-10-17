<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_menu\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ui_icons_menu\Hook\UiIconsMenuHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the icon settings of each menu.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsMenuHooks::class)]
#[Group('ui_icons')]
class MenuIconSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_ui',
    'menu_link_content',
    'ui_icons_menu',
    'ui_icons_picker',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests a menu sets the icon widget of its own links only.
   */
  public function testMenuIconSettings(): void {
    // The icon autocomplete route needs 'access content'.
    $this->drupalLogin($this->drupalCreateUser(['administer menu', 'access content']));

    $this->drupalGet('admin/structure/menu/manage/tools');
    $this->submitForm([
      'ui_icons_menu[icon_selector]' => 'icon_picker',
      'ui_icons_menu[icon_required]' => TRUE,
      'ui_icons_menu[allowed_icon_pack][test_path]' => TRUE,
    ], 'Save');

    $menu = $this->container->get('entity_type.manager')->getStorage('menu')->loadUnchanged('tools');
    $this->assertSame('icon_picker', $menu->getThirdPartySetting('ui_icons_menu', 'icon_selector'));
    $this->assertSame(['test_path'], $menu->getThirdPartySetting('ui_icons_menu', 'allowed_icon_pack'));
    $this->assertTrue($menu->getThirdPartySetting('ui_icons_menu', 'icon_required'));

    $this->drupalGet('admin/structure/menu/manage/tools/add');
    $this->assertSession()->elementExists('css', 'input.form-icon-dialog[data-allowed-icon-pack="test_path"][required]');

    $this->drupalGet('admin/structure/menu/manage/footer');
    $this->submitForm(['ui_icons_menu[result_format]' => 'grid'], 'Save');
    $this->drupalGet('admin/structure/menu/manage/footer/add');
    $this->assertSession()->elementAttributeContains('css', 'input[name="link[0][options][icon][icon_id]"]', 'data-autocomplete-path', 'result_format=grid');

    $this->drupalGet('admin/structure/menu/manage/main/add');
    $this->assertSession()->elementNotExists('css', 'input.form-icon-dialog');
    $this->assertSession()->elementExists('css', 'input.form-autocomplete[name="link[0][options][icon][icon_id]"]:not([required])');
    $this->assertSession()->elementAttributeContains('css', 'input[name="link[0][options][icon][icon_id]"]', 'data-autocomplete-path', 'result_format=list');
  }

}
