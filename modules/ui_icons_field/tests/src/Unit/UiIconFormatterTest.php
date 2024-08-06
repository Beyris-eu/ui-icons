<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit\Plugin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Plugin\UiIconsetManagerInterface;
use Drupal\ui_icons_field\Plugin\Field\FieldFormatter\UiIconFormatter;

/**
 * Tests the UiIconFormatter field class.
 *
 * @group ui_icons
 */
class UiIconFormatterTest extends UnitTestCase {

  /**
   * The field formatter under test.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldFormatter\UiIconFormatter
   */
  private UiIconFormatter $formatter;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  private FieldDefinitionInterface $fieldDefinition;

  /**
   * The UiIconsetManager instance.
   *
   * @var \Drupal\ui_icons\Plugin\UiIconsetManagerInterface
   */
  private UiIconsetManagerInterface $pluginManagerUiIconset;

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

    $this->pluginManagerUiIconset = $this->createMock(UiIconsetManagerInterface::class);
    $this->fieldDefinition = $this->createMock(FieldDefinitionInterface::class);

    $this->formatter = new UiIconFormatter(
      'ui_icon_formatter',
      [],
      $this->fieldDefinition,
      [],
      'label',
      'view_mode',
      [],
      $this->pluginManagerUiIconset
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
    $form_state = $this->createMock(FormStateInterface::class);

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
