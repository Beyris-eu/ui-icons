<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Form\IconExtractorSettingsForm;

/**
 * Tests ui_icons configuration form.
 *
 * @group ui_icons
 */
class IconExtractorSettingsFormTest extends UnitTestCase {

  /**
   * Test the generateSettingsForm method.
   *
   * @param array $settings
   *   The settings to test.
   * @param array $expected
   *   The expected result.
   *
   * @dataProvider settingsFormDataProvider
   */
  public function testGenerateSettingsForm(array $settings, array $expected): void {
    $actual = IconExtractorSettingsForm::generateSettingsForm($settings);
    $this->assertSame($expected, $actual);
  }

  /**
   * Provide data for testGenerateSettingsForm.
   *
   * @return array
   *   The data for settings and expected.
   *
   * @phpcs:disable 
   */
  public static function settingsFormDataProvider(): array {
    return [
      'default case for string field' => [
        'settings' => [
          'test_string_default' => [
            'title' => 'Test String',
            'description' => 'Form test string',
          ],
        ],
        'expected' => [
          'test_string_default' => [
            '#type' => 'textfield',
            '#title' => 'Test String',
            '#description' => 'Form test string',
          ],
        ],
      ],
      'case for string field' => [
        'settings' => [
          'test_string' => [
            'title' => 'Test String',
            'description' => 'Form test string',
            'type' => 'string',
            'size' => 22,
            'maxlength' => 33,
            'required' => TRUE,
            'placeholder' => 'My test string',
            'pattern' => '_pattern_',
            'prefix' => '_prefix_',
            'suffix' => '_suffix_',
          ],
        ],
        'expected' => [
          'test_string' => [
            '#type' => 'textfield',
            '#title' => 'Test String',
            '#description' => 'Form test string',
            '#size' => 22,
            '#maxlength' => 33,
            '#pattern' => '_pattern_',
            '#required' => TRUE,
            '#field_prefix' => '_prefix_',
            '#field_suffix' => '_suffix_',
            '#placeholder' => 'My test string',
          ],
        ],
      ],
      'case for number field' => [
        'settings' => [
          'test_number' => [
            'title' => 'Test Number',
            'description' => 'Form test number',
            'type' => 'number',
            'min' => 1,
            'max' => 100,
            'step' => 1,
          ],
        ],
        'expected' => [
          'test_number' => [
            '#type' => 'number',
            '#title' => 'Test Number',
            '#description' => 'Form test number',
            '#min' => 1,
            '#max' => 100,
            '#step' => 1,
          ],
        ],
      ],
      'case for boolean field' => [
        'settings' => [
          'test_boolean' => [
            'title' => 'Test Boolean',
            'description' => 'Form test boolean',
            'type' => 'boolean',
          ],
        ],
        'expected' => [
          'test_boolean' => [
            '#type' => 'checkbox',
            '#title' => 'Test Boolean',
            '#description' => 'Form test boolean',
          ],
        ],
      ],
      'case for color field' => [
        'settings' => [
          'test_color' => [
            'title' => 'Test Color',
            'description' => 'Form test color',
            'type' => 'color',
          ],
        ],
        'expected' => [
          'test_color' => [
            '#type' => 'color',
            '#title' => 'Test Color',
            '#description' => 'Form test color',
          ],
        ],
      ],
      'case for range field' => [
        'settings' => [
          'test_range' => [
            'title' => 'Test Range',
            'description' => 'Form test range',
            'type' => 'range',
            'min' => 1,
            'max' => 100,
            'step' => 1,
          ],
        ],
        'expected' => [
          'test_range' => [
            '#type' => 'range',
            '#title' => 'Test Range',
            '#description' => 'Form test range',
            '#min' => 1,
            '#max' => 100,
            '#step' => 1,
          ],
        ],
      ],
      'case for field with enum' => [
        'settings' => [
          'test_enum' => [
            'title' => 'Test Enum',
            'description' => 'Form test enum',
            'type' => 'string',
            'enum' => ['option1', 'option2', 'option3'],
          ],
        ],
        'expected' => [
          'test_enum' => [
            '#type' => 'select',
            '#title' => 'Test Enum',
            '#description' => 'Form test enum',
            '#options' => array_combine(['option1', 'option2', 'option3'], ['option1', 'option2', 'option3']),
          ],
        ],
      ],
      'case for field with default value' => [
        'settings' => [
          'test_default' => [
            'title' => 'Test Default',
            'description' => 'Form test default',
            'size' => 44,
            'type' => 'string',
            'default' => 'default value',
          ],
        ],
        'expected' => [
          'test_default' => [
            '#type' => 'textfield',
            '#title' => 'Test Default',
            '#description' => 'Form test default',
            '#size' => 44,
            '#default_value' => 'default value',
          ],
        ],
      ],
      'case for float field' => [
        'settings' => [
          'test_number' => [
            'title' => 'Test float',
            'description' => 'Form test float',
            'type' => 'float',
            'min' => 10,
            'max' => 1200,
            'step' => 5,
          ],
        ],
        'expected' => [
          'test_number' => [
            '#type' => 'number',
            '#title' => 'Test float',
            '#description' => 'Form test float',
            '#min' => 10,
            '#max' => 1200,
            '#step' => 5,
          ],
        ],
      ],
    ];
  }

  /**
   * Test the generateSettingsForm method.
   */
  public function testGenerateSettingsFormWithValues(): void {
    $options = [
      'test_saved' => [
        'title' => 'Test Saved',
        'description' => 'Form test saved',
        'type' => 'string',
      ],
    ];

    $form_state = $this->createMock(SubformStateInterface::class);
    $subform_state = $this->createMock(SubformStateInterface::class);
    $form_state->method('getCompleteFormState')->willReturn($subform_state);
    $subform_state->method('getValue')->with('saved_values')->willReturn(['test_saved' => 'saved value']);

    $actual = IconExtractorSettingsForm::generateSettingsForm($options, $form_state);
    $expected = [
      'test_saved' => [
        '#type' => 'textfield',
        '#title' => $options['test_saved']['title'],
        '#description' => $options['test_saved']['description'],
        '#default_value' => 'saved value',
      ],
    ];
    $this->assertSame($expected, $actual);
  }

}
