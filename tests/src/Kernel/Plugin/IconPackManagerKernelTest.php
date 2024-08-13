<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManager;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;

/**
 * Tests the IconPackManager class.
 *
 * Tests values are from test module.
 *
 * @see ui_icons/tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
 *
 * @group ui_icons
 */
class IconPackManagerKernelTest extends KernelTestBase {

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
  private IconPackManagerInterface $iconPackManager;

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

    $this->iconPackManager = new IconPackManager(
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
    $this->assertInstanceOf(IconPackManager::class, $this->iconPackManager);
  }

  /**
   * Test the getIcons method.
   */
  public function testGetIcons(): void {
    $icons = $this->iconPackManager->getIcons();
    $this->assertIsArray($icons);
    $this->assertArrayHasKey('test_local_files:local__9.0_black', $icons);
  }

  /**
   * Test the getIcon method.
   */
  public function testGetIcon(): void {
    $icon = $this->iconPackManager->getIcon('test_local_files:local__9.0_black');
    $this->assertInstanceOf(IconDefinitionInterface::class, $icon);

    $icon = $this->iconPackManager->getIcon('test_local_files:_do_not_exist_');
    $this->assertNull($icon);
  }

  /**
   * Test the listIconPackOptions method.
   */
  public function testListIconPackOptions(): void {
    $actual = $this->iconPackManager->listIconPackOptions();
    $expected = [
      'test_local_files' => 'Local files',
      'test_local_svg' => 'SVG manual',
      'test_local_svg_sprite' => 'Small sprite',
      'test_no_icons' => 'No Icons',
      'test_no_settings' => 'No Settings',
    ];
    $this->assertSame($expected, $actual);
  }

  /**
   * Test the listIconPackWithDescriptionOption method.
   */
  public function testListIconPackWithDescriptionOptions(): void {
    $actual = $this->iconPackManager->listIconPackWithDescriptionOptions();
    $expected = [
      'test_local_files' => 'Local files - Local files relative available.',
      'test_local_svg' => 'SVG manual - Local svg files.',
      'test_local_svg_sprite' => 'Small sprite - Local svg sprite file.',
      'test_no_icons' => 'No Icons',
      'test_no_settings' => 'No Settings',
    ];
    $this->assertSame($expected, $actual);
  }

  /**
   * Test the listOptions method.
   */
  public function testListOptions(): void {
    $actual = $this->iconPackManager->listOptions();
    $this->assertCount(25, $actual);

    $actual = $this->iconPackManager->listOptions(['test_local_svg']);
    $this->assertCount(7, $actual);

    $actual = $this->iconPackManager->listOptions(['test_no_icons']);
    $this->assertCount(0, $actual);

    $actual = $this->iconPackManager->listOptions(['do_not_exist']);
    $this->assertCount(0, $actual);
  }

  /**
   * Test the getExtractorFormDefault method.
   */
  public function testGetExtractorFormDefaults(): void {
    $actual = $this->iconPackManager->getExtractorFormDefaults('test_local_files');
    // @see ui_icons/tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
    $expected = [
      'width' => 32,
      'height' => 33,
      'title' => 'default title',
    ];
    $this->assertSame($expected, $actual);

    $actual = $this->iconPackManager->getExtractorFormDefaults('test_no_settings');
    $this->assertSame([], $actual);
  }

  /**
   * Test the getExtractorPluginForms method.
   */
  public function testGetExtractorPluginForms(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $this->iconPackManager->getExtractorPluginForms($form, $form_state);

    // @see ui_icons/tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
    $this->assertCount(4, array_keys($form));
    $expected = ['test_local_files', 'test_local_svg', 'test_local_svg_sprite', 'test_no_icons'];
    $this->assertSame($expected, array_keys($form));

    // Attributes is important, used by js for hidden/show.
    $this->assertSame(['name' => 'icon-settings--test_local_files'], $form['test_local_files']['#attributes']);
    $this->assertSame(['name' => 'icon-settings--test_local_svg'], $form['test_local_svg']['#attributes']);
    $this->assertSame(['name' => 'icon-settings--test_local_svg_sprite'], $form['test_local_svg_sprite']['#attributes']);
    $this->assertSame(['name' => 'icon-settings--test_no_icons'], $form['test_no_icons']['#attributes']);

    // Check under settings form key.
    $this->assertArrayHasKey('width', $form['test_local_files']);
    $this->assertArrayHasKey('height', $form['test_local_files']);
    $this->assertArrayHasKey('title', $form['test_local_files']);

    $this->assertArrayHasKey('width', $form['test_local_svg']);
    $this->assertArrayHasKey('height', $form['test_local_svg']);

    $this->assertArrayHasKey('width', $form['test_local_svg_sprite']);
    $this->assertArrayHasKey('height', $form['test_local_svg_sprite']);

    $this->assertArrayHasKey('title', $form['test_no_icons']);

    // No form if no settings.
    $this->assertArrayNotHasKey('test_no_settings', $form);
  }

