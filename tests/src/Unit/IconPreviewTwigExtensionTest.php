<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Template\IconPreviewTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconPreviewTwigExtension class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPreviewTwigExtension::class)]
#[Group('ui_icons')]
class IconPreviewTwigExtensionTest extends UnitTestCase {

  use IconTestTrait;

  /**
   * The plugin manager.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private IconPackManagerInterface $pluginManagerIconPack;

  /**
   * The twig extension.
   *
   * @var \Drupal\ui_icons\Template\IconPreviewTwigExtension
   */
  private IconPreviewTwigExtension $iconPreviewTwigExtension;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginManagerIconPack = $this->createMock(IconPackManagerInterface::class);
    $this->iconPreviewTwigExtension = new IconPreviewTwigExtension($this->pluginManagerIconPack);
  }

  /**
   * Test the getFunctions method.
   */
  public function testGetFunctions(): void {
    $functions = $this->iconPreviewTwigExtension->getFunctions();
    $this->assertCount(1, $functions);
    $this->assertEquals('icon_preview', $functions[0]->getName());
  }

  /**
   * Test the getIconPreview method.
   */
  public function testGetIconPreview(): void {
    $settings = ['foo' => 'bar'];

    // A real definition: getId() is the full id, getIconId() the short one.
    $icon = $this->createTestIcon([
      'pack_id' => 'pack_id',
      'icon_id' => 'icon_id',
      'source' => 'foo/bar.svg',
    ]);
    $this->pluginManagerIconPack
      ->expects($this->once())
      ->method('getIcon')
      ->with('pack_id:icon_id')
      ->willReturn($icon);

    $result = $this->iconPreviewTwigExtension->getIconPreview('pack_id', 'icon_id', $settings);
    $expected = [
      '#theme' => 'icon_preview',
      '#icon_label' => 'pack_id:icon_id',
      '#icon_name' => 'Icon Id',
      '#icon_id' => 'icon_id',
      '#pack_id' => 'pack_id',
      '#extractor' => NULL,
      '#source' => 'foo/bar.svg',
      '#library' => NULL,
      '#settings' => $settings,
    ];
    $this->assertSame($expected, $result);
  }

  /**
   * Test the getIconPreview method falls back to a default size.
   */
  public function testGetIconPreviewNullSettings(): void {
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn($this->createTestIcon());

    $result = $this->iconPreviewTwigExtension->getIconPreview('foo', 'bar', NULL);
    $this->assertSame(['size' => 32], $result['#settings']);
  }

  /**
   * Test the getIconPreview method with invalid icon.
   */
  public function testGetIconPreviewInvalidIcon(): void {
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn(NULL);

    $result = $this->iconPreviewTwigExtension->getIconPreview('pack_id', 'icon_id', []);
    $this->assertEquals([], $result);
  }

}
