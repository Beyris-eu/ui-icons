<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel;

use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\Controller\IconAutocompleteController;
use Drupal\ui_icons\IconSearch;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconAutocompleteController class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconAutocompleteController::class)]
#[Group('ui_icons')]
class IconAutocompleteControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_test',
  ];

  /**
   * The IconAutocompleteController instance.
   *
   * @var \Drupal\ui_icons\Controller\IconAutocompleteController
   */
  private IconAutocompleteController $iconAutocompleteController;

  /**
   * The App root instance.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $iconSearch = $this->container->get('ui_icons.search');

    $this->iconAutocompleteController = new IconAutocompleteController(
      $iconSearch,
    );
  }

  /**
   * Tests the handleSearchIcons method of the IconAutocompleteController.
   */
  public function testHandleSearchIconsResultList(): void {
    $icon_full_id = 'test_minimal:foo';
    $search = $this->iconAutocompleteController->handleSearchIcons(new Request(['q' => $icon_full_id]));
    $result = json_decode($search->getContent(), TRUE);

    // Load the response to test, cannot simply compare string as `src` path is
    // based on physical path than can be specific for example in CI.
    $result_dom = new \DOMDocument();
    $result_dom->loadHTML($result[0]['label']);
    $this->assertSame('Footest_minimal', trim($result_dom->lastChild->textContent));

    $result_xpath = new \DOMXpath($result_dom);

    $div = $result_xpath->query('//div');
    $this->assertSame('ui-icons-result', $div->item(0)->getAttribute('class'));
    $span = $result_xpath->query('//div/span');
    $this->assertSame('ui-icons-result-icon-name', $span->item(0)->getAttribute('class'));

    $img = $result_xpath->query('//div/img');
    $this->assertSame('icon icon-preview', $img->item(0)->getAttribute('class'));
    $this->assertSame($icon_full_id, $img->item(0)->getAttribute('title'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('width'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('height'));

    $src = $img->item(0)->getAttribute('src');
    $this->assertStringEndsWith('tests/modules/ui_icons_test/icons/flat/foo.png', $src);
  }

  /**
   * Tests the handleSearchIcons method of the IconAutocompleteController.
   */
  public function testHandleSearchIconsResultGrid(): void {
    $icon_full_id = 'test_minimal:foo';
    $req = [
      'q' => $icon_full_id,
      'result_format' => 'grid',
    ];
    $search = $this->iconAutocompleteController->handleSearchIcons(new Request($req));
    $result = json_decode($search->getContent(), TRUE);

    // Load the response to test, cannot simply compare string as `src` path is
    // based on physical path than can be specific for example in CI.
    $result_dom = new \DOMDocument();
    $result_dom->loadHTML($result[0]['label']);
    $this->assertSame('Foo (test_minimal)', trim($result_dom->lastChild->textContent));

    $result_xpath = new \DOMXpath($result_dom);

    $span = $result_xpath->query('//span');
    $this->assertSame('ui-icons-result-grid', $span->item(0)->getAttribute('class'));
    $spanName = $result_xpath->query('//span/span');
    $this->assertSame('ui-icons-result-icon-name', $spanName->item(0)->getAttribute('class'));

    $img = $result_xpath->query('//span/img');
    $this->assertSame('icon icon-preview', $img->item(0)->getAttribute('class'));
    $this->assertSame($icon_full_id, $img->item(0)->getAttribute('title'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('width'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('height'));

    $src = $img->item(0)->getAttribute('src');
    $this->assertStringEndsWith('tests/modules/ui_icons_test/icons/flat/foo.png', $src);
  }

  /**
   * Provides max_result query values and the value passed to the search.
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerMaxResult(): iterable {
    yield 'above maximum' => ['100000', IconSearch::SEARCH_RESULT_MAX];
    yield 'in range' => ['48', 48];
    yield 'zero' => ['0', 1];
    yield 'negative' => ['-5', 1];
  }

  /**
   * Tests max_result from the query string is kept within the search limits.
   */
  #[DataProvider('providerMaxResult')]
  public function testHandleSearchIconsMaxResult(string $max_result, int $expected): void {
    $icon_search = $this->createMock(IconSearch::class);
    $icon_search->expects($this->once())
      ->method('search')
      ->with('foo', [], $expected, $this->anything())
      ->willReturn([]);

    $controller = new IconAutocompleteController($icon_search);
    $controller->handleSearchIcons(new Request(['q' => 'foo', 'max_result' => $max_result]));
  }

  /**
   * Tests a pack label containing a colon is kept in both result formats.
   */
  public function testCreateResultEntryLabelWithColon(): void {
    $icon = IconDefinition::create('test_pack', 'foo', 'template', NULL, NULL, [
      'label' => new TranslatableMarkup('Material: Outlined'),
    ]);
    $preview = Markup::create('_preview_');

    $entry = IconAutocompleteController::createResultEntry($icon, $preview);
    $this->assertStringContainsString('<strong class="ui-icons-result-collection">Material: Outlined</strong>', (string) $entry['label']);

    $entry = IconAutocompleteController::createResultEntryGrid($icon, $preview);
    $this->assertStringContainsString('Foo (Material: Outlined)', (string) $entry['label']);
  }

}