  /**
   * Test the getExtractorPluginForms method.
   */
  public function testGetExtractorPluginFormsWithAllowed(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $allowed_icon_pack['test_local_svg'] = '';

    $this->iconPackManager->getExtractorPluginForms($form, $form_state, [], $allowed_icon_pack);

    $this->assertArrayHasKey('test_local_svg', $form);

    $this->assertArrayNotHasKey('test_local_files', $form);
    $this->assertArrayNotHasKey('test_local_svg_sprite', $form);
    $this->assertArrayNotHasKey('test_no_icons', $form);
  }

  /**
   * Test the getExtractorPluginForms method.
   */
  public function testGetExtractorPluginFormsWithDefault(): void {
    $form = [
      '#parents' => [],
      'test_local_files' => [
        '#parents' => ['test_local_files'],
        '#array_parents' => ['test_local_files'],
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $this->iconPackManager->getExtractorPluginForms($form, $form_state);

    // Without default, values are from definition.
    $this->assertSame(32, $form['test_local_files']['width']['#default_value']);
    $this->assertSame(33, $form['test_local_files']['height']['#default_value']);
    $this->assertSame('default title', $form['test_local_files']['title']['#default_value']);

    // Test definition without value.
    $this->assertArrayNotHasKey('#default_value', $form['test_local_svg']['width']);
    $this->assertArrayNotHasKey('#default_value', $form['test_local_svg']['height']);
    $this->assertArrayNotHasKey('#default_value', $form['test_local_svg']['title']);

    $default_settings = ['test_local_files' => ['width' => 100, 'height' => 110, 'title' => 'Test']];

    // Test the set/get of default values as 'saved_values'.
    $form_state->expects($this->once())
      ->method('setValue')
      ->with('saved_values', $default_settings['test_local_files']);

    $form_state->expects($this->once())
      ->method('getValue')
      ->with('saved_values')
      ->willReturn($default_settings['test_local_files']);

    // Test with only one icon pack test_local_files.
    $this->iconPackManager->getExtractorPluginForms($form, $form_state, $default_settings, ['test_local_files' => '']);

    $this->assertSame($default_settings['test_local_files']['width'], $form['test_local_files']['width']['#default_value']);
    $this->assertSame($default_settings['test_local_files']['height'], $form['test_local_files']['height']['#default_value']);
    $this->assertSame($default_settings['test_local_files']['title'], $form['test_local_files']['title']['#default_value']);
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
      'config' => [],
    ];

    $this->iconPackManager->processDefinition($definition, 'foo');

    $this->assertSame('foo', $definition['id']);
    $this->assertSame('Foo', $definition['label']);
    $this->assertArrayHasKey('_path_info', $definition);
    $this->assertArrayHasKey('drupal_root', $definition['_path_info']);
    $this->assertSame($this->appRoot, $definition['_path_info']['drupal_root']);

    // Can not check these paths values as CI relate to modules/custom.
    $this->assertArrayHasKey('absolute_path', $definition['_path_info']);
    $this->assertArrayHasKey('relative_path', $definition['_path_info']);
  }

  /**
   * Test the processDefinition method.
   */
  public function testProcessDefinitionExceptionName(): void {
    $definition = ['provider' => 'foo'];
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Invalid Icon Pack id in: foo, name: $ Not valid !* must contain only lowercase letters, numbers, and underscores.');
    $this->iconPackManager->processDefinition($definition, '$ Not valid !*');
  }

  /**
   * Test the processDefinition method.
   */
  public function testProcessDefinitionExceptionExtractor(): void {
    $definition = [
      'id' => 'foo',
      'label' => 'Foo',
      'provider' => 'ui_icons_test',
    ];
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `extractor:` key in your definition!');
    $this->iconPackManager->processDefinition($definition, 'foo');
  }

  /**
   * Test the processDefinition method.
   */
  public function testProcessDefinitionExceptionConfig(): void {
    $definition = [
      'id' => 'foo',
      'label' => 'Foo',
      'provider' => 'ui_icons_test',
      'extractor' => 'bar',
    ];
    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config:` key in your definition extractor!');
    $this->iconPackManager->processDefinition($definition, 'foo');
  }

}
