<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\Template\IconTwigExtension;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\ui_icons\Template\IconTwigExtension
 *
 * @group ui_icons
 */
class IconTwigExtensionTest extends TestCase {

  /**
   * The plugin manager.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private IconPackManagerInterface $pluginManagerIconPack;

  /**
   * The twig extension.
   *
   * @var \Drupal\ui_icons\Template\IconTwigExtension
   */
  private IconTwigExtension $iconTwigExtension;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->pluginManagerIconPack = $this->createMock(IconPackManagerInterface::class);
    $this->iconTwigExtension = new IconTwigExtension($this->pluginManagerIconPack);
  }

  /**
   * Test the getFunctions method.
   */
  public function testGetFunctions(): void {
    $functions = $this->iconTwigExtension->getFunctions();
    $this->assertCount(2, $functions);
    $this->assertEquals('icon', $functions[0]->getName());
    $this->assertEquals('icon_preview', $functions[1]->getName());
  }

  /**
   * Test the getIconRenderable method.
   */
  public function testGetIconRenderableIconNotFound(): void {
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn(NULL);

    $result = $this->iconTwigExtension->getIconRenderable('pack_id', 'icon_id');
    $this->assertEmpty($result);
  }

  /**
   * Test the getIconRenderable method.
   */
  public function testGetIconRenderable(): void {
    $settings = ['foo' => 'bar'];
    $iconMock = $this->createMock(IconDefinitionInterface::class);
    $iconMock->method('getRenderable')
      ->with($settings)
      ->willReturn(['rendered_icon'] + $settings);

    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn($iconMock);

    $result = $this->iconTwigExtension->getIconRenderable('pack_id', 'icon_id', $settings);
    $this->assertEquals(['rendered_icon'] + $settings, $result);
  }

  /**
   * Test the getIconPreview method.
   */
  public function testGetIconPreview(): void {
    $settings = ['foo' => 'bar'];
    $iconMock = $this->createMock(IconDefinitionInterface::class);
    $iconMock->method('getPreview')
      ->with($settings)
      ->willReturn(['preview_icon'] + $settings);

    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn($iconMock);

    $result = $this->iconTwigExtension->getIconPreview('pack_id', 'icon_id', $settings);
    $this->assertEquals(['preview_icon'] + $settings, $result);
  }

  /**
   * Test the getIconPreview method with invalid icon.
   */
  public function testGetIconPreviewInvalidIcon(): void {
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn(NULL);

    $result = $this->iconTwigExtension->getIconPreview('pack_id', 'icon_id', []);
    $this->assertEquals([], $result);
  }

}
