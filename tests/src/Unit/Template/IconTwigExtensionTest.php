<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\Template\IconTwigExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

/**
 * Test the class IconTwigExtension.
 */
class IconTwigExtensionTest extends TestCase {

  /**
   * The plugin manager.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface
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
    $this->assertIsArray($functions);
    $this->assertCount(1, $functions);
    $this->assertInstanceOf(TwigFunction::class, $functions[0]);
    $this->assertEquals('icon', $functions[0]->getName());
  }

  /**
   * Test the getIconRenderable method.
   */
  public function testGetIconRenderableReturnsEmptyArrayWhenIconNotFound(): void {
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn(NULL);

    $result = $this->iconTwigExtension->getIconRenderable('icon_pack_id', 'icon_id');
    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Test the getIconRenderable method.
   */
  public function testGetIconRenderableReturnsRenderableArray(): void {
    $iconMock = $this->createMock(IconDefinitionInterface::class);
    $iconMock->method('getRenderable')
      ->willReturn(['rendered_icon']);

    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn($iconMock);

    $result = $this->iconTwigExtension->getIconRenderable('icon_pack_id', 'icon_id');
    $this->assertIsArray($result);
    $this->assertEquals(['rendered_icon'], $result);
  }

}
