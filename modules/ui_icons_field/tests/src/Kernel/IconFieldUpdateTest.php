<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update adding the `settings` column to `ui_icon` fields.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversFunction('ui_icons_field_update_11201')]
#[Group('ui_icons')]
class IconFieldUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'system',
    'user',
    'entity_test',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * Tests the column is added and the stored schema matches the field type.
   */
  #[DataProvider('providerSettingsColumnUpdate')]
  public function testSettingsColumnUpdate(string $entity_type_id, array $tables): void {
    $this->installEntitySchema($entity_type_id);
    FieldStorageConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => $entity_type_id,
      'type' => 'ui_icon',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => $entity_type_id,
      'bundle' => $entity_type_id,
    ])->save();
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $this->assertSame($tables, $storage->getTableMapping()->getAllFieldTableNames('field_icon'));

    // Simulate a site installed before the column existed.
    $key_value = \Drupal::keyValue('entity.storage_schema.sql');
    $schema_key = $entity_type_id . '.field_schema_data.field_icon';
    $installed_schema = $key_value->get($schema_key);
    $stored_schema = $installed_schema;
    $db_schema = \Drupal::database()->schema();
    foreach ($tables as $table) {
      unset($stored_schema[$table]['fields']['field_icon_settings']);
      $db_schema->dropField($table, 'field_icon_settings');
    }
    $key_value->set($schema_key, $stored_schema);

    // Kernel tests always list uninstalled entity types as changes, so only
    // look at this field.
    $update_manager = \Drupal::entityDefinitionUpdateManager();
    $this->assertArrayHasKey('field_icon', $update_manager->getChangeList()[$entity_type_id]['field_storage_definitions'] ?? []);

    \Drupal::moduleHandler()->loadInclude('ui_icons_field', 'install');
    $message = (string) ui_icons_field_update_11201();

    foreach ($tables as $table) {
      $this->assertTrue($db_schema->fieldExists($table, 'field_icon_settings'), $table);
    }
    $this->assertSame($installed_schema, $key_value->get($schema_key));
    $this->assertArrayNotHasKey('field_icon', $update_manager->getChangeList()[$entity_type_id]['field_storage_definitions'] ?? []);
    $this->assertSame('Added the icon settings column to 1 icon field(s).', $message);
    // Nothing left to add: a second run is a no-op.
    $this->assertSame('Added the icon settings column to 0 icon field(s).', (string) ui_icons_field_update_11201());

    $settings = ['test_settings' => ['width' => 64]];
    $entity = $storage->create([
      'field_icon' => ['target_id' => 'test_settings:foo', 'settings' => $settings],
    ]);
    $entity->save();
    $this->assertSame($settings, $storage->loadUnchanged($entity->id())->get('field_icon')->settings);
  }

  /**
   * Data provider for ::testSettingsColumnUpdate().
   */
  public static function providerSettingsColumnUpdate(): array {
    return [
      'not revisionable' => ['entity_test', ['entity_test__field_icon']],
      'revisionable' => ['entity_test_rev', ['entity_test_rev__field_icon', 'entity_test_rev_revision__field_icon']],
    ];
  }

  /**
   * Tests a single-value base field, stored in its entity's shared table.
   */
  public function testBaseFieldSettingsColumnUpdate(): void {
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', [
      'icon' => BaseFieldDefinition::create('ui_icon')->setName('icon'),
    ]);
    $this->installEntitySchema('entity_test');
    $entity = EntityTest::create(['icon' => ['target_id' => 'test_path:foo']]);
    $entity->save();
    $key_value = \Drupal::keyValue('entity.storage_schema.sql');
    $installed_schema = $key_value->get('entity_test.field_schema_data.icon');

    // Simulate a site installed before the column existed. A shared table names
    // the only column of a field after the field. Unlike a field storage config
    // entity, a base field definition is stored with the schema it had then.
    $old_schema = $installed_schema;
    $old_schema['entity_test']['fields'] = ['icon' => $installed_schema['entity_test']['fields']['icon__target_id']];
    array_walk_recursive($old_schema['entity_test']['indexes'], function (&$index_column): void {
      $index_column = $index_column === 'icon__target_id' ? 'icon' : $index_column;
    });
    $key_value->set('entity_test.field_schema_data.icon', $old_schema);
    $db_schema = \Drupal::database()->schema();
    $db_schema->dropField('entity_test', 'icon__settings');
    $db_schema->changeField('entity_test', 'icon__target_id', 'icon', $old_schema['entity_test']['fields']['icon']);
    $repository = $this->container->get('entity.last_installed_schema.repository');
    $definition = clone $repository->getLastInstalledFieldStorageDefinitions('entity_test')['icon'];
    $definition_schema = $definition->getSchema();
    unset($definition_schema['columns']['settings']);
    (new \ReflectionProperty($definition, 'schema'))->setValue($definition, $definition_schema);
    $repository->setLastInstalledFieldStorageDefinition($definition);

    $update_manager = \Drupal::entityDefinitionUpdateManager();
    $this->assertArrayHasKey('icon', $update_manager->getChangeList()['entity_test']['field_storage_definitions'] ?? []);

    \Drupal::moduleHandler()->loadInclude('ui_icons_field', 'install');
    $this->assertSame('Added the icon settings column to 1 icon field(s).', (string) ui_icons_field_update_11201());

    $this->assertFalse($db_schema->fieldExists('entity_test', 'icon'));
    $this->assertEquals($installed_schema, $key_value->get('entity_test.field_schema_data.icon'));
    $this->assertArrayNotHasKey('icon', $update_manager->getChangeList()['entity_test']['field_storage_definitions'] ?? []);

    // The stored icon survives the column rename, and settings now save.
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test');
    $entity = $storage->loadUnchanged($entity->id());
    $this->assertSame('test_path:foo', $entity->icon->target_id);
    $settings = ['test_path' => ['width' => 10]];
    $entity->icon = ['target_id' => 'test_path:foo', 'settings' => $settings];
    $entity->save();
    $this->assertSame($settings, $storage->loadUnchanged($entity->id())->icon->settings);
  }

}
