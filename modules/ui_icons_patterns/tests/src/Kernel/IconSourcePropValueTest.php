<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_patterns\Plugin\Derivative\FieldIconSourceDeriver;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\FieldIconSource;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\IconRenderableSource;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\IconSource;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\LinkIconSource;
use Drupal\ui_patterns\SourceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the value each icon source gives to a prop or a slot.
 *
 * Icon settings are stored keyed by pack id, while core renders an icon with
 * flat settings: a prop value keeps the settings of its own pack only.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconSource::class)]
#[CoversClass(IconRenderableSource::class)]
#[CoversClass(FieldIconSource::class)]
#[CoversClass(FieldIconSourceDeriver::class)]
#[CoversClass(LinkIconSource::class)]
#[Group('ui_icons')]
class IconSourcePropValueTest extends KernelTestBase {

  private const ICON_ID = 'test_svg:foo';

  private const KEYED_SETTINGS = [
    'test_svg' => ['size' => 99],
    'test_path' => ['width' => 10],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'link',
    'system',
    'text',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_patterns',
    'ui_icons_test',
    'ui_patterns',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('state')->set('entity_test.additional_base_field_definitions', [
      // The source deriver skips a field storage without a label.
      'icon' => BaseFieldDefinition::create('ui_icon')->setName('icon')->setLabel('Icon'),
      'link' => BaseFieldDefinition::create('link')->setName('link')->setLabel('Link')->setCardinality(2),
    ]);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
  }

  /**
   * Tests the icon source gives the icon data with its own pack settings.
   */
  public function testIconSource(): void {
    $this->assertSame(
      ['pack_id' => 'test_svg', 'icon_id' => 'foo', 'settings' => ['size' => 99]],
      $this->createSource('icon', ['value' => ['target_id' => self::ICON_ID, 'settings' => self::KEYED_SETTINGS]])->getPropValue(),
    );
    $this->assertSame(
      ['pack_id' => 'test_svg', 'icon_id' => 'foo', 'settings' => []],
      $this->createSource('icon', ['value' => ['icon_id' => self::ICON_ID]])->getPropValue(),
    );
    $this->assertNull($this->createSource('icon', ['value' => NULL])->getPropValue());
    $this->assertNull($this->createSource('icon', ['value' => ['target_id' => 'foo']])->getPropValue());
  }

  /**
   * Tests the slot source gives an icon render array.
   */
  public function testIconRenderableSource(): void {
    $build = $this->createSource('icon_renderable', ['value' => ['target_id' => self::ICON_ID, 'settings' => self::KEYED_SETTINGS]])->getPropValue();
    $this->assertSame('icon', $build['#type']);
    $this->assertSame('test_svg', $build['#pack_id']);
    $this->assertSame('foo', $build['#icon_id']);
    $this->assertSame(['size' => 99], $build['#settings']);

    $this->assertSame([], $this->createSource('icon_renderable', ['value' => NULL])->getPropValue());
  }

  /**
   * Tests a source is derived for each icon field only.
   */
  public function testFieldIconSourceDeriver(): void {
    $definitions = $this->container->get('plugin.manager.ui_patterns_source')->getDefinitions();

    $this->assertArrayHasKey('field_icon:entity_test:icon:ui_icon', $definitions);
    $definition = $definitions['field_icon:entity_test:icon:ui_icon'];
    $this->assertSame(['icon', 'slot'], $definition['prop_types']);
    $this->assertContains('field_granularity:item', $definition['context_requirements']);

    $this->assertSame(['field_icon:entity_test:icon:ui_icon'], array_values(array_filter(
      array_keys($definitions),
      static fn (string $id): bool => str_starts_with($id, 'field_icon:entity_test:'),
    )));
  }

  /**
   * Tests the field source gives icon data to a prop, a render array to a slot.
   */
  public function testFieldIconSource(): void {
    $entity = EntityTest::create([
      'icon' => ['target_id' => self::ICON_ID, 'settings' => self::KEYED_SETTINGS],
    ]);
    $entity->save();

    $this->assertSame(
      ['pack_id' => 'test_svg', 'icon_id' => 'foo', 'settings' => ['size' => 99]],
      $this->createFieldIconSource($entity, 'icon')->getPropValue(),
    );

    $build = $this->createFieldIconSource($entity, 'slot')->getPropValue();
    $this->assertSame('icon', $build['#type']);
    $this->assertSame('test_svg', $build['#pack_id']);
    $this->assertSame('foo', $build['#icon_id']);
    $this->assertSame(['size' => 99], $build['#settings']);

    $empty = EntityTest::create();
    $empty->save();
    $this->assertNull($this->createFieldIconSource($empty, 'icon')->getPropValue());
    $this->assertSame([], $this->createFieldIconSource($empty, 'slot')->getPropValue());
  }

  /**
   * Tests the link source gives the icon data with its own pack settings.
   */
  public function testLinkIconSource(): void {
    $entity = EntityTest::create([
      'link' => [
        [
          'uri' => 'internal:/',
          'options' => [
            'icon' => ['target_id' => self::ICON_ID, 'settings' => self::KEYED_SETTINGS],
          ],
        ],
        ['uri' => 'internal:/'],
      ],
    ]);

    $this->assertSame(
      ['pack_id' => 'test_svg', 'icon_id' => 'foo', 'settings' => ['size' => 99]],
      $this->createLinkIconSource($entity, 0)->getPropValue(),
    );
    $this->assertSame([], $this->createLinkIconSource($entity, 1)->getPropValue());
  }

  /**
   * Creates a source plugin with the given settings.
   */
  private function createSource(string $source_id, array $settings, array $configuration = []): SourceInterface {
    return $this->container->get('plugin.manager.ui_patterns_source')
      ->createInstance($source_id, ['settings' => $settings] + $configuration);
  }

  /**
   * Creates the field icon source for a prop of the given type.
   */
  private function createFieldIconSource(EntityTest $entity, string $prop_type_id): SourceInterface {
    $prop_type = $this->container->get('plugin.manager.ui_patterns_prop_type')->createInstance($prop_type_id);

    return $this->createSource('field_icon:entity_test:icon:ui_icon', [], [
      'prop_definition' => ['ui_patterns' => ['type_definition' => $prop_type]],
      'contexts' => ['entity' => EntityContext::fromEntity($entity)],
    ]);
  }

  /**
   * Creates the link icon source for a link field item.
   */
  private function createLinkIconSource(EntityTest $entity, int $delta): SourceInterface {
    return $this->createSource('link_icon', [], [
      'contexts' => [
        'ui_patterns:field:items' => new Context(new ContextDefinition('any'), $entity->get('link')),
        'ui_patterns:field:index' => new Context(new ContextDefinition('integer'), $delta),
      ],
    ]);
  }

}
