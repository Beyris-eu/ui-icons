<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_popover\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_popover\Element\IconPopover;
use Drupal\ui_icons_popover\Element\IconPopoverTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the popover attached to the autocomplete element preview.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPopoverTrigger::class)]
#[CoversClass(IconPopover::class)]
#[Group('ui_icons')]
class IconPopoverTriggerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_picker',
    'ui_icons_popover',
    'ui_icons_popover_test',
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
        return 'ui_icons_popover_trigger_test';
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
   * Tests the autocomplete input carries what the popover needs.
   */
  public function testAutocompleteGetsThePopover(): void {
    $element = $this->buildElement([
      '#type' => 'icon_autocomplete',
      '#allowed_icon_pack' => ['popover_one', 'popover_two'],
    ]);

    $attributes = $element['icon_id']['#attributes'];
    // The marker the popover script selects on. 'data-icon-popover' is left
    // to the 'icon_popover' element, which replaces the input entirely.
    $this->assertSame('true', $attributes['data-icon-popover-trigger']);
    $this->assertArrayNotHasKey('data-icon-popover', $attributes);
    $this->assertSame('/ui-icons/ajax/popover/index', $attributes['data-index-url']);
    $this->assertSame('/ui-icons/ajax/popover/previews', $attributes['data-previews-url']);
    $this->assertSame('popover_one+popover_two', $attributes['data-allowed-icon-pack']);
    $this->assertContains('ui_icons_popover/popover', $element['icon_id']['#attached']['library']);
    // The autocomplete must survive, the preview box is what opens the popover.
    $this->assertSame('ui_icons.autocomplete', $element['icon_id']['#autocomplete_route_name']);
  }

  /**
   * Tests the picker steps aside when the popover is enabled.
   */
  public function testPickerStepsAside(): void {
    $element = $this->buildElement([
      '#type' => 'icon_autocomplete',
      '#allowed_icon_pack' => ['popover_one', 'popover_two'],
    ]);

    $this->assertArrayNotHasKey('data-icon-preview-trigger', $element['icon_id']['#attributes']);
    $this->assertArrayNotHasKey('data-dialog-url', $element['icon_id']['#attributes']);
    $this->assertNotContains(
      'ui_icons_picker/preview_trigger',
      $element['icon_id']['#attached']['library'] ?? [],
    );
  }

  /**
   * Tests '#show_picker' FALSE leaves a plain autocomplete.
   */
  public function testShowPickerFalseOptsOut(): void {
    $element = $this->buildElement([
      '#type' => 'icon_autocomplete',
      '#show_picker' => FALSE,
    ]);

    $this->assertArrayNotHasKey('data-icon-popover-trigger', $element['icon_id']['#attributes']);
    $this->assertArrayNotHasKey('data-index-url', $element['icon_id']['#attributes']);
  }

  /**
   * Tests the icon_popover element is not altered.
   */
  public function testIconPopoverElementIsNotAltered(): void {
    $element = $this->buildElement(['#type' => 'icon_popover']);

    $this->assertSame('true', $element['icon_id']['#attributes']['data-icon-popover']);
    $this->assertArrayNotHasKey('data-icon-popover-trigger', $element['icon_id']['#attributes']);
  }

  /**
   * Tests the icon_picker element still works next to the popover.
   */
  public function testIconPickerElementStillWorks(): void {
    $element = $this->buildElement(['#type' => 'icon_picker']);

    $this->assertContains('form-icon-dialog', $element['icon_id']['#attributes']['class']);
  }

}
