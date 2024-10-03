<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit\Plugin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget;

/**
 * @coversDefaultClass \Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget
 *
 * @group ui_icons
 */
class IconWidgetTest extends IconUnitTestCase {

  /**
   * The field widget under test.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget
   */
  private IconWidget $widget;

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
    $this->container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($this->container);

    $fieldDefinition = $this->getMockBuilder('Drupal\Core\Field\FieldDefinition')
      ->disableOriginalConstructor()
      ->getMock();

    $this->widget = new IconWidget(
      'icon_widget',
      [],
      $fieldDefinition,
      [],
      []
    );
  }

  /**
   * Tests the massageFormValues method.
   */
  public function testMassageFormValues(): void {
    $form_state = $this->getMockBuilder('Drupal\Core\Form\FormState')
      ->disableOriginalConstructor()
      ->getMock();
    $values = [];

    // Invalid icon.
    $values[]['value'] = [
      'icon' => NULL,
    ];

    // Icon with data.
    $values[]['value'] = [
      'icon' => $this->createMockIcon([
        'pack_id' => 'foo',
        'icon_id' => 'bar',
      ]),
    ];

    $actual = $this->widget->massageFormValues($values, [], $form_state);

    foreach ($actual as $delta => $value) {
      if (NULL === $values[$delta]['value']['icon']) {
        $this->assertArrayNotHasKey('target_id', $value);
      }
      else {
        $this->assertEquals('foo:bar', $value['target_id']);
      }
    }
  }

}
