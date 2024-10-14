<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element;

// cspell:ignore corge quux tri OME
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons\Controller\IconAutocompleteController;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\ui_icons\Controller\IconAutocompleteController
 *
 * @group ui_icons
 */
class IconAutocompleteControllerTest extends IconUnitTestCase {

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
    \Drupal::setContainer($this->container);
  }

  /**
   * Test the _construct method.
   */
  public function testConstructor(): void {
    $iconPackManager = $this->createMock(IconPackManagerInterface::class);
    $renderer = $this->createMock(RendererInterface::class);
    $iconAutocompleteController = new IconAutocompleteController($iconPackManager, $renderer);

    $this->assertInstanceOf(IconAutocompleteController::class, $iconAutocompleteController);
  }

  /**
   * Tests the handleSearchIcons method of the IconAutocompleteController.
   *
   * @param array $iconsData
   *   The array of icons data to be created.
   * @param array $queryParams
   *   The query parameters to be passed to the Request object.
   * @param array|null $expectedData
   *   The expected data to be returned in the JsonResponse.
   *
   * @dataProvider handleSearchIconsDataProvider
   */
  public function testHandleSearchIcons(array $iconsData, array $queryParams, ?array $expectedData): void {
    $prophecy = $this->prophesize(IconPackManagerInterface::class);

    $icons = [];
    foreach ($iconsData as $iconData) {
      $icons[] = $this->createTestIcon($iconData);
    }

    $prophecy->getIcons(Argument::any())->willReturn($icons);

    /** @var \Drupal\ui_icons\Plugin\IconPackManagerInterface $iconPackManager */
    $iconPackManager = $prophecy->reveal();
    $this->container->set('plugin.manager.icon_pack', $iconPackManager);

    $prophecy = $this->prophesize(RendererInterface::class);
    $prophecy->renderInIsolation(Argument::any())->willReturn('_rendered_');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $prophecy->reveal();
    $this->container->set('renderer', $renderer);

    $iconAutocompleteController = new IconAutocompleteController($iconPackManager, $renderer);
    $request = new Request($queryParams);
    $actual = $iconAutocompleteController->handleSearchIcons($request);

    if (NULL === $expectedData) {
      $expected = new JsonResponse([]);
    }
    else {
      $expected = new JsonResponse($expectedData);
    }
    $this->assertEquals($expected->getContent(), $actual->getContent());
  }

  /**
   * Provide data for testHandleSearchIcons.
   *
   * @return array
   *   Test data.
   */
  public static function handleSearchIconsDataProvider(): array {
    return [
      'no query' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => [],
        'expectedData' => [],
      ],
      'empty query' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => ''],
        'expectedData' => [],
      ],
      'query icon id' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => 'bar'],
        'expectedData' => [
          self::createIconResultData(),
        ],
      ],
      'query foo with allowed_icon_pack include icon' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => 'bar', 'allowed_icon_pack' => 'foo+bar'],
        'expectedData' => [
          self::createIconResultData(),
        ],
      ],
      'query foo with allowed_icon_pack do not include icon' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => 'foo', 'allowed_icon_pack' => 'qux+bar'],
        'expectedData' => [],
      ],
      'query foo with max_result 2 for 3 valid icons' => [
        'iconsData' => self::createIconTestData('foo', 'foo-qux') + self::createIconTestData('quux', 'qux-foo') + self::createIconTestData('corge', 'baz-foo'),
        'queryParams' => ['q' => 'foo', 'max_result' => 2],
        'expectedData' => [
          self::createIconResultData('foo', 'foo-qux'),
          self::createIconResultData('quux', 'qux-foo'),
        ],
      ],
      'query string part name' => [
        'iconsData' => self::createIconTestData(NULL, 'some-name-string'),
        'queryParams' => ['q' => 'tri'],
        'expectedData' => [
          self::createIconResultData(NULL, 'some-name-string'),
        ],
      ],
      'query multiple words' => [
        'iconsData' => self::createIconTestData('foo', 'foo-bar') + self::createIconTestData('foo', 'qux-foo', 'Biz'),
        'queryParams' => ['q' => 'foo Baz'],
        'expectedData' => [
          self::createIconResultData('foo', 'foo-bar'),
        ],
      ],
      'query string part name with non ascii chars' => [
        'iconsData' => self::createIconTestData(NULL, 'Some Name String'),
        'queryParams' => ['q' => '%!?OME*$$'],
        'expectedData' => [
          self::createIconResultData(NULL, 'Some Name String'),
        ],
      ],
      'query string icon_id' => [
        'iconsData' => self::createIconTestData(NULL, 'my_icon_id', 'Some Name'),
        'queryParams' => ['q' => 'icon'],
        'expectedData' => [
          self::createIconResultData(NULL, 'my_icon_id', 'Some Name'),
        ],
      ],
      'query non ascii letter' => [
        'iconsData' => self::createIconTestData('%2$*', 'à(5çè', '?:!!/"&'),
        'queryParams' => ['q' => ':!!'],
        'expectedData' => [],
      ],
      'query icon pack no result' => [
        'iconsData' => self::createIconTestData('my_icon_pack'),
        'queryParams' => ['q' => 'icon_pack'],
        'expectedData' => [],
      ],
      'query 1 char' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => 'f'],
        'expectedData' => [],
      ],
      'query 2 chars' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => 'fo'],
        'expectedData' => [],
      ],
      'query empty' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => ''],
        'expectedData' => NULL,
      ],
      'query space' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => ' '],
        'expectedData' => NULL,
      ],
      'query spaces' => [
        'iconsData' => self::createIconTestData(),
        'queryParams' => ['q' => '   '],
        'expectedData' => NULL,
      ],
      'query foo with empty icons' => [
        'iconsData' => [],
        'queryParams' => ['q' => 'foo', 'allowed_icon_pack' => 'foo+bar'],
        'expectedData' => NULL,
      ],
    ];
  }

  /**
   * Creates icon data array.
   *
   * @param string|null $pack_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $pack_label
   *   The label of the icon set.
   *
   * @return array
   *   The icon data array.
   */
  private static function createIconTestData(?string $pack_id = NULL, ?string $icon_id = NULL, ?string $pack_label = NULL): array {
    $pack_id = $pack_id ?? 'foo';
    $icon_id = $icon_id ?? 'bar';
    $icon_full_id = IconDefinition::createIconId($pack_id, $icon_id);

    return [
      $icon_full_id => [
        'icon_id' => $icon_id,
        'source' => 'qux/corge',
        'pack_id' => $pack_id,
        'label' => $pack_label ?? 'Baz',
      ],
    ];
  }

  /**
   * Creates icon data search result array.
   *
   * @param string|null $pack_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $pack_label
   *   The label of the icon set.
   *
   * @return array
   *   The icon data array.
   */
  private static function createIconResultData(?string $pack_id = NULL, ?string $icon_id = NULL, ?string $pack_label = NULL): array {
    $icon_id = $icon_id ?? 'bar';
    // Important to mimic IconDefinition::getLabel().
    $label = IconDefinition::humanize($icon_id);
    $icon_full_id = IconDefinition::createIconId($pack_id ?? 'foo', $icon_id);

    return [
      'value' => $icon_full_id,
      'label' => new FormattableMarkup('<span class="ui-menu-icon">@icon</span> @name', [
        '@icon' => '_rendered_',
        '@name' => $label . ' (' . ($pack_label ?? 'Baz') . ')',
      ]),
    ];
  }

}
