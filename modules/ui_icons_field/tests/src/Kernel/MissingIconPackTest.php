<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconFormatter;
use Drupal\ui_icons_field\Plugin\Field\FieldType\IconType;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests icons and settings left behind by an icon pack removed from the site.
 *
 * Config does not depend on the extension providing an icon pack, so
 * uninstalling it leaves its pack id in icon values and allowed pack lists.
 * Nothing may break: the missing pack only offers and renders nothing.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconFormatter::class)]
#[CoversClass(IconType::class)]
#[CoversClass(IconWidget::class)]
#[Group('ui_icons')]
class MissingIconPackTest extends KernelTestBase {

  /**
   * A pack id no extension provides.
   */
  private const MISSING_PACK_ID = 'missing_pack';

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
   * The icon base field of the test entity.
   */
  private BaseFieldDefinition $baseField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->baseField = BaseFieldDefinition::create('ui_icon')
      ->setName('icon')
      ->setSetting('allowed_icon_pack', [self::MISSING_PACK_ID => self::MISSING_PACK_ID]);
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', [
      'icon' => $this->baseField,
    ]);

    $this->installEntitySchema('entity_test');
    $this->installConfig(['system']);
  }

  /**
   * Tests a stored icon of a missing pack saves and renders nothing.
   */
  public function testStoredIconRendersNothing(): void {
    $entity = EntityTest::create(['name' => 'Missing pack']);
    $entity->icon = ['target_id' => self::MISSING_PACK_ID . ':foo'];
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertSame(self::MISSING_PACK_ID . ':foo', $entity->icon->target_id);

    $renderer = $this->container->get('renderer');
    $build = $entity->get('icon')->view(['type' => 'icon_formatter']);
    $output = (string) $renderer->renderInIsolation($build);
    $this->assertStringNotContainsString('<img', $output);
    $this->assertStringNotContainsString('<svg', $output);

    // The same formatter does render an icon of an existing pack.
    $entity->icon = ['target_id' => 'test_path:foo'];
    $build = $entity->get('icon')->view(['type' => 'icon_formatter']);
    $this->assertStringContainsString('<img', (string) $renderer->renderInIsolation($build));
  }

  /**
   * Tests settings of a missing pack keep their pack keying through a load.
   */
  public function testSettingsOfMissingPackKeepTheirKeying(): void {
    $settings = [self::MISSING_PACK_ID => ['width' => 10]];
    $entity = EntityTest::create(['name' => 'Missing pack']);
    $entity->icon = ['target_id' => self::MISSING_PACK_ID . ':foo', 'settings' => $settings];
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertSame($settings, $entity->icon->settings);
    // No pack declares them, so none apply.
    $this->assertSame([], $entity->icon->resolved_settings);
  }

  /**
   * Tests an allowed pack list naming a missing pack offers no icon from it.
   */
  public function testAllowedMissingPackOffersNothing(): void {
    $search = $this->container->get('ui_icons.search');
    $this->assertSame([], $search->search('foo', [self::MISSING_PACK_ID]));

    $icons = $search->search('foo', [self::MISSING_PACK_ID, 'test_path']);
    $this->assertNotEmpty($icons);
    foreach ($icons as $icon_full_id) {
      $this->assertStringStartsWith('test_path:', $icon_full_id);
    }

    $entity = EntityTest::create(['name' => 'Missing pack']);
    $form = ['#parents' => []];
    $element = $this->container->get('plugin.manager.field.widget')->createInstance('icon_widget', [
      'field_definition' => $this->baseField,
      'settings' => [],
      'third_party_settings' => [],
    ])->formElement($entity->icon, 0, ['#description' => ''], $form, new FormState());
    $this->assertSame([self::MISSING_PACK_ID => self::MISSING_PACK_ID], $element['value']['#allowed_icon_pack']);
  }

}
