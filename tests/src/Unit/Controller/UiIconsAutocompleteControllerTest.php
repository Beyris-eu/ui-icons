<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Controller\UiIconsAutocompleteController;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\UiIconsetManagerInterface;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests UiIconsAutocompleteController Controller class.
 *
 * @group ui_icons
 */
class UiIconsAutocompleteControllerTest extends UnitTestCase {

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

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
    $uiIconsetManager = $this->createMock(UiIconsetManagerInterface::class);
    $renderer = $this->createMock(RendererInterface::class);
    $uiIconsAutocompleteController = new UiIconsAutocompleteController($uiIconsetManager, $renderer);

    $this->assertInstanceOf('Drupal\ui_icons\Controller\UiIconsAutocompleteController', $uiIconsAutocompleteController);
  }

  /**
   * @dataProvider handleRenderIconDataProvider
   *
   * @param string|null $iconId
   *   The ID of the icon to be rendered. Can be null.
   * @param bool $hasResult
   *   Should have icon result.
   * @param array $queryParams
   *   The query parameters to be passed in the request.
   * @param array|null $iconData
   *   The data of the icon to be rendered. Can be null.
   * @param string $expectedContent
   *   The expected content of the response.
   * @param int $expectedStatusCode
   *   The expected status code of the response.
   */
  public function testHandleRenderIcon(?string $iconId, bool $hasResult, array $queryParams, ?array $iconData, string $expectedContent, int $expectedStatusCode): void {
    $prophecy = $this->prophesize(UiIconsetManagerInterface::class);
    if ($hasResult) {
      $icon = $this->createMockIcon($iconData);
      $prophecy->getIcon($iconId)->willReturn($icon);
    }
    else {
      $prophecy->getIcon(Argument::any())->willReturn(NULL);
    }
    $uiIconsetManager = $prophecy->reveal();
    $this->container->set('plugin.manager.ui_iconset', $uiIconsetManager);

    $prophecy = $this->prophesize(RendererInterface::class);
    $prophecy->renderInIsolation(Argument::any())->willReturn($expectedContent);
    $renderer = $prophecy->reveal();
    $this->container->set('renderer', $renderer);

    $uiIconsAutocompleteController = new UiIconsAutocompleteController($uiIconsetManager, $renderer);

    $request = new Request($queryParams);
    $actual = $uiIconsAutocompleteController->handleRenderIcon($request);

    $expected = new Response($expectedContent, $expectedStatusCode);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Provide data for testHandleRenderIcon.
   *
   * @return array
   *   Test data.
   */
  public static function handleRenderIconDataProvider(): array {
    return [
      'empty query' => [
        'iconId' => NULL,
        'hasResult' => FALSE,
        'queryParams' => ['q' => ''],
        'iconData' => NULL,
        'expectedContent' => '',
        'expectedStatusCode' => 200,
      ],
      'valid icon query' => [
        'iconId' => 'foo:bar',
        'hasResult' => TRUE,
        'queryParams' => ['q' => 'foo:bar', 'width' => 100, 'height' => 100],
        'iconData' => ['iconId' => 'foo:bar', 'width' => 100, 'height' => 100],
        'expectedContent' => '_rendered_',
        'expectedStatusCode' => 200,
      ],
      'valid icon query with custom dimensions' => [
        'iconId' => 'foo:bar',
        'hasResult' => TRUE,
        'queryParams' => ['q' => 'foo:bar', 'width' => 200, 'height' => 200],
        'iconData' => ['iconId' => 'foo:bar', 'width' => 200, 'height' => 200],
        'expectedContent' => '_rendered_',
        'expectedStatusCode' => 200,
      ],
      'invalid query' => [
        'iconId' => 'foo:bar',
        'hasResult' => FALSE,
        'queryParams' => ['q' => 'baz:foo', 'width' => 100, 'height' => 100],
        'iconData' => NULL,
        'expectedContent' => '',
        'expectedStatusCode' => 200,
      ],
    ];
  }

  /**
   * Tests the handleSearchIcons method of the UiIconsAutocompleteController.
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
    $prophecy = $this->prophesize(UiIconsetManagerInterface::class);

    $icons = [];
    foreach ($iconsData as $iconId => $iconData) {
      $icons[$iconId] = $this->createIcon($iconData);
    }
    $prophecy->getIcons()->willReturn($icons);

    $uiIconsetManager = $prophecy->reveal();
    $this->container->set('plugin.manager.ui_iconset', $uiIconsetManager);

    $prophecy = $this->prophesize(RendererInterface::class);
    $prophecy->renderInIsolation(Argument::any())->willReturn('_rendered_');
    $renderer = $prophecy->reveal();
    $this->container->set('renderer', $renderer);

    $uiIconsAutocompleteController = new UiIconsAutocompleteController($uiIconsetManager, $renderer);
    $request = new Request($queryParams);
    $actual = $uiIconsAutocompleteController->handleSearchIcons($request);

    if (NULL === $expectedData) {
      $expected = new JsonResponse();
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
      'query icon id' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'bar'],
        'expectedData' => [
          self::createIconResultData(),
        ],
      ],
      'query foo with allowed_iconset include icon' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'bar', 'allowed_iconset' => 'foo+bar'],
        'expectedData' => [
          self::createIconResultData(),
        ],
      ],
      'query foo with allowed_iconset do not include icon' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'fo', 'allowed_iconset' => 'qux+bar'],
        'expectedData' => [],
      ],
      'query foo with max_result 2 for 3 valid icons' => [
        'iconsData' => self::createIconData(NULL, 'foo-qux') + self::createIconData('quux', 'qux-foo') + self::createIconData('corge', 'baz-foo'),
        'queryParams' => ['q' => 'foo', 'max_result' => 2],
        'expectedData' => [
          self::createIconResultData(NULL, 'foo-qux'),
          self::createIconResultData('quux', 'qux-foo'),
        ],
      ],
      'query string part name' => [
        'iconsData' => self::createIconData(NULL, NULL, 'Some Name String'),
        'queryParams' => ['q' => 'tri'],
        'expectedData' => [
          self::createIconResultData(NULL, NULL, 'Some Name String'),
        ],
      ],
      'query string part name with non ascii chars' => [
        'iconsData' => self::createIconData(NULL, NULL, 'Some Name String'),
        'queryParams' => ['q' => '%!?OME*$$'],
        'expectedData' => [
          self::createIconResultData(NULL, NULL, 'Some Name String'),
        ],
      ],
      'query string icon_id' => [
        'iconsData' => self::createIconData(NULL, 'my_icon_id', 'Some name'),
        'queryParams' => ['q' => 'icon'],
        'expectedData' => [
          self::createIconResultData(NULL, 'my_icon_id', 'Some name'),
        ],
      ],
      'query non ascii letter' => [
        'iconsData' => self::createIconData('%2$*', 'à(5çè', '?:!!/"&', ']}=(-_ù,'),
        'queryParams' => ['q' => '!!'],
        'expectedData' => [
          self::createIconResultData('%2$*', 'à(5çè', '?:!!/"&', ']}=(-_ù,'),
        ],
      ],
      'query iconset no result' => [
        'iconsData' => self::createIconData('my_iconset'),
        'queryParams' => ['q' => 'iconset'],
        'expectedData' => [],
      ],
      'query empty' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => ''],
        'expectedData' => NULL,
      ],
      'query space' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => ' '],
        'expectedData' => NULL,
      ],
      'query foo with empty icons' => [
        'iconsData' => [],
        'queryParams' => ['q' => 'foo', 'allowed_iconset' => 'foo+bar'],
        'expectedData' => NULL,
      ],
    ];
  }

  /**
   * Creates icon data array.
   *
   * @param string|null $iconset_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $icon_name
   *   The name of the icon.
   * @param string|null $iconset_label
   *   The label of the icon set.
   *
   * @return array The icon data array.
   */
  private static function createIconData(?string $iconset_id = NULL, ?string $icon_id = NULL, ?string $icon_name = NULL, ?string $iconset_label = NULL): array {
    return [
      ($iconset_id ?? 'foo') . ':' . ($icon_id ?? 'bar') => [
        'name' => $icon_name ?? 'Bar',
        'source' => 'qux/corge',
        'iconset_id' => $iconset_id ?? 'foo',
        'iconset_label' => $iconset_label ?? 'Baz',
      ],
    ];
  }

  /**
   * Creates icon data result array.
   *
   * @param string|null $iconset_id
   *   The ID of the icon set.
   * @param string|null $icon_id
   *   The ID of the icon.
   * @param string|null $icon_name
   *   The name of the icon.
   * @param string|null $iconset_label
   *   The label of the icon set.
   *
   * @return array The icon data array.
   */
  private static function createIconResultData(?string $iconset_id = NULL, ?string $icon_id = NULL, ?string $icon_name = NULL, ?string $iconset_label = NULL): array {
    return [
      'value' => ($iconset_id ?? 'foo') . ':' . ($icon_id ?? 'bar'),
      'label' => new FormattableMarkup('<span class="ui-menu-icon">@icon</span> @name', [
        '@icon' => '_rendered_',
        '@name' => ($icon_name ?? 'Bar') . ' (' . ($iconset_label ?? 'Baz') . ')',
      ]),
    ];
  }

  /**
   * Create a mock icon.
   *
   * @param array $iconData
   *   The icon data to create.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface
   *   The icon mocked.
   */
  private function createMockIcon(array $iconData): IconDefinitionInterface {
    $icon = $this->prophesize(IconDefinitionInterface::class);
    $icon->getRenderable(['width' => $iconData['width'], 'height' => $iconData['height']])->willReturn(['#markup' => '<svg></svg>']);
    return $icon->reveal();
  }

  /**
   * Create an icon.
   *
   * @param array $iconData
   *   The icon data to create.
   *
   * @return \Drupal\ui_icons\IconDefinitionInterface
   *   The icon mocked.
   */
  private function createIcon(array $iconData): IconDefinitionInterface {
    return IconDefinition::create(
      $iconData['name'],
      $iconData['source'],
      [
        'iconset_id' => $iconData['iconset_id'],
        'iconset_label' => $iconData['iconset_label'],
      ]
    );
  }

}
