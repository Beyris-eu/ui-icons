<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the config schema of the icon UI Patterns source.
 *
 * @internal
 */
#[Group('ui_icons')]
#[Group('ui_icons_patterns')]
class IconSourceConfigSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'system',
    'ui_icons',
    'ui_icons_patterns',
    'ui_icons_patterns_test',
    'ui_icons_test',
    'ui_patterns',
    'ui_patterns_field_formatters',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'entity_test'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Test the schema of the icon source values.
   *
   * Saving fails when the schema is missing or does not match the value, as
   * kernel tests check the config schema strictly.
   */
  #[DataProvider('providerIconSourceValue')]
  public function testIconSourceSchema(mixed $value): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_reference', [
      'type' => 'ui_patterns_component',
      'settings' => [
        'ui_patterns' => [
          'component_id' => 'ui_icons_patterns_test:icon_test',
          'props' => [
            'icon1' => [
              'source_id' => 'icon',
              'source' => ['value' => $value],
            ],
          ],
        ],
      ],
    ]);
    $display->save();

    $props = $display->getComponent('field_reference')['settings']['ui_patterns']['props'];
    $this->assertSame($value, $props['icon1']['source']['value']);
  }

  /**
   * Provides icon source values.
   */
  public static function providerIconSourceValue(): array {
    return [
      // The icon autocomplete element sets the value to NULL when nothing is
      // selected.
      'no icon selected' => [NULL],
      'icon with pack settings' => [
        [
          'target_id' => 'test_settings:foo',
          'settings' => [
            'test_settings' => [
              'width' => 32,
              'height' => 33,
              'title' => 'Test title',
            ],
          ],
        ],
      ],
    ];
  }

}
