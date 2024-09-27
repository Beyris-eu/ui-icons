<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\ui_icons\Unit\IconUnitTestCase;
use Drupal\ui_icons\Controller\IconAutocompleteController;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests IconAutocompleteController Controller class.
 *
 * @group ui_icons
 *
 * @cspell:ignore tri OME
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
    foreach ($iconsData as $iconId => $iconData) {
      $icons[$iconId] = $this->createTestIcon($iconData);
    }
    $prophecy->getIcons()->willReturn($icons);

    $iconPackManager = $prophecy->reveal();
    $this->container->set('plugin.manager.ui_icons_pack', $iconPackManager);

    $prophecy = $this->prophesize(RendererInterface::class);
    $prophecy->renderInIsolation(Argument::any())->willReturn('_rendered_');
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
      'query icon id' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'bar'],
        'expectedData' => [
          self::createIconResultData(),
        ],
      ],
      'query foo with allowed_icon_pack include icon' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'bar', 'allowed_icon_pack' => 'foo+bar'],
        'expectedData' => [
          self::createIconResultData(),
        ],
      ],
      'query foo with allowed_icon_pack do not include icon' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'foo', 'allowed_icon_pack' => 'qux+bar'],
        'expectedData' => [],
      ],
      'query foo with max_result 2 for 3 valid icons' => [
        'iconsData' => self::createIconData('foo', 'foo-qux') + self::createIconData('quux', 'qux-foo') + self::createIconData('corge', 'baz-foo'),
        'queryParams' => ['q' => 'foo', 'max_result' => 2],
        'expectedData' => [
          self::createIconResultData('foo', 'foo-qux'),
          self::createIconResultData('quux', 'qux-foo'),
        ],
      ],
      'query string part name' => [
        'iconsData' => self::createIconData(NULL, 'some-name-string'),
        'queryParams' => ['q' => 'tri'],
        'expectedData' => [
          self::createIconResultData(NULL, 'some-name-string'),
        ],
      ],
      'query multiple words' => [
        'iconsData' => self::createIconData('foo', 'foo-bar') + self::createIconData('foo', 'qux-foo', 'Biz'),
        'queryParams' => ['q' => 'foo Baz'],
        'expectedData' => [
          self::createIconResultData('foo', 'foo-bar'),
        ],
      ],
      'query string part name with non ascii chars' => [
        'iconsData' => self::createIconData(NULL, 'Some Name String'),
        'queryParams' => ['q' => '%!?OME*$$'],
        'expectedData' => [
          self::createIconResultData(NULL, 'Some Name String'),
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
        'iconsData' => self::createIconData('%2$*', 'à(5çè', '?:!!/"&'),
        'queryParams' => ['q' => ':!!'],
        'expectedData' => [
          self::createIconResultData('%2$*', 'à(5çè', '?:!!/"&'),
        ],
      ],
      'query icon pack no result' => [
        'iconsData' => self::createIconData('my_icon_pack'),
        'queryParams' => ['q' => 'icon_pack'],
        'expectedData' => [],
      ],
      'query 1 char' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'f'],
        'expectedData' => [],
      ],
      'query 2 chars' => [
        'iconsData' => self::createIconData(),
        'queryParams' => ['q' => 'fo'],
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
      'query spaces' => [
        'iconsData' => self::createIconData(),
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

}
