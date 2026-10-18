<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_picker\Kernel;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormState;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_picker\Form\IconSelectForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test the icon picker modal form.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconSelectForm::class)]
#[Group('ui_icons')]
class IconSelectFormTest extends KernelTestBase {

  /**
   * Wrapper id the picker is opened for.
   */
  private const WRAPPER_ID = 'edit-icon-wrapper';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_picker',
    'ui_icons_test',
  ];

  /**
   * Builds the picker form with the given dialog query and user input.
   *
   * @param array $query
   *   The `dialogOptions[query]` the modal was opened with.
   * @param array $input
   *   User input, `filter` being the only key the form reads.
   * @param array $dialog_options
   *   Extra `dialogOptions`, siblings of `query`, `target` being the only key
   *   the form reads.
   * @param \Drupal\Core\Form\FormState|null $form_state
   *   The form state to build with, to inspect it afterwards.
   *
   * @return array
   *   The built form.
   */
  private function buildPicker(array $query, array $input = [], array $dialog_options = [], ?FormState $form_state = NULL): array {
    $request = Request::create('/ui-icons/picker/dialog', 'GET', [
      'dialogOptions' => $dialog_options + ['query' => $query],
    ]);
    // The form builder reads the session to build the form token.
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $form_state ??= new FormState();
    if ($input) {
      $form_state->setUserInput($input);
    }

    return $this->container->get('form_builder')
      ->buildForm(IconSelectForm::class, $form_state);
  }

  /**
   * Tests that the picker lists icons of the allowed packs only.
   */
  public function testBuildFormListsAllowedPacksOnly(): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $this->assertSame(self::WRAPPER_ID, $form['wrapper_id']['#value']);

    $options = $form['results']['list']['icon_full_id']['#options'];
    // The empty option always comes first so a selection can be cleared.
    $this->assertArrayHasKey('_none_', $options);
    unset($options['_none_']);

    $this->assertNotEmpty($options);
    foreach (array_keys($options) as $icon_full_id) {
      $this->assertStringStartsWith('test_path:', (string) $icon_full_id);
    }
  }

  /**
   * Tests that an empty `allowed_icon_pack` lists every pack.
   */
  public function testBuildFormWithoutPackLimit(): void {
    $form = $this->buildPicker(['wrapper_id' => self::WRAPPER_ID]);

    $options = $form['results']['list']['icon_full_id']['#options'];
    unset($options['_none_']);

    $packs = [];
    foreach (array_keys($options) as $icon_full_id) {
      [$pack_id] = explode(':', (string) $icon_full_id);
      $packs[$pack_id] = TRUE;
    }

    $this->assertGreaterThan(1, count($packs));
  }

  /**
   * Tests the preview libraries and settings attached to the icon list.
   */
  public function testBuildFormAttachesPreviewAssets(): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $attached = $form['results']['list']['#attached'];
    $this->assertContains('ui_icons_picker/library', $attached['library']);
    $this->assertContains('ui_icons/ui_icons.preview', $attached['library']);

    $preview_data = $attached['drupalSettings']['ui_icons_preview_data'];
    // Ajax deep merges settings into the previous ones, so they would still
    // carry ids of the grid before. js/library.js reads them from the grid.
    $this->assertArrayNotHasKey('icon_full_ids', $preview_data);
    $this->assertSame(['size' => 32], $preview_data['settings']);
    $this->assertTrue($preview_data['target_input_label']);
  }

  /**
   * Tests the search filter narrows the listed icons.
   */
  public function testBuildFormWithSearchQuery(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID],
      ['filter' => 'foo'],
    );

    $this->assertSame('foo', $form['filters']['filter']['#default_value']);

    $options = $form['results']['list']['icon_full_id']['#options'];
    unset($options['_none_']);
    $this->assertNotEmpty($options);
  }

  /**
   * Tests the empty state when nothing matches the filter.
   */
  public function testBuildFormWithNoResult(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID],
      ['filter' => 'nothing_can_ever_match_this_query'],
    );

    $this->assertSame('markup', $form['results']['list']['#type']);
    $this->assertStringContainsString('No icon found', (string) $form['results']['list']['#markup']);
    // The empty state stops before the select button and the pager.
    $this->assertArrayNotHasKey('actions', $form);
    $this->assertArrayNotHasKey('pagination', $form['results']);
  }

  /**
   * Tests that a single page of results carries no pager.
   */
  public function testBuildFormWithoutPagination(): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayNotHasKey('pagination', $form['results']);
  }

  /**
   * Tests the placeholder images still load from a subdirectory install.
   */
  public function testBuildFormImagesFollowBasePath(): void {
    $GLOBALS['base_path'] = '/subdir/';

    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $options = $form['results']['list']['icon_full_id']['#options'];
    $this->assertStringContainsString('src="/subdir/core/themes/claro/images/icons/e34f4f/crossout.svg"', $options['_none_']);
    $this->assertStringContainsString('src="/subdir/core/themes/claro/images/icons/003ecc/spinner.svg"', $options['test_path:foo']);
  }

  /**
   * Tests the page submit handlers move the stored page and rebuild.
   */
  public function testPageSubmitHandlers(): void {
    $form = [];
    $form_state = new FormState();
    IconSelectForm::setModalState($form_state, ['page' => 0]);

    $picker = IconSelectForm::create($this->container);

    $picker->nextPageSubmit($form, $form_state);
    $this->assertSame(1, IconSelectForm::getModalState($form_state)['page']);
    $this->assertTrue($form_state->isRebuilding());

    $picker->previousPageSubmit($form, $form_state);
    $this->assertSame(0, IconSelectForm::getModalState($form_state)['page']);
  }

  /**
   * Tests searching resets the pager back to the first page.
   */
  public function testSearchSubmitResetsPage(): void {
    $form = [];
    $form_state = new FormState();
    IconSelectForm::setModalState($form_state, ['page' => 4]);

    IconSelectForm::create($this->container)->searchSubmit($form, $form_state);

    $this->assertSame(0, IconSelectForm::getModalState($form_state)['page']);
    $this->assertTrue($form_state->isRebuilding());
  }

  /**
   * Tests submitting without a selection is rejected.
   */
  public function testValidateFormRequiresSelection(): void {
    $form = ['results' => ['list' => ['#parents' => ['results', 'list']]]];
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['submit']]);

    IconSelectForm::create($this->container)->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('Pick an icon to insert', (string) reset($errors));
  }

  /**
   * Tests validation is skipped for the pager and search buttons.
   */
  public function testValidateFormIgnoresOtherButtons(): void {
    $form = ['results' => ['list' => ['#parents' => ['results', 'list']]]];
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['search']]);

    IconSelectForm::create($this->container)->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Tests the ajax response sent when an icon is picked.
   */
  public function testSelectIconAjax(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'icon_full_id' => 'test_path:foo',
      'wrapper_id' => self::WRAPPER_ID,
    ]);

    $response = IconSelectForm::create($this->container)->selectIconAjax($form, $form_state);

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $commands = $response->getCommands();
    $this->assertSame('updateIconLibrarySelection', $commands[0]['command']);
    $this->assertSame('test_path:foo', $commands[0]['icon_full_id']);
    $this->assertSame(self::WRAPPER_ID, $commands[0]['wrapper_id']);
    $this->assertSame('closeDialog', $commands[1]['command']);
    // No target was carried, so the shared modal is what gets closed.
    $this->assertSame('#drupal-modal', $commands[1]['selector']);
  }

  /**
   * Tests picking the empty option clears the selection.
   */
  public function testSelectIconAjaxWithNoneClearsValue(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'icon_full_id' => '_none_',
      'wrapper_id' => self::WRAPPER_ID,
    ]);

    $response = IconSelectForm::create($this->container)->selectIconAjax($form, $form_state);

    $this->assertSame('', $response->getCommands()[0]['icon_full_id']);
  }

  /**
   * Tests the dialog target falls back to the shared modal.
   */
  public function testBuildFormDefaultsDialogTarget(): void {
    $form = $this->buildPicker(['wrapper_id' => self::WRAPPER_ID]);

    $this->assertSame('#drupal-modal', $form['dialog_target']['#value']);
  }

  /**
   * Tests the dialog target named by the opener is carried in the form.
   *
   * Core strips `target` from the options it hands to jQuery UI, so the form
   * has to pick it up itself to know which dialog to close on selection.
   */
  public function testBuildFormCarriesDialogTarget(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID],
      [],
      ['target' => 'ui-icons-picker-dialog'],
    );

    // The id comes in bare and has to end up a selector.
    $this->assertSame('#ui-icons-picker-dialog', $form['dialog_target']['#value']);
  }

  /**
   * Tests a target already given as a selector is left alone.
   */
  public function testBuildFormKeepsDialogTargetSelector(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID],
      [],
      ['target' => '#ui-icons-picker-dialog'],
    );

    $this->assertSame('#ui-icons-picker-dialog', $form['dialog_target']['#value']);
  }

  /**
   * Tests the selection closes the dialog the picker is in, not its opener.
   *
   * Opened from a form that is itself in the shared modal, closing
   * `#drupal-modal` would take down the form the icon is written back to.
   */
  public function testSelectIconAjaxClosesOwnDialog(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'icon_full_id' => 'test_path:foo',
      'wrapper_id' => self::WRAPPER_ID,
      'dialog_target' => '#ui-icons-picker-dialog',
    ]);

    $response = IconSelectForm::create($this->container)->selectIconAjax($form, $form_state);

    $commands = $response->getCommands();
    $this->assertSame('closeDialog', $commands[1]['command']);
    $this->assertSame('#ui-icons-picker-dialog', $commands[1]['selector']);
  }

  /**
   * Data provider for ::testPageCount().
   *
   * @return array
   *   The test cases: icons total, expected page count.
   */
  public static function providerPageCount(): array {
    return [
      'less than a page' => [246, 1],
      'exactly one page' => [247, 1],
      'one more than a page' => [248, 2],
      'exactly two pages' => [494, 2],
      'nine pages, the last one partial' => [2147, 9],
    ];
  }

  /**
   * Tests the pager counts the pages the icons fill, no empty last page.
   *
   * @param int $total
   *   The number of icons to list.
   * @param int $expected
   *   The expected page count.
   */
  #[DataProvider('providerPageCount')]
  public function testPageCount(int $total, int $expected): void {
    $icons = array_fill_keys(array_map(
      static fn (int $index): string => 'test_path:icon_' . $index,
      range(1, $total),
    ), []);
    $icon_pack_manager = $this->createMock(IconPackManagerInterface::class);
    $icon_pack_manager->method('getIcons')->willReturn($icons);
    $icon_pack_manager->method('getDefinitions')->willReturn([]);
    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $form = $this->buildPicker(['wrapper_id' => self::WRAPPER_ID]);

    if (1 === $expected) {
      $this->assertArrayNotHasKey('pagination', $form['results']);
      return;
    }
    $this->assertSame(
      'Page 1/' . $expected,
      (string) $form['results']['pagination']['page_info']['#markup'],
    );
  }

  /**
   * Tests the form state keeps the page only, not the icon list.
   *
   * The form state is written to the database on every ajax request of the
   * picker, the icon list of a large pack weighs hundreds of kilobytes there.
   */
  public function testModalStateHoldsNoIcons(): void {
    $form_state = new FormState();
    $this->buildPicker(['wrapper_id' => self::WRAPPER_ID], [], [], $form_state);

    $this->assertSame(['page' => 0], IconSelectForm::getModalState($form_state));
  }

  /**
   * Data provider for ::testPackLibraries().
   *
   * @return array
   *   The test cases: allowed packs query, whether the library is expected.
   */
  public static function providerPackLibraries(): array {
    return [
      'other pack allowed' => ['test_path', FALSE],
      'pack allowed' => ['test_library', TRUE],
      'every pack allowed' => ['', TRUE],
    ];
  }

  /**
   * Tests only the libraries of the allowed icon packs are attached.
   *
   * @param string $allowed_icon_pack
   *   The `allowed_icon_pack` dialog query.
   * @param bool $expected
   *   Whether the library of the `test_library` pack is expected.
   */
  #[DataProvider('providerPackLibraries')]
  public function testPackLibraries(string $allowed_icon_pack, bool $expected): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => $allowed_icon_pack,
    ]);

    $libraries = $form['results']['list']['#attached']['library'];
    $this->assertSame($expected, in_array('ui_icons_test/test_library', $libraries, TRUE));
  }

  /**
   * Tests opening the picker without its dialog options is a bad request.
   */
  public function testMissingDialogOptionsIsBadRequest(): void {
    $this->expectException(BadRequestHttpException::class);

    $this->buildPicker([]);
  }

  /**
   * Tests the empty option is a tile of its own with a text alternative.
   *
   * Styling it from its position took the border off the first icon when the
   * field is required and the empty option is left out.
   */
  public function testNoneTile(): void {
    $form = $this->buildPicker(['wrapper_id' => self::WRAPPER_ID]);

    $radios = $form['results']['list']['icon_full_id'];
    $this->assertContains('icon-picker-modal__none', $radios['_none_']['#wrapper_attributes']['class']);
    $this->assertStringContainsString('alt="Select none"', (string) $radios['#options']['_none_']);
  }

  /**
   * Tests a single character filter lists every icon.
   *
   * The search needs two characters, js/library.js does not send one. Paging
   * still posts it, and that must not empty the grid.
   */
  public function testSingleCharacterFilterListsEverything(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID, 'allowed_icon_pack' => 'test_path'],
      ['filter' => 'f'],
    );

    $this->assertArrayHasKey('test_path:foo', $form['results']['list']['icon_full_id']['#options']);
    $this->assertArrayHasKey('test_path:bar', $form['results']['list']['icon_full_id']['#options']);
  }

}
