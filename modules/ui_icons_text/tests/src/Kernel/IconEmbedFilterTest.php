<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_text\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_text\Plugin\Filter\IconEmbed;

/**
 * Test the text filter.
 *
 * @group ui_icons
 */
class IconEmbedFilterTest extends KernelTestBase {

  /**
   * Icon pack from ui_icons_test module.
   */
  private const TEST_ICON_PACK_ID = 'test';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID = 'foo';

  /**
   * Icon filename from ui_icons_test module.
   */
  private const TEST_ICON_FILENAME = 'foo.png';

  /**
   * Icon class from ui_icons_test module.
   */
  private const TEST_ICON_CLASS = 'foo';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'filter',
    'ui_icons',
    'ui_icons_text',
    'ui_icons_test',
  ];

  /**
   * The icon embed filter plugin.
   *
   * @var \Drupal\ui_icons_text\Plugin\Filter\IconEmbed
   */
  protected $filter;

  /**
   * The icon pack manager service.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface
   */
  protected $pluginManagerIconPack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'filter', 'ui_icons']);

    $this->pluginManagerIconPack = $this->container->get('plugin.manager.ui_icons_pack');

    /** @var \Drupal\filter\FilterPluginManager $filterManager */
    $filterManager = $this->container->get('plugin.manager.filter');
    $configuration = [];
    $plugin_id = 'icon_embed';
    $plugin_definition = $filterManager->getDefinition($plugin_id);

    $this->filter = new IconEmbed(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->pluginManagerIconPack,
      $this->container->get('renderer'),
      $this->container->get('logger.factory')
    );
  }

  /**
   * Test the process method.
   */
  public function testProcess(): void {
    $icon_full_id = self::TEST_ICON_PACK_ID . ':' . self::TEST_ICON_ID;

    // Test case 1: No icon tags.
    $text = '<p>This is a test paragraph without icons.</p>';
    $result = $this->filter->process($text, 'en');
    $this->assertEquals($text, $result->getProcessedText());

    // Test case 2: Valid icon tag.
    $text = '<p>This is a test with an icon: <drupal-icon data-icon-id="' . $icon_full_id . '" /></p>';
    $result = $this->filter->process($text, 'en');
    $this->assertStringContainsString('<span class="drupal-icon">', $result->getProcessedText());
    $this->assertStringContainsString(self::TEST_ICON_CLASS, $result->getProcessedText());
    $this->assertStringContainsString(self::TEST_ICON_FILENAME, $result->getProcessedText());

    // Test case 3: Invalid icon ID.
    $text = '<p>This is a test with an invalid icon: <drupal-icon data-icon-id="invalid:icon" /></p>';
    $result = $this->filter->process($text, 'en');
    $this->assertStringContainsString('<span class="drupal-icon">', $result->getProcessedText());
    $this->assertStringContainsString('The referenced icon: invalid:icon is missing', $result->getProcessedText());

    // Test case 4: Icon with additional attribute.
    $text = '<p>This is a test with an icon and attributes: <drupal-icon data-icon-id="' . $icon_full_id . '" class="custom-class" aria-label="Custom Label" /></p>';
    $result = $this->filter->process($text, 'en');
    $this->assertStringContainsString('<span class="custom-class drupal-icon"', $result->getProcessedText());
    $this->assertStringContainsString('aria-label="Custom Label"', $result->getProcessedText());

    // Test case 5: Icon with settings.
    $text = '<p>This is a test with an icon and settings: <drupal-icon data-icon-id="' . $icon_full_id . '" data-icon-settings=\'{"width":100}\' /></p>';
    $result = $this->filter->process($text, 'en');
    $this->assertStringContainsString('<span class="drupal-icon">', $result->getProcessedText());
    $this->assertStringContainsString(self::TEST_ICON_CLASS, $result->getProcessedText());
    $this->assertStringContainsString(self::TEST_ICON_FILENAME, $result->getProcessedText());
    $this->assertStringContainsString('width="100"', $result->getProcessedText());
  }

}
