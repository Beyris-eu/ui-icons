<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_popover\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Menu;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget;
use Drupal\ui_icons_menu\Hook\UiIconsMenuHooks;
use Drupal\ui_icons_text\Plugin\Filter\IconEmbed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the popover is offered wherever a site builder picks a selector.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconWidget::class)]
#[CoversClass(IconEmbed::class)]
#[CoversClass(UiIconsMenuHooks::class)]
#[Group('ui_icons')]
class PopoverSelectorOptionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'link',
    'menu_link_content',
    'menu_ui',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_menu',
    'ui_icons_text',
    'ui_icons_popover',
  ];

  /**
   * Gets the selector options of each choice point.
   *
   * The icon and link widgets share IconFieldTrait::getPickerOptions(), the
   * CKEditor icon dialog reads the text filter setting.
   *
   * @return array
   *   The options, keyed by choice point.
   */
  private function selectorOptions(): array {
    $widget = $this->container->get('plugin.manager.field.widget')->createInstance('icon_widget', [
      'field_definition' => BaseFieldDefinition::create('ui_icon')->setName('icon'),
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $filter = $this->container->get('plugin.manager.filter')->createInstance('icon_embed', ['settings' => ['allowed_icon_pack' => []]]);

    $form_object = $this->container->get('entity_type.manager')->getFormObject('menu', 'edit');
    $form_object->setEntity(Menu::create(['id' => 'test', 'label' => 'Test']));
    $form_state = (new FormState())->setFormObject($form_object);
    $menu_form = [];
    $this->container->get(UiIconsMenuHooks::class)->formMenuFormAlter($menu_form, $form_state);

    return [
      'widget' => $widget->settingsForm([], new FormState())['icon_selector']['#options'],
      'filter' => $filter->settingsForm([], new FormState())['selector_format']['#options'],
      'menu' => $menu_form['ui_icons_menu']['icon_selector']['#options'],
    ];
  }

  /**
   * Tests every choice point lists the popover.
   */
  public function testPopoverOffered(): void {
    foreach ($this->selectorOptions() as $name => $options) {
      $this->assertSame('Popover', (string) ($options['icon_popover'] ?? ''), $name);
    }
  }

  /**
   * Tests no choice point lists the popover once its module is gone.
   */
  public function testPopoverNotOfferedWhenUninstalled(): void {
    $this->disableModules(['ui_icons_popover']);

    foreach ($this->selectorOptions() as $name => $options) {
      $this->assertArrayNotHasKey('icon_popover', $options, $name);
    }
  }

}
