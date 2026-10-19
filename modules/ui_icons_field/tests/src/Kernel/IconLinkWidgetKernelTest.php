<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconLinkWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconLinkWidget class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconLinkWidget::class)]
#[Group('ui_icons')]
class IconLinkWidgetKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'link',
    'system',
    'user',
    'entity_test',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * The base field definition.
   *
   * @var \Drupal\Core\Field\BaseFieldDefinition
   */
  private BaseFieldDefinition $baseField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->baseField = BaseFieldDefinition::create('link')
      ->setName('link');
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', [
      'link' => $this->baseField,
    ]);

    $this->installEntitySchema('entity_test');
    $this->installConfig(['system']);
  }

  /**
   * Tests the summary keeps the link and the icon lines.
   */
  public function testSettingsSummary(): void {
    // Older configs hold unchecked packs as zeros, they must still be read.
    $summary = array_map('strval', $this->createWidget([
      'allowed_icon_pack' => ['test_path' => 'test_path', 'test_svg' => 0],
    ])->settingsSummary());
    $label = $this->container->get('plugin.manager.icon_pack')->listIconPackOptions()['test_path'];

    $this->assertContains('No placeholders', $summary);
    $this->assertContains('With Icon set: ' . $label, $summary);
    $this->assertContains('Selector: Autocomplete', $summary);
    $this->assertContains('Icon is required', $summary);
  }

  /**
   * Tests the maximum results show for the autocomplete selector only.
   */
  public function testSettingsMaxResult(): void {
    $widget = $this->createWidget(['icon_selector' => 'icon_picker']);
    $form = $widget->settingsForm([], new FormState());
    $summary = array_map('strval', $widget->settingsSummary());

    $this->assertSame($form['result_format']['#states'], $form['max_result']['#states'] ?? NULL);
    $this->assertEmpty(preg_grep('/^Max results/', $summary));
  }

  /**
   * Tests the picker selector falls back when ui_icons_picker is missing.
   */
  public function testFormElementPickerFallback(): void {
    $entity = EntityTest::create([
      'name' => 'sample entity',
      'link' => ['uri' => 'https://example.com'],
    ]);
    $element = [
      '#field_parents' => [],
      '#required' => FALSE,
      '#title' => 'Link',
      '#description' => '',
      '#delta' => 0,
      '#weight' => 0,
    ];
    $form = ['#parents' => []];

    $element = $this->createWidget([
      'icon_selector' => 'icon_picker',
      'allowed_icon_pack' => ['test_path' => 'test_path', 'test_svg' => 0],
    ])->formElement($entity->get('link'), 0, $element, $form, new FormState());

    $this->assertSame('icon_autocomplete', $element['icon']['#type']);
    $this->assertSame('list', $element['icon']['#result_format']);
    $this->assertSame(['test_path' => 'test_path'], $element['icon']['#allowed_icon_pack']);
  }

  /**
   * Create the icon link widget.
   *
   * @param array $settings
   *   The widget settings.
   *
   * @return \Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconLinkWidget
   *   The widget.
   */
  private function createWidget(array $settings): IconLinkWidget {
    return $this->container->get('plugin.manager.field.widget')->createInstance('icon_link_widget', [
      'field_definition' => $this->baseField,
      'settings' => $settings + IconLinkWidget::defaultSettings(),
      'third_party_settings' => [],
    ]);
  }

}
