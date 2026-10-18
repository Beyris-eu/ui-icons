<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\Element\IconAutocomplete;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the rendered icon_autocomplete element.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconAutocomplete::class)]
#[Group('ui_icons')]
class IconAutocompleteKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_test',
  ];

  /**
   * Builds and renders a form holding one icon_autocomplete element.
   *
   * @param array $element
   *   Properties of the element, on top of its type and title.
   */
  private function renderElement(array $element): void {
    $request = Request::create('/');
    // The form builder reads the session to build the form token.
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $form_object = new class($element) implements FormInterface {

      /**
       * Holds the element under test.
       *
       * @param array $element
       *   The element to put in the form.
       */
      public function __construct(private readonly array $element) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_icons_autocomplete_test';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['icon'] = $this->element + [
          '#type' => 'icon_autocomplete',
          '#title' => 'Select icon',
        ];
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function validateForm(array &$form, FormStateInterface $form_state): void {}

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

    };

    // FormBuilder takes the state by reference.
    $form_state = new FormState();
    $form = $this->container->get('form_builder')
      ->buildForm($form_object, $form_state);
    $this->render($form);
  }

  /**
   * Tests `#attributes` land on the wrapper of the preview and the input.
   */
  public function testAttributesOnWrapper(): void {
    $this->renderElement([
      '#attributes' => [
        'class' => ['my-icon'],
        'data-foo' => 'bar',
      ],
    ]);

    $this->assertCount(1, $this->cssSelect('div.ui-icons-wrapper.my-icon[data-foo="bar"]'));
    // Not on the input, which carries its own attributes.
    $this->assertCount(0, $this->cssSelect('input[data-foo]'));
  }

  /**
   * Tests the visible label names the input, and is its only label.
   */
  public function testLabelPointsToInput(): void {
    $this->renderElement([]);

    $input = $this->cssSelect('input[name="icon[icon_id]"]');
    $this->assertCount(1, $input);
    $labels = $this->cssSelect('label[for="' . $input[0]['id'] . '"]');
    $this->assertCount(1, $labels);
    $this->assertSame('Select icon', trim((string) $labels[0]));
  }

  /**
   * Tests the input keeps a label of its own when the element shows none.
   */
  public function testInputLabelWithoutElementTitle(): void {
    $this->renderElement(['#title_display' => 'none']);

    $input = $this->cssSelect('input[name="icon[icon_id]"]');
    $labels = $this->cssSelect('label[for="' . $input[0]['id'] . '"]');
    $this->assertCount(1, $labels);
    $this->assertSame('Icon', trim((string) $labels[0]));
  }

}
