<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ui_icons\Hook\UiIconsHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the config schema of the icon pack settings.
 *
 * Saving fails when the schema is missing or does not match the value, as
 * kernel tests check the config schema strictly.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsHooks::class)]
#[Group('ui_icons')]
class IconPackSettingsSchemaTest extends KernelTestBase {

  private const DISPLAY = 'core.entity_view_display.entity_test.entity_test.default';

  private const SETTINGS = 'content.field_icon.settings.icon_settings';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'link',
    'system',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
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
      'field_name' => 'field_icon',
      'entity_type' => 'entity_test',
      'type' => 'ui_icon',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Test the schema generated for a pack without a hand-written one.
   */
  public function testGeneratedSchema(): void {
    // Values as submitted by the settings form, cast on save by the schema.
    $this->saveIconSettings([
      'test_settings_no_schema' => [
        'size' => '32',
        'ratio' => '1.5',
        'rounded' => '1',
        'label' => 'Foo',
      ],
      'test_no_settings' => [],
    ]);

    // Packs are sorted by id on save, as keys of a mapping.
    $this->assertSame([
      'test_no_settings' => [],
      'test_settings_no_schema' => [
        'size' => 32,
        'ratio' => 1.5,
        'rounded' => TRUE,
        'label' => 'Foo',
      ],
    ], $this->config(self::DISPLAY)->get(self::SETTINGS));

    $size = $this->getSettingDefinition('test_settings_no_schema.size');
    $this->assertSame('integer', $size->getDataType());
    $this->assertSame('Size', $size->getLabel());
  }

  /**
   * Test a hand-written pack schema is kept.
   */
  public function testHandWrittenSchemaKept(): void {
    $this->saveIconSettings([
      'test_svg_sprite' => ['alt' => 'Foo'],
      'test_settings_no_schema' => ['label' => 'Foo'],
    ]);

    // The hand-written schema declares `alt` as a translatable label, the
    // generated one would be a string.
    $this->assertSame('label', $this->getSettingDefinition('test_svg_sprite.alt')->getDataType());
    $this->assertSame('string', $this->getSettingDefinition('test_settings_no_schema.label')->getDataType());
  }

  /**
   * Test packs of a newly installed module get a schema.
   */
  public function testSchemaRebuiltOnModuleInstall(): void {
    $definition = $this->container->get('config.typed')->getDefinition('ui_icons.icon_pack_settings');
    $this->assertArrayNotHasKey('test_font', $definition['mapping']);

    $this->container->get('module_installer')->install(['ui_icons_font_test']);

    $this->saveIconSettings([
      'test_font' => ['size' => '32', 'color' => '#000000'],
    ]);
    $this->assertSame(
      ['test_font' => ['size' => 32, 'color' => '#000000']],
      $this->config(self::DISPLAY)->get(self::SETTINGS),
    );
  }

  /**
   * Save the icon formatter settings on the test display.
   */
  private function saveIconSettings(array $icon_settings): void {
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_icon', [
      'type' => 'icon_formatter',
      'settings' => ['icon_settings' => $icon_settings],
    ])->save();
  }

  /**
   * Get the typed config definition of a saved icon setting.
   */
  private function getSettingDefinition(string $path): DataDefinitionInterface {
    return $this->container->get('config.typed')
      ->get(self::DISPLAY)
      ->get(self::SETTINGS . '.' . $path)
      ->getDataDefinition();
  }

}
