<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit\Plugin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget;

/**
 * Tests the IconWidget field class.
 *
 * @group ui_icons
 */
class IconWidgetTest extends IconUnitTestCase {

  /**
   * The field widget under test.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget
   */
  private IconWidget $widget;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  private FieldDefinitionInterface $fieldDefinition;

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
    $this->fieldDefinition = $this->createMock(FieldDefinitionInterface::class);

    $this->widget = new IconWidget(
      'icon_widget',
      [],
      $this->fieldDefinition,
      [],
      [],
      $this->pluginManagerIconPack
    );
  }

  /**
   * Tests the default settings of the widget.
   */
  public function testDefaultSettings(): void {
    $expected = [
      'allowed_icon_pack' => [],
      'show_settings' => FALSE,
    ];
    $this->assertEquals($expected, $this->widget->defaultSettings());
  }

  /**
   * Tests the settings form.
   */
  public function testSettingsForm(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $form = $this->widget->settingsForm($form, $form_state);

    $this->assertArrayHasKey('allowed_icon_pack', $form);
    $this->assertArrayHasKey('show_settings', $form);
  }

  /**
   * Tests the settings summary.
   */
  public function testSettingsSummary(): void {
    $this->widget->setSetting('allowed_icon_pack', ['icon_pack_1' => 'icon_pack_1']);
    $this->widget->setSetting('show_settings', TRUE);

    $summary = $this->widget->settingsSummary();

    $this->assertStringContainsString('With Icon set:', $summary[0]->getUntranslatedString());
    $this->assertSame('Icon settings enabled', $summary[1]->getUntranslatedString());

    $this->widget->setSetting('allowed_icon_pack', []);

    $summary = $this->widget->settingsSummary();

    $this->assertStringContainsString('All icon sets available for selection', $summary[0]->getUntranslatedString());
  }

  /**
   * Tests the massageFormValues method.
   */
  public function testMassageFormValues(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $values = [];

    // Invalid icon.
    $values[]['value'] = [
      'icon' => NULL,
    ];

    // Icon without settings.
    $values[]['value'] = [
      'icon' => $this->createMockIcon([
        'icon_pack_id' => 'foo',
        'icon_id' => 'bar',
      ]),
      'settings' => [],
    ];

    // Icon with settings.
    $values[]['value'] = [
      'icon' => $this->createMockIcon([
        'icon_pack_id' => 'foo',
        'icon_id' => 'bar',
      ]),
      'settings' => ['baz' => 'qux'],
    ];

    $actual = $this->widget->massageFormValues($values, [], $form_state);

    foreach ($actual as $delta => $value) {
      if (NULL === $values[$delta]['value']['icon']) {
        $this->assertNull($value['target_id']);
        $this->assertEmpty($value['settings']);
      }
      else {
        $this->assertInstanceOf('Drupal\ui_icons\IconDefinitionInterface', $value['value']['icon']);
        $this->assertEquals('foo:bar', $value['target_id']);
        $this->assertEquals($values[$delta]['value']['settings'], $value['settings']);
      }
    }
  }

}
