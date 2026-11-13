<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_popover\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_popover\Element\IconPopoverTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the popover trigger when ui_icons_picker is not enabled.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPopoverTrigger::class)]
#[Group('ui_icons')]
class IconPopoverWithoutPickerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_popover',
  ];

  /**
   * Builds a form holding one element and returns that element.
   *
   * @param array $element
   *   The element to build, without its title.
   *
   * @return array
   *   The processed element.
   */
  private function buildElement(array $element): array {
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
        return 'ui_icons_popover_without_picker_test';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['icon'] = ['#title' => 'Icon'] + $this->element;
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

    return $form['icon'];
  }

  /**
   * Tests the popover trigger is added without the picker module.
   */
  public function testAutocompleteGetsThePopover(): void {
    $element = $this->buildElement(['#type' => 'icon_autocomplete']);

    $attributes = $element['icon_id']['#attributes'];
    $this->assertSame('true', $attributes['data-icon-popover-trigger']);
    $this->assertSame('ui_icons.autocomplete', $element['icon_id']['#autocomplete_route_name']);
  }

}
