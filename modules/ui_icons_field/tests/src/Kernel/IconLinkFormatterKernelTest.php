<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\ui_icons_field\IconFieldHelpers;
use Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconLinkFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconLinkFormatter class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconLinkFormatter::class)]
#[CoversClass(IconFieldHelpers::class)]
#[Group('ui_icons')]
class IconLinkFormatterKernelTest extends KernelTestBase {

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
   * Data provider for ::testViewElements().
   */
  public static function iconDisplayProvider(): array {
    return [
      'icon before' => ['before', 'foo Example'],
      'icon after' => ['after', 'Example foo'],
    ];
  }

  /**
   * Tests the icon is added to the link title with its library.
   */
  #[DataProvider('iconDisplayProvider')]
  public function testViewElements(string $icon_display, string $expected): void {
    $build = $this->viewLink([
      'icon' => ['target_id' => 'test_library:foo'],
      'icon_display' => $icon_display,
    ]);

    $this->assertSame($expected, (string) $build[0]['#title']);
    $this->assertContains('ui_icons_test/test_library', $build[0]['#attached']['library'] ?? []);
    $this->assertTrue($build[0]['#url']->getOption('ui_icons_processed'));
  }

  /**
   * Tests the icon only display keeps the icon as a render array.
   */
  public function testViewElementsIconOnly(): void {
    $build = $this->viewLink([
      'icon' => ['target_id' => 'test_library:foo'],
    ]);

    $this->assertSame('icon', $build[0]['#title']['#type']);
    $this->assertSame('test_library', $build[0]['#title']['#pack_id']);
    $this->assertSame('foo', $build[0]['#title']['#icon_id']);
  }

  /**
   * Tests a link without icon is left as is.
   */
  public function testViewElementsWithoutIcon(): void {
    $build = $this->viewLink([]);

    $this->assertSame('Example', $build[0]['#title']);
    $this->assertNull($build[0]['#url']->getOption('ui_icons_processed'));
  }

  /**
   * Tests ui_icons_menu does not add the icon a second time.
   */
  public function testViewElementsNotProcessedTwice(): void {
    $this->enableModules(['ui_icons_menu']);

    $build = $this->viewLink([
      'icon' => ['target_id' => 'test_minimal:foo'],
      'icon_display' => 'before',
    ]);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build[0]);

    $this->assertSame(1, substr_count($html, '<img'), $html);
  }

  /**
   * Data provider for ::testValidateSettings().
   */
  public static function settingsFormValuesProvider(): array {
    $settings = [
      'trim_length' => '40',
      'url_only' => 0,
      'url_plain' => 0,
      'rel' => 'nofollow',
      'target' => '_blank',
      'icon_display' => 'before',
      'test_settings' => ['width' => 64],
      'icon_settings' => '',
    ];

    return [
      'field ui' => [
        [
          'fields' => [
            'link' => [
              'type' => 'icon_link_formatter',
              'settings_edit_form' => ['settings' => $settings],
            ],
          ],
        ],
      ],
      'views' => [
        [
          'options' => [
            'type' => 'icon_link_formatter',
            'settings' => $settings,
          ],
        ],
      ],
      'layout builder' => [
        [
          'settings' => [
            'formatter' => [
              'type' => 'icon_link_formatter',
              'settings' => $settings,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Tests only icon pack settings are saved in icon_settings.
   */
  #[DataProvider('settingsFormValuesProvider')]
  public function testValidateSettings(array $values): void {
    $formatter = $this->container->get('plugin.manager.field.formatter')->createInstance('icon_link_formatter', [
      'field_definition' => $this->baseField,
      'settings' => [],
      'label' => '',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);
    $form_state = new FormState();
    $form_state->setValues($values);
    $element = ['#parents' => ['saved_icon_settings']];
    $complete_form = [];

    $formatter->validateSettings($element, $form_state, $complete_form);

    $this->assertSame(['test_settings' => ['width' => 64]], $form_state->getValue('saved_icon_settings'));
  }

  /**
   * Build the icon link formatter output of a link.
   *
   * @param array $options
   *   The link options, holding the icon.
   *
   * @return array
   *   The field render array.
   */
  private function viewLink(array $options): array {
    $entity = EntityTest::create([
      'name' => 'sample entity',
      'link' => [
        'uri' => 'https://example.com',
        'title' => 'Example',
        'options' => $options,
      ],
    ]);

    return $entity->get('link')->view(['type' => 'icon_link_formatter']);
  }

}
