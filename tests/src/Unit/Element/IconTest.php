<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Element\Icon;
use Drupal\ui_icons\IconDefinition;

/**
 * Tests Icon RenderElement class.
 *
 * @group ui_icons
 */
class IconTest extends UnitTestCase {

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
   * Test the getInfo method.
   */
  public function testGetInfo(): void {
    $icon = new Icon([], 'test', 'test');
    $info = $icon->getInfo();

    $this->assertArrayHasKey('#pre_render', $info);
    $this->assertArrayHasKey('#icon_pack', $info);
    $this->assertArrayHasKey('#icon', $info);
    $this->assertArrayHasKey('#context', $info);

    $this->assertSame([['Drupal\ui_icons\Element\Icon', 'preRenderIcon']], $info['#pre_render']);
    $this->assertSame([], $info['#context']);
  }

  /**
   * Test the preRenderIcon method.
   *
   * @param array $data
   *   The icon data.
   * @param array $expected
   *   The result expected.
   *
   * @dataProvider providerPreRenderIcon
   */
  public function testPreRenderIcon(array $data, array $expected): void {
    $icon = IconDefinition::create(
      $data['icon_id'],
      $data['icon_source'],
      [
        'icon_pack_id' => $data['icon_pack_id'],
        'icon_pack_label' => $data['icon_pack_label'] ?? '',
        'template' => $data['icon_template'] ?? '',
        'library' => $data['icon_library'] ?? '',
        'content' => $data['icon_content'] ?? '',
      ],
      $data['icon_group'] ?? NULL,
    );

    $prophecy = $this->prophesize('\Drupal\ui_icons\Plugin\IconPackManagerInterface');
    $prophecy->getIcon($data['icon_pack_id'] . ':' . $data['icon_id'])
      ->willReturn($icon);

    $pluginManagerIconPack = $prophecy->reveal();
    $this->container->set('plugin.manager.ui_icons_pack', $pluginManagerIconPack);

    $element = [
      '#type' => 'ui_icon',
      '#icon_pack' => $data['icon_pack_id'],
      '#icon' => $data['icon_id'],
      '#context' => $data['icon_settings'] ?? [],
    ];

    $actual = Icon::preRenderIcon($element);

    $this->assertEquals($expected, $actual['inline-template']);
  }

  /**
   * Test the preRenderIcon method.
   */
  public function testPreRenderIconNoIcon(): void {
    $prophecy = $this->prophesize('Drupal\ui_icons\Plugin\IconPackManagerInterface');
    $prophecy->getIcon('foo:bar')->willReturn(NULL);

    $pluginManagerIconPack = $prophecy->reveal();
    $this->container->set('plugin.manager.ui_icons_pack', $pluginManagerIconPack);

    $element = [
      '#type' => 'ui_icon',
      '#icon_pack' => 'foo',
      '#icon' => 'bar',
    ];

    $actual = Icon::preRenderIcon($element);

    $this->assertEquals($element, $actual);
  }

  /**
   * Provides data for testGetFilesFromSource.
   *
   * @return array
   *   Provide test data as:
   *   - array of information for the icon
   *   - result array of render element
   */
  public static function providerPreRenderIcon(): array {
    return [
      // Minimum icon definition.
      [
        [
          'icon_pack_id' => 'icon_pack_id',
          'icon_id' => 'icon_id',
          'icon_source' => '/foo/bar',
          'icon_template' => 'my_template',
        ],
        [
          '#type' => 'inline_template',
          '#template' => 'my_template',
          '#context' => [
            'icon_id' => 'icon_id',
            'source' => '/foo/bar',
            'content' => '',
            'icon_pack_label' => '',
          ],
        ],
      ],
      // Full icon definition.
      [
        [
          'icon_pack_id' => 'icon_pack_id',
          'icon_pack_label' => 'Baz',
          'icon_id' => 'icon_id',
          'icon_source' => '/foo/bar',
          'icon_group' => 'test_group',
          'icon_content' => 'test_content',
          'icon_template' => 'my_template',
          'icon_library' => 'my_theme/my_library',
          'icon_settings' => ['foo' => 'bar'],
        ],
        [
          '#type' => 'inline_template',
          '#template' => 'my_template',
          '#attached' => ['library' => ['my_theme/my_library']],
          '#context' => [
            'icon_id' => 'icon_id',
            'source' => '/foo/bar',
            'content' => 'test_content',
            'foo' => 'bar',
            'icon_pack_label' => 'Baz',
          ],
        ],
      ],
    ];
  }

}
