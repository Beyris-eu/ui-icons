<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit\Plugin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconFormatter;

/**
 * @coversDefaultClass \Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconFormatter
 *
 * @group ui_icons
 */
class IconFormatterTest extends UnitTestCase {

  /**
   * The field formatter under test.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconFormatter
   */
  private IconFormatter $formatter;

  /**
   * The IconPackManager instance.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface
   */
  private IconPackManagerInterface $pluginManagerIconPack;

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  private ContainerBuilder $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($this->container);

    $this->pluginManagerIconPack = $this->createMock(IconPackManagerInterface::class);
    $fieldDefinition = $this->getMockBuilder('Drupal\Core\Field\FieldDefinition')
      ->disableOriginalConstructor()
      ->getMock();

    $this->formatter = new IconFormatter(
      'icon_formatter',
      [],
      $fieldDefinition,
      [],
      'label',
      'view_mode',
      [],
      $this->pluginManagerIconPack
    );
  }

  /**
   * Tests the default settings of the formatter.
   */
  public function testDefaultSettings(): void {
    $expected = [
      'icon_settings' => [],
    ];
    $this->assertEquals($expected, $this->formatter->defaultSettings());
  }

  /**
   * Tests the settings form.
   */
  public function testSettingsForm(): void {
    $form = [];
    $form_state = $this->getMockBuilder('Drupal\Core\Form\FormState')
      ->disableOriginalConstructor()
      ->getMock();

    $form = $this->formatter->settingsForm($form, $form_state);

    $this->assertArrayHasKey('icon_settings', $form);
    $this->assertSame('validateSettings', $form['icon_settings']['#element_validate'][0][1]);
  }

  /**
   * Tests the settings summary.
   */
  public function testSettingsSummary(): void {
    $this->formatter->setSetting('icon_settings', ['foo' => 'bar']);
    $summary = $this->formatter->settingsSummary();
    /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $summary */
    $this->assertEquals('Specific icon settings saved', $summary[0]->getUntranslatedString());
  }

  /**
   * Tests the settings summary.
   */
  public function testSettingsSummaryEmpty(): void {
    $this->formatter->setSetting('icon_settings', []);
    $summary = $this->formatter->settingsSummary();
    $this->assertEmpty($summary);
  }

}
