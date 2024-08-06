<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element\Attribute;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Attribute\UiIconsExtractor;

/**
 * Tests UiIconsExtractor FormAttributeElement class.
 *
 * @group ui_icons
 */
class UiIconsExtractorTest extends UnitTestCase {

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
    $this->container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($this->container);
  }

  /**
   * Test the _construct method.
   */
  public function testConstruct(): void {
    $plugin = new UiIconsExtractor(
      'foo',
      new TranslatableMarkup('Foo'),
      new TranslatableMarkup('Foo description'),
      NULL,
      ['bar' => 'baz'],
    );
    $plugin->setProvider('example');
    $this->assertEquals('example', $plugin->getProvider());
    $this->assertEquals('foo', $plugin->getId());

    // @phpstan-ignore-next-line
    $plugin->setClass('\Drupal\Foo');
    $this->assertEquals('\Drupal\Foo', $plugin->getClass());

    $this->assertEquals('Foo', $plugin->label->getUntranslatedString());
    $this->assertSame('Foo description', $plugin->description->getUntranslatedString());
    $this->assertSame(['bar' => 'baz'], $plugin->forms);
  }

}
