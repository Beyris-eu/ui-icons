<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel\Plugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManager;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;

/**
 * @coversDefaultClass \Drupal\ui_icons\Plugin\IconPackManager
 *
 * Tests values are from test module.
 *
 * @see ui_icons/tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
 *
 * @group ui_icons
 */
class IconPackManagerKernelTest extends KernelTestBase {

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_FULL_ID = 'test_minimal:foo';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_test',
  ];

  /**
   * The IconPackManager instance.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface
   */
  private IconPackManagerInterface $pluginManagerIconPack;

  /**
   * The App root instance.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_handler = $this->container->get('module_handler');
    $theme_handler = $this->container->get('theme_handler');
    $cache_backend = $this->container->get('cache.default');
    $ui_icons_extractor_plugin_manager = $this->container->get('plugin.manager.ui_icons_extractor');
    $this->appRoot = $this->container->getParameter('app.root');

    $this->pluginManagerIconPack = new IconPackManager(
      $module_handler,
      $theme_handler,
      $cache_backend,
      $ui_icons_extractor_plugin_manager,
      $this->appRoot,
    );
  }

  /**
   * Test the _construct method.
   */
  public function testConstructor(): void {
    $this->assertInstanceOf(IconPackManager::class, $this->pluginManagerIconPack);
  }

  /**
   * Test the getIcons method.
   */
  public function testGetIcons(): void {
    $icons = $this->pluginManagerIconPack->getIcons();
    $this->assertCount(30, $icons);
    foreach ($icons as $icon) {
      $this->assertInstanceOf(IconDefinitionInterface::class, $icon);
    }

    $icons = $this->pluginManagerIconPack->getIcons(['test_minimal']);
    $this->assertCount(1, $icons);
    foreach ($icons as $icon) {
      $this->assertInstanceOf(IconDefinitionInterface::class, $icon);
    }

    $icons = $this->pluginManagerIconPack->getIcons(['do_not_exist']);
    $this->assertEmpty($icons);
  }

  /**
   * Test the getIcon method.
   */
  public function testGetIcon(): void {
    $icon = $this->pluginManagerIconPack->getIcon(self::TEST_ICON_FULL_ID);
    $this->assertInstanceOf(IconDefinitionInterface::class, $icon);

    $icon = $this->pluginManagerIconPack->getIcon('test_minimal:_do_not_exist_');
    $this->assertNull($icon);
  }

  /**
   * Test the listIconPackOptions method.
   */
  public function testListIconPackOptions(): void {
    $actual = $this->pluginManagerIconPack->listIconPackOptions();
    $expected = [
      'test_minimal' => 'test_minimal (1)',
      'test_path' => 'Test path (10)',
      'test_svg' => 'Test svg (11)',
      'test_svg_sprite' => 'Test sprite (3)',
      'test_no_settings' => 'test_no_settings (1)',
      'test_settings' => 'Test settings (1)',
      'test_url_path' => 'Test url path (2)',
      'test_url_svg' => 'Test url svg (1)',
    ];
    $this->assertEquals($expected, $actual);

    $actual = $this->pluginManagerIconPack->listIconPackOptions(TRUE);
    $expected = [
      'test_minimal' => 'test_minimal (1)',
      'test_path' => 'Test path - Local png files available for test. (10)',
      'test_svg' => 'Test svg (11)',
      'test_svg_sprite' => 'Test sprite (3)',
      'test_no_settings' => 'test_no_settings (1)',
      'test_settings' => 'Test settings (1)',
      'test_url_path' => 'Test url path (2)',
      'test_url_svg' => 'Test url svg (1)',
    ];
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test the getExtractorFormDefault method.
   */
  public function testGetExtractorFormDefaults(): void {
    $actual = $this->pluginManagerIconPack->getExtractorFormDefaults('test_settings');
    // @see ui_icons/tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
    $expected = [
      'width' => 32,
      'height' => 33,
      'title' => 'Default title',
      'alt' => 'Default alt',
      'select' => 400,
      'boolean' => TRUE,
      'decimal' => 66.66,
      'number' => 30,
    ];
    $this->assertSame($expected, $actual);

    $actual = $this->pluginManagerIconPack->getExtractorFormDefaults('test_no_settings');
    $this->assertSame([], $actual);
  }

  /**
   * Test the getExtractorPluginForms method.
   */
  public function testGetExtractorPluginForms(): void {
    $form_state = $this->getMockBuilder('Drupal\Core\Form\FormState')
      ->disableOriginalConstructor()
      ->getMock();
    $form = [];

    $this->pluginManagerIconPack->getExtractorPluginForms($form, $form_state);

    // @see ui_icons/tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
    $this->assertCount(4, array_keys($form));
    $expected = ['test_path', 'test_svg', 'test_svg_sprite', 'test_settings'];
    $this->assertSame($expected, array_keys($form));

    $expected = [
      '#type',
      '#title',
      'width',
      'height',
      'title',
    ];
    $this->assertSame($expected, array_keys($form['test_path']));

    $expected = [
      '#type',
      '#title',
      'width',
      'height',
    ];
    $this->assertSame($expected, array_keys($form['test_svg_sprite']));

    $expected = [
      '#type',
      '#title',
      'width',
      'height',
      'title',
      'alt',
      'select',
      'boolean',
      'decimal',
      'number',
    ];
    $this->assertSame($expected, array_keys($form['test_settings']));

    // No form if no settings.
    $this->assertArrayNotHasKey('test_no_settings', $form);
  }

  /**
   * Test the getExtractorPluginForms method.
   */
  public function testGetExtractorPluginFormsWithAllowed(): void {
    $form_state = $this->getMockBuilder('Drupal\Core\Form\FormState')
      ->disableOriginalConstructor()
      ->getMock();
    $form = [];

    $allowed_icon_pack['test_svg'] = '';

    $this->pluginManagerIconPack->getExtractorPluginForms($form, $form_state, [], $allowed_icon_pack);

    $this->assertArrayHasKey('test_svg', $form);

    $this->assertArrayNotHasKey('test_minimal', $form);
    $this->assertArrayNotHasKey('test_svg_sprite', $form);
    $this->assertArrayNotHasKey('test_no_icons', $form);
  }

  /**
   * Test the getExtractorPluginForms method.
   */
  public function testGetExtractorPluginFormsWithDefault(): void {
    $form = [
      '#parents' => [],
      'test_settings' => [
        '#parents' => ['test_settings'],
        '#array_parents' => ['test_settings'],
      ],
    ];

    $form_state = $this->getMockBuilder('Drupal\Core\Form\FormState')
      ->disableOriginalConstructor()
      ->onlyMethods(['setValue', 'getValue'])
      ->getMock();
    $this->pluginManagerIconPack->getExtractorPluginForms($form, $form_state);

    // Without default, values are from definition.
    $expected = [
      'width' => 32,
      'height' => 33,
      'title' => 'Default title',
      'alt' => 'Default alt',
      'select' => 400,
      'boolean' => TRUE,
      'decimal' => 66.66,
      'number' => 30,
    ];
    foreach ($expected as $key => $value) {
      $this->assertSame($value, $form['test_settings'][$key]['#default_value']);
    }

    // Test definition without value.
    $this->assertArrayNotHasKey('#default_value', $form['test_svg']['size']);

    $default_settings = ['test_settings' => ['width' => 100, 'height' => 110, 'title' => 'Test']];

    // Test the set/get of default values as 'saved_values'.
    $form_state->expects($this->once())
      ->method('setValue')
      ->with('saved_values', $default_settings['test_settings']);

    $form_state->expects($this->once())
      ->method('getValue')
      ->with('saved_values')
      ->willReturn($default_settings['test_settings']);

    $this->pluginManagerIconPack->getExtractorPluginForms($form, $form_state, $default_settings, ['test_settings' => '']);

    $this->assertSame($default_settings['test_settings']['width'], $form['test_settings']['width']['#default_value']);
    $this->assertSame($default_settings['test_settings']['height'], $form['test_settings']['height']['#default_value']);
    $this->assertSame($default_settings['test_settings']['title'], $form['test_settings']['title']['#default_value']);
  }

  /**
   * Test the processDefinition method.
   */
  public function testProcessDefinition(): void {
    $definition = [
      'id' => 'foo',
      'label' => 'Foo',
      'provider' => 'ui_icons_test',
      'extractor' => 'bar',
      'template' => '',
      'config' => [],
    ];

    $this->pluginManagerIconPack->processDefinition($definition, 'foo');

    $this->assertSame('foo', $definition['id']);
    $this->assertSame('Foo', $definition['label']);

    $relative_path = 'modules/custom/ui_icons/tests/modules/ui_icons_test';
    $this->assertEquals($relative_path, $definition['relative_path']);

    $absolute_path = sprintf('%s/%s', $this->appRoot, $relative_path);
    $this->assertEquals($absolute_path, $definition['absolute_path']);
  }

  /**
   * Test the processDefinition method with exception.
   */
  public function testProcessDefinitionExceptionName(): void {
    $definition = ['provider' => 'foo'];
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Invalid Icon Pack id in: foo, name: $ Not valid !* must contain only lowercase letters, numbers, and underscores.');
    $this->pluginManagerIconPack->processDefinition($definition, '$ Not valid !*');
  }

  /**
   * Test the processDefinition method with exception.
   */
  public function testProcessDefinitionExceptionExtractor(): void {
    $definition = [
      'id' => 'foo',
      'label' => 'Foo',
      'provider' => 'ui_icons_test',
      'template' => '',
    ];
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `extractor:` key in your definition!');
    $this->pluginManagerIconPack->processDefinition($definition, 'foo');
  }

  /**
   * Test the processDefinition method with exception.
   */
  public function testProcessDefinitionExceptionTemplate(): void {
    $definition = [
      'id' => 'foo',
      'label' => 'Foo',
      'provider' => 'ui_icons_test',
      'extractor' => 'bar',
    ];
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `template:` key in your definition!');
    $this->pluginManagerIconPack->processDefinition($definition, 'foo');
  }

}
