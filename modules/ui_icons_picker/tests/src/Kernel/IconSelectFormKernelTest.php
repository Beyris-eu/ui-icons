<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_picker\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_picker\Form\IconSelectForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the IconSelectForm class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconSelectForm::class)]
#[Group('ui_icons')]
class IconSelectFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_picker',
    'ui_icons_test',
  ];

  /**
   * Data provider for ::testNoneOption().
   *
   * @return array
   *   The test cases.
   */
  public static function providerNoneOption(): array {
    return [
      'optional' => ['0', TRUE],
      'required' => ['1', FALSE],
      'missing' => [NULL, TRUE],
    ];
  }

  /**
   * Test the "Select none" option is not available for required field.
   *
   * @param string|null $required
   *   The required query value, NULL to not send it.
   * @param bool $expected
   *   Whether the "Select none" option is expected.
   */
  #[DataProvider('providerNoneOption')]
  public function testNoneOption(?string $required, bool $expected): void {
    $query = ['wrapper_id' => 'foo', 'allowed_icon_pack' => 'test_svg'];
    if (NULL !== $required) {
      $query['required'] = $required;
    }
    $request = Request::create('/', 'GET', ['dialogOptions' => ['query' => $query]]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $form_state = new FormState();
    $form = $this->container->get('form_builder')
      ->buildForm(IconSelectForm::class, $form_state);

    $this->assertSame($expected, isset($form['results']['list']['icon_full_id']['#options']['_none_']));
  }

}
