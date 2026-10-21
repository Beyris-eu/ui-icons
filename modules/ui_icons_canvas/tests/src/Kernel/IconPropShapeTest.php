<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_canvas\Kernel;

use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas\PropShape\PropShapeRepositoryInterface;
use Drupal\canvas\PropShape\StorablePropShape;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_canvas\Hook\UiIconsCanvasHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the standard icon prop shape is storable in Drupal Canvas.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsCanvasHooks::class)]
#[Group('ui_icons')]
class IconPropShapeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    'canvas',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_canvas',
    'ui_icons_test',
    'ui_icons_canvas_test',
  ];

  /**
   * The prop shape repository under test.
   */
  private PropShapeRepositoryInterface $propShapeRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->propShapeRepository = $this->container->get(PropShapeRepositoryInterface::class);
  }

  /**
   * Every spelling of the standard icon shape resolves to `ui_icon` storage.
   *
   * `settings` is optional in the shape, and is mapped only for the components
   * that declare it — which is also what switches the widget's icon pack
   * settings sub-form on.
   */
  #[DataProvider('providerStandardIconShapes')]
  public function testStandardIconShapeIsStorable(string $component_id, array $expected_mapping, bool $expects_settings): void {
    $component = $this->container->get('plugin.manager.sdc')->createInstance($component_id);
    $prop_shape = PropShape::standardize($component->metadata->schema['properties']['icon']);

    $storable = $this->propShapeRepository->getStorablePropShape($prop_shape);
    $this->assertInstanceOf(StorablePropShape::class, $storable);
    $this->assertInstanceOf(FieldTypeObjectPropsExpression::class, $storable->fieldTypeProp);
    $this->assertSame('ui_icon', $storable->fieldTypeProp->getFieldType());
    $this->assertSame('icon_widget', $storable->fieldWidget);

    // Assert which field type property backs each member of the icon shape.
    $mapping = array_map(
      fn (FieldTypePropExpression $expression): string => $expression->propName,
      $storable->fieldTypeProp->objectPropsToFieldTypeProps,
    );
    $this->assertSame($expected_mapping, $mapping);

    $this->assertSame(
      $expects_settings ? ['allow_settings' => TRUE] : NULL,
      $storable->fieldInstanceSettings,
    );
  }

  /**
   * Data provider for ::testStandardIconShapeIsStorable().
   */
  public static function providerStandardIconShapes(): array {
    return [
      'inline, no settings' => [
        'ui_icons_canvas_test:icon_inline',
        ['pack_id' => 'pack_id', 'icon_id' => 'icon_id'],
        FALSE,
      ],
      'inline, with settings' => [
        'ui_icons_canvas_test:icon_settings',
        ['pack_id' => 'pack_id', 'icon_id' => 'icon_id', 'settings' => 'resolved_settings'],
        TRUE,
      ],
    ];
  }

  /**
   * An object with members the `ui_icon` field type cannot store is no icon.
   *
   * Matching it would drop those members, so Canvas must report the prop as
   * unsupported instead.
   */
  #[DataProvider('providerNotIconShapes')]
  public function testObjectIsNotAnIcon(string $component_id): void {
    $component = $this->container->get('plugin.manager.sdc')->createInstance($component_id);
    $prop_shape = PropShape::standardize($component->metadata->schema['properties']['icon']);

    $this->assertNull($this->propShapeRepository->getStorablePropShape($prop_shape));
  }

  /**
   * Data provider for ::testObjectIsNotAnIcon().
   */
  public static function providerNotIconShapes(): array {
    return [
      'extra member' => ['ui_icons_canvas_test:icon_extra'],
      'settings not an object' => ['ui_icons_canvas_test:icon_settings_string'],
    ];
  }

  /**
   * The object shape round-trips through the `ui_icon` field type's storage.
   */
  public function testValueRoundTrip(): void {
    $component = $this->container->get('plugin.manager.sdc')->createInstance('ui_icons_canvas_test:icon_inline');
    $prop_shape = PropShape::standardize($component->metadata->schema['properties']['icon']);
    $storable = $this->propShapeRepository->getStorablePropShape($prop_shape);
    \assert($storable instanceof StorablePropShape);

    $prop_source = $storable->toStaticPropSource()
      ->withValue(['pack_id' => 'test_path', 'icon_id' => 'foo']);
    \assert($prop_source instanceof StaticPropSource);

    // Storage keeps the concatenated icon id; no `settings` key is written for
    // a prop that does not ask for settings.
    $this->assertSame(['target_id' => 'test_path:foo'], $prop_source->toArray()['value']);

    // …but the component receives the standard object shape.
    $this->assertSame(
      ['pack_id' => 'test_path', 'icon_id' => 'foo'],
      $prop_source->evaluate(NULL, FALSE)->value,
    );
  }

  /**
   * Icon pack settings round-trip, including the form element's pack keying.
   */
  public function testSettingsRoundTrip(): void {
    $component = $this->container->get('plugin.manager.sdc')->createInstance('ui_icons_canvas_test:icon_settings');
    $prop_shape = PropShape::standardize($component->metadata->schema['properties']['icon']);
    $storable = $this->propShapeRepository->getStorablePropShape($prop_shape);
    \assert($storable instanceof StorablePropShape);

    $expected = [
      'pack_id' => 'test_settings',
      'icon_id' => 'foo',
      'settings' => ['width' => 64, 'title' => 'Hello'],
    ];

    // Settings are stored keyed by icon pack — the structure `icon_widget` and
    // `ui_icons.icon_value` config schema both use.
    $flat = $storable->toStaticPropSource()->withValue($expected);
    $this->assertEquals([
      'target_id' => 'test_settings:foo',
      'settings' => ['test_settings' => ['width' => 64, 'title' => 'Hello']],
    ], $flat->toArray()['value']);
    // …but the component receives the flat object the standard shape promises.
    $this->assertSame($expected, $flat->evaluate(NULL, FALSE)->value);

    // The already pack-keyed spelling the widget submits is accepted as-is.
    $pack_keyed = $storable->toStaticPropSource()->withValue([
      'target_id' => 'test_settings:foo',
      'settings' => ['test_settings' => ['width' => 64, 'title' => 'Hello']],
    ]);
    $this->assertSame($expected, $pack_keyed->evaluate(NULL, FALSE)->value);

    // Settings belonging to another icon pack never leak into the prop.
    $other_pack = $storable->toStaticPropSource()->withValue([
      'target_id' => 'test_settings:foo',
      'settings' => ['test_path' => ['width' => 1]],
    ]);
    $this->assertSame([
      'pack_id' => 'test_settings',
      'icon_id' => 'foo',
      'settings' => [],
    ], $other_pack->evaluate(NULL, FALSE)->value);

    // Nor do settings the pack does not declare, or that are not scalars.
    $undeclared = $storable->toStaticPropSource()->withValue([
      'pack_id' => 'test_settings',
      'icon_id' => 'foo',
      'settings' => ['width' => 64, 'attributes' => 'x', 'title' => ['#markup' => 'x']],
    ]);
    $this->assertSame([
      'pack_id' => 'test_settings',
      'icon_id' => 'foo',
      'settings' => ['width' => 64],
    ], $undeclared->evaluate(NULL, FALSE)->value);
  }

}
