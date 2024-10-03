<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_menu\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;

/**
 * Tests the ui_icons_menu module.
 *
 * @group ui_icons
 */
class UiIconsMenuTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'menu_link_content',
    'link',
    'ui_icons',
    'ui_icons_menu',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
  }

  /**
   * Tests ui_icons_menu_entity_base_field_info_alter().
   */
  public function testEntityBaseFieldInfoAlter(): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('menu_link_content');
    $fields = MenuLinkContent::baseFieldDefinitions($entity_type);

    ui_icons_menu_entity_base_field_info_alter($fields, $entity_type);

    $this->assertArrayHasKey('link', $fields);
    $link_field = $fields['link'];
    $this->assertInstanceOf(BaseFieldDefinition::class, $link_field);

    $form_display_options = $link_field->getDisplayOptions('form');
    $this->assertIsArray($form_display_options);
    $this->assertArrayHasKey('type', $form_display_options);
    $this->assertContains($form_display_options['type'], ['icon_link_widget', 'icon_link_attributes_widget']);

    $this->assertFalse($link_field->isRequired());
  }

  /**
   * Data provider for ::testPreprocessMenu().
   */
  public static function iconDisplayDataProvider(): array {
    return [
      'icon before' => ['before', ['icon', 'title']],
      'icon after' => ['after', ['title', 'icon']],
      'icon only' => ['icon_only', ['icon']],
    ];
  }

  /**
   * Tests ui_icons_menu_preprocess_menu().
   *
   * @dataProvider iconDisplayDataProvider
   */
  public function testPreprocessMenu(?string $iconDisplay, array $expectedOrder): void {
    // Create a mock menu item.
    $menu_link = MenuLinkContent::create([
      'title' => 'Test Item',
      'link' => ['uri' => 'internal:/'],
    ]);
    $menu_link->save();

    $variables = [
      'items' => [
        [
          'url' => $menu_link->getUrlObject(),
          'title' => $menu_link->getTitle(),
          'below' => [],
        ],
      ],
    ];

    // Set icon options.
    $url = $variables['items'][0]['url'];
    $options = $url->getOptions();
    $options['icon'] = ['target_id' => 'test_pack:test_icon'];
    if ($iconDisplay !== NULL) {
      $options['icon_display'] = $iconDisplay;
    }
    $url->setOptions($options);

    // Create a mock IconDefinitionInterface.
    $icon = $this->createMock(IconDefinitionInterface::class);
    $icon->method('getPackId')->willReturn('test_pack');
    $icon->method('getRenderable')->willReturn(['#markup' => '<img class="icon drupal-icon" src=""/>']);

    // Mock the icon pack manager service.
    $icon_pack_manager = $this->createMock(IconPackManagerInterface::class);
    $icon_pack_manager->method('getIcon')->willReturn($icon);
    $this->container->set('plugin.manager.ui_icons_pack', $icon_pack_manager);

    ui_icons_menu_preprocess_menu($variables);

    if ($iconDisplay === 'icon_only') {
      $this->assertIsArray($variables['items'][0]['title']);
      $this->assertEquals(
        ['#markup' => '<img class="icon drupal-icon" src=""/>'],
        $variables['items'][0]['title']
      );
    }
    else {
      $this->assertIsArray($variables['items'][0]['title']);
      $this->assertCount(count($expectedOrder), $variables['items'][0]['title']);

      foreach ($expectedOrder as $index => $key) {
        $this->assertArrayHasKey($key, $variables['items'][0]['title']);
        $this->assertSame($index, array_search($key, array_keys($variables['items'][0]['title']), TRUE));
      }

      $this->assertEquals(
        '<span class="ui-icons-menu-text">Test Item</span>',
        $variables['items'][0]['title']['title']['#markup']
      );

      $this->assertEquals(
        ['#markup' => '<img class="icon drupal-icon" src=""/>'],
        $variables['items'][0]['title']['icon']
      );
    }
  }

}
