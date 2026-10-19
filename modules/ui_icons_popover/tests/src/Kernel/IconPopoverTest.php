<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_popover\Kernel;

// cspell:ignore autocompleteclose
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_popover\Element\IconPopover;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the data the popover selector puts on its input.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPopover::class)]
#[Group('ui_icons')]
class IconPopoverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The picker is enabled to check its alter of 'icon_autocomplete' does not
   * reach this element.
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_picker',
    'ui_icons_popover',
    'ui_icons_popover_test',
    'ui_icons_test',
  ];

  /**
   * Builds a form holding one popover element and returns that element.
   *
   * @param array $element
   *   The element properties, without its type and title.
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
        return 'ui_icons_popover_test';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['icon'] = ['#type' => 'icon_popover', '#title' => 'Icon'] + $this->element;
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
   * Tests the input carries what js/icon.popover.js needs.
   */
  public function testInputCarriesPopoverData(): void {
    $element = $this->buildElement([
      '#allowed_icon_pack' => ['popover_one', 'popover_two'],
      '#default_value' => 'popover_one:sun',
    ]);
    $input = $element['icon_id'];

    // The input is the fallback without JavaScript, not an autocomplete. The
    // key itself comes back from the 'search' element defaults.
    $this->assertFalse($input['#autocomplete_route_name']);
    $this->assertSame('true', $input['#attributes']['data-icon-popover']);
    $this->assertSame('/ui-icons/ajax/popover/index', $input['#attributes']['data-index-url']);
    $this->assertSame('/ui-icons/ajax/popover/previews', $input['#attributes']['data-previews-url']);
    $this->assertSame('popover_one+popover_two', $input['#attributes']['data-allowed-icon-pack']);
    $this->assertSame('Sun', $input['#attributes']['data-icon-label']);
    $this->assertContains('ui_icons_popover/popover', $input['#attached']['library']);
    // A pick rebuilds the settings exactly like the autocomplete does.
    $this->assertSame('autocompleteclose change', $input['#ajax']['event']);
    // ui_icons_picker only turns the 'icon_autocomplete' preview into a
    // trigger.
    $this->assertArrayNotHasKey('data-icon-preview-trigger', $input['#attributes']);
  }

  /**
   * Tests the libraries of the allowed packs are attached for the previews.
   *
   * Previews arrive as JSON, which carries no assets, so a font pack would
   * show empty tiles without its library already on the page.
   */
  public function testPackLibrariesAttached(): void {
    $element = $this->buildElement(['#allowed_icon_pack' => ['test_library']]);

    $this->assertContains('ui_icons_test/test_library', $element['icon_id']['#attached']['library']);
  }

  /**
   * Tests a value that is no known icon gets no label.
   */
  public function testUnknownValueHasNoLabel(): void {
    $element = $this->buildElement(['#default_value' => 'popover_one:missing']);

    $this->assertArrayNotHasKey('data-icon-label', $element['icon_id']['#attributes']);
  }

}
