<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons\Element\IconAutocomplete;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests IconAutocomplete FormElement class.
 *
 * @group ui_icons
 */
class IconAutocompleteTest extends IconUnitTestCase {

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
    $this->container->set('plugin.manager.ui_icons_pack', $this->createMock(IconPackManagerInterface::class));
    \Drupal::setContainer($this->container);
  }

  /**
   * Test the getInfo method.
   */
  public function testGetInfo(): void {
    $iconAutocomplete = new IconAutocomplete([], 'test', 'test');
    $info = $iconAutocomplete->getInfo();

    $class = 'Drupal\ui_icons\Element\IconAutocomplete';
    $expected = [
      '#input' => TRUE,
      '#element_validate' => [
        [$class, 'validateIcon'],
      ],
      '#process' => [
        [$class, 'processIcon'],
        [$class, 'processIconAjaxForm'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#theme' => 'input__icon',
      '#theme_wrappers' => ['form_element'],
      '#allowed_icon_pack' => [],
      '#show_settings' => FALSE,
      '#default_settings' => [],
    ];

    foreach ($expected as $key => $value) {
      $this->assertArrayHasKey($key, $info);
      $this->assertSame($value, $info[$key]);
    }
  }

  /**
   * Test the processIcon method.
   */
  public function testProcessIcon(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $element = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
    ];

    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $expected = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#tree' => TRUE,
      'icon_id' => [
        '#type' => 'textfield',
        '#title' => new TranslatableMarkup('Icon'),
        '#placeholder' => new TranslatableMarkup('Start typing icon name'),
        '#title_display' => 'invisible',
        '#autocomplete_route_name' => 'ui_icons.autocomplete',
        '#required' => FALSE,
        '#size' => 55,
        '#maxlength' => 128,
        '#value' => '',
        '#error_no_message' => TRUE,
        '#limit_validation_errors' => [$element['#parents']],
      ],
    ];

    $this->assertEquals($expected, $element);

    // Test basic values and #default_value.
    $values = [
      '#size' => 22,
      '#description' => new TranslatableMarkup('Foo'),
      '#placeholder' => new TranslatableMarkup('Qux'),
      '#required' => TRUE,
      '#default_value' => 'foo:bar',
    ];
    $element += $values;

    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $expected['#description'] = $values['#description'];
    $expected['#required'] = $values['#required'];
    $expected['#default_value'] = $values['#default_value'];
    $expected['icon_id']['#size'] = $values['#size'];
    $expected['icon_id']['#placeholder'] = $values['#placeholder'];
    $expected['icon_id']['#required'] = $values['#required'];
    $expected['icon_id']['#value'] = $values['#default_value'];

    $this->assertEquals($expected, $element);

    // Test value set used before default.
    $element['#value']['icon_id'] = 'baz:qux';
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertSame('baz:qux', $element['icon_id']['#value']);

    // Test empty allowed with basic values and default value.
    $element['#allowed_icon_pack'] = [];
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertArrayNotHasKey('#autocomplete_query_parameters', $element['icon_id']);

    // Test allowed.
    $element['#allowed_icon_pack'] = ['corge', 'quux'];
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertArrayHasKey('allowed_icon_pack', $element['icon_id']['#autocomplete_query_parameters']);
    $this->assertSame('corge+quux', $element['icon_id']['#autocomplete_query_parameters']['allowed_icon_pack']);

    // Test values are cleaned on the parent element.
    $this->assertArrayNotHasKey('#size', $element);
    $this->assertArrayNotHasKey('#placeholder', $element);

    // Ensure we still have no settings.
    $this->assertArrayNotHasKey('icon_settings', $element);
  }

  /**
   * Test the processIconAjaxForm method.
   */
  public function testProcessIconAjaxForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $base_element = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#show_settings' => FALSE,
      '#settings_title' => new TranslatableMarkup('Baz'),
    ];

    $element = $base_element;
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertArrayNotHasKey('icon_settings', $element);

    // Test value without show settings.
    $element = $base_element;
    $element['#default_value'] = 'foo:bar';
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertArrayNotHasKey('icon_settings', $element);

    // Test show settings without icon id.
    $element = $base_element;
    $element['#show_settings'] = TRUE;
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertArrayNotHasKey('icon_settings', $element);

    // Test settings enabled with icon_id.
    $ui_icons_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icons_pack_plugin_manager->expects($this->once())->method('getIcon')->willReturn($this->createMockIcon());
    $ui_icons_pack_plugin_manager->expects($this->once())
      ->method('getExtractorPluginForms')
      ->with($this->anything())
      ->will($this->returnCallback(function (&$form): void {
        $form['sub_form'] = TRUE;
      }));
    $this->container->set('plugin.manager.ui_icons_pack', $ui_icons_pack_plugin_manager);

    $element = $base_element;
    $element['#show_settings'] = TRUE;
    $element['#default_value'] = 'bar:baz';
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertSame('baz/qux', $element['icon_id']['#ajax']['options']['query']['element_parents']);

    $this->assertArrayHasKey('icon_settings', $element);
    $this->assertSame('icon[foo_bar]', $element['icon_settings']['#name']);
    $this->assertSame($base_element['#settings_title'], $element['icon_settings']['#title']);

    $this->assertArrayHasKey('sub_form', $element['icon_settings']);
  }

  /**
   * Test the validateIcon method.
   *
   * @param array $element
   *   The element data.
   * @param string $icon_pack_id
   *   The icon set id.
   * @param array $values
   *   The values data.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $expected_error
   *   The expected error message or no message.
   *
   * @dataProvider providerValidateIcon
   */
  public function testValidateIcon(array $element, string $icon_pack_id, array $values, ?TranslatableMarkup $expected_error): void {
    $complete_form = [];
    $settings = $values['icon']['icon_settings'];

    $icon = self::createIcon([
      'icon_id' => explode(':', $values['icon']['icon_id'])[1],
      'source' => 'foo/bar',
      'icon_pack_id' => $icon_pack_id,
      'icon_pack_label' => $element['icon_id']['#title'],
    ]);

    $ui_icons_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icons_pack_plugin_manager->method('getIcon')
      ->with($icon->getId())
      ->willReturn($icon);
    $this->container->set('plugin.manager.ui_icons_pack', $ui_icons_pack_plugin_manager);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn($values);

    // Main test is to expect the setValueForElement().
    $form_state->expects($this->once())
      ->method('setValueForElement')
      ->with($element, ['icon' => $icon, 'settings' => $settings]);

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);

    // Test #return_id property.
    $element['#return_id'] = TRUE;

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn($values);

    // Main test is to expect the setValueForElement() with only target_id.
    $form_state->expects($this->once())
      ->method('setValueForElement')
      ->with($element, ['target_id' => $values['icon']['icon_id'], 'settings' => $settings]);

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
  }

  /**
   * Provides data for testValidateIcon.
   *
   * @return array
   *   The data to test.
   */
  public static function providerValidateIcon(): array {
    return [
      'valid icon' => [
        'element' => [
          '#parents' => ['icon'],
          'icon_id' => [
            '#title' => 'Foo',
          ],
        ],
        'icon_pack_id' => 'foo',
        'values' => [
          'icon' => [
            'icon_id' => 'foo:baz',
            'icon_settings' => [
              'foo' => [
                'settings_1' => [],
              ],
            ],
          ],
        ],
        'expected_error' => NULL,
      ],
    ];
  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconNull(): void {
    $complete_form = [];
    $element = [
      '#parents' => ['icon'],
      '#required' => FALSE,
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn(['icon' => []]);

    // The test is to expect the setValueForElement().
    $form_state->expects($this->once())
      ->method('setValueForElement')
      ->with($element, NULL);

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconError(): void {
    $complete_form = [];
    $element = [
      '#parents' => ['icon'],
      'icon_id' => [
        '#title' => 'Foo',
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn(['icon' => ['icon_id' => 'foo:baz']]);

    // The test is to expect the setError().
    $form_state
      ->expects($this->once())
      ->method('setError')
      ->with($element['icon_id'], new TranslatableMarkup('Icon for %title is invalid: %icon.<br>Please search again and select a result in the list.', [
        '%title' => $element['icon_id']['#title'],
        '%icon' => 'foo:baz',
      ]));

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);

  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconErrorNotAllowed(): void {
    $complete_form = [];
    $icon_id = 'bar';
    $icon_pack_id = 'foo';
    $icon_full_id = $icon_pack_id . ':' . $icon_id;
    $element = [
      '#parents' => ['icon'],
      'icon_id' => [
        '#title' => 'Foo',
      ],
      '#allowed_icon_pack' => ['qux', 'corge'],
    ];

    $icon = self::createIcon([
      'icon_pack_id' => $icon_pack_id,
      'icon_id' => $icon_id,
      'source' => 'foo/path',
      'icon_pack_label' => 'Baz',
    ]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn(['icon' => ['icon_id' => $icon_full_id]]);

    $ui_icons_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icons_pack_plugin_manager->method('getIcon')
      ->with($icon_full_id)
      ->willReturn($icon);
    $this->container->set('plugin.manager.ui_icons_pack', $ui_icons_pack_plugin_manager);

    // The test is to expect the setError().
    $form_state
      ->expects($this->once())
      ->method('setError')
      ->with($element['icon_id'], new TranslatableMarkup('Icon for %title is not valid anymore because it is part of icon pack: %icon_pack_id. This field limit icon pack to: %limit.', [
        '%title' => $element['icon_id']['#title'],
        '%icon_pack_id' => $icon_pack_id,
        '%limit' => implode(', ', $element['#allowed_icon_pack']),
      ]));

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconEmpty(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];
    $element = ['#parents' => ['foo']];
    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
    $this->assertEquals(['#parents' => ['foo']], $element);
  }

  /**
   * Test the valueCallback method.
   */
  public function testValueCallback(): void {
    $element = [];

    $icon_id = 'bar';
    $icon_pack_id = 'foo';
    $icon_full_id = $icon_pack_id . ':' . $icon_id;

    $input = [
      'icon_id' => $icon_full_id,
      'icon_settings' => ['foo' => 'bar'],
    ];

    $icon = self::createIcon([
      'icon_pack_id' => $icon_pack_id,
      'icon_id' => $icon_id,
      'source' => 'foo/path',
      'icon_pack_label' => 'Baz',
    ]);

    $form_state = $this->createMock(FormStateInterface::class);

    $ui_icons_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icons_pack_plugin_manager->method('getIcon')
      ->with($icon_full_id)
      ->willReturn($icon);
    $this->container->set('plugin.manager.ui_icons_pack', $ui_icons_pack_plugin_manager);

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);

    $expected = [
      'icon_id' => $icon_full_id,
      'icon_settings' => $input['icon_settings'],
      'object' => $icon,
    ];
    $this->assertSame($expected, $actual);

    // Test default_value with no icon_id.
    $input = FALSE;
    $element['#default_value'] = $icon_full_id;

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);

    $expected = [
      'object' => $icon,
    ];
    $this->assertSame($expected, $actual);
  }

  /**
   * Test the buildSettingsAjaxCallback method.
   */
  public function testBuildSettingsAjaxCallback(): void {
    $form = [
      'foo' => [
        '#prefix' => '',
        '#attached' => ['foo/bar'],
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    $request = new Request(['element_parents' => 'foo']);

    $prophecy = $this->prophesize(RendererInterface::class);
    $prophecy->renderRoot(Argument::any())->willReturn('_rendered_');
    $renderer = $prophecy->reveal();
    $this->container->set('renderer', $renderer);

    $actual = IconAutocomplete::buildSettingsAjaxCallback($form, $form_state, $request);

    $this->assertInstanceOf(AjaxResponse::class, $actual);

    $expected = [
      'command' => 'insert',
      'method' => 'replaceWith',
      'selector' => NULL,
      'data' => '_rendered_',
      'settings' => NULL,
    ];
    $this->assertSame([$expected], $actual->getCommands());
  }

}
