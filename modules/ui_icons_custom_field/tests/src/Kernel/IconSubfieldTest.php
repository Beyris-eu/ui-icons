<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_custom_field\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_custom_field\Plugin\CustomField\FieldFormatter\IconFormatter;
use Drupal\ui_icons_custom_field\Plugin\CustomField\FieldType\IconType;
use Drupal\ui_icons_custom_field\Plugin\CustomField\FieldWidget\IconAutocompleteWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the icon subfield of a custom field.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconType::class)]
#[CoversClass(IconAutocompleteWidget::class)]
#[CoversClass(IconFormatter::class)]
#[Group('ui_icons')]
class IconSubfieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'entity_test',
    'field',
    'file',
    'filter',
    'image',
    'link',
    'system',
    'user',
    'ui_icons',
    'ui_icons_custom_field',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'system']);

    FieldStorageConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'entity_test',
      'type' => 'custom',
      'settings' => [
        'columns' => [
          'label' => ['name' => 'label', 'type' => 'string', 'length' => 255],
          'icon' => ['name' => 'icon', 'type' => 'ui_icon'],
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Links',
      'settings' => [
        'field_settings' => [
          'label' => [],
          'icon' => [],
        ],
      ],
    ])->save();

    // Saving the widget settings checks their schema.
    EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'field_links' => [
          'type' => 'custom_stacked',
          'weight' => 0,
          'region' => 'content',
          'settings' => [
            'fields' => [
              'icon' => [
                'type' => 'ui_icon_autocomplete',
                'allowed_icon_pack' => ['test_path'],
              ],
            ],
          ],
          'third_party_settings' => [],
        ],
      ],
    ])->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'field_links' => [
          'type' => 'custom_formatter',
          'weight' => 0,
          'region' => 'content',
          'settings' => ['fields' => []],
          'label' => 'hidden',
          'third_party_settings' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Tests the subfield stores the icon id and renders the icon.
   */
  public function testStoreAndRender(): void {
    $entity = EntityTest::create([
      'name' => 'Icons',
      'field_links' => [['label' => 'Home', 'icon' => 'test_path:foo']],
    ]);
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertSame('test_path:foo', $entity->field_links[0]->icon);

    $build = $this->container->get('entity_type.manager')->getViewBuilder('entity_test')->view($entity);
    $output = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertStringContainsString('Home', $output);
    $this->assertStringContainsString('foo.png', $output);
  }

  /**
   * Tests the widget selects an icon within its allowed packs.
   */
  public function testWidget(): void {
    $entity = EntityTest::create(['name' => 'Icons']);
    $field = $entity->get('field_links');
    $custom_items = $this->container->get('plugin.manager.custom_field_type')->getCustomFieldItems($field->getSettings());
    $widget = $this->container->get('plugin.manager.custom_field_widget')->createInstance('ui_icon_autocomplete', [
      'settings' => ['allowed_icon_pack' => ['test_path' => 'test_path']],
    ]);

    $form = ['#parents' => []];
    $element = $widget->widget($field, 0, [], $form, new FormState(), $custom_items['icon']);
    $this->assertSame('icon_autocomplete', $element['#type']);
    $this->assertTrue($element['#return_id']);
    $this->assertSame(['test_path' => 'test_path'], $element['#allowed_icon_pack']);

    $this->assertSame('test_path:foo', $widget->massageFormValue(['target_id' => 'test_path:foo', 'settings' => []], []));
    $this->assertNull($widget->massageFormValue(['target_id' => '', 'settings' => []], []));
  }

}
