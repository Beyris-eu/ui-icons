<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

// cspell:ignore corge
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconDefinition;
use Drupal\ui_icons\IconFinder;
use Drupal\ui_icons_test\Plugin\IconExtractor\TestExtractor;
use Drupal\ui_icons_test\Plugin\IconExtractor\TestExtractorWithFinder;

/**
 * Tests ui_icons extractor with base and finder base plugin.
 *
 * @group ui_icons
 */
class ExtractorTest extends UnitTestCase {

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_extractor';

  /**
   * Test the IconExtractorBase::label and IconExtractorBase::description.
   */
  public function testIconExtractorBase(): void {
    $extractorPlugin = new TestExtractor(
      [],
      $this->pluginId,
      [
        'label' => 'foo',
        'description' => 'bar',
      ],
    );

    $this->assertSame('foo', $extractorPlugin->label());
    $this->assertSame('bar', $extractorPlugin->description());
  }

  /**
   * Test the IconExtractorBase::buildConfigurationForm.
   */
  public function testBuildConfigurationForm(): void {
    // Test no settings.
    $extractorPlugin = new TestExtractor(
      [],
      $this->pluginId,
      [],
    );

    $form_state = $this->getMockBuilder('Drupal\Core\Form\FormState')
      ->disableOriginalConstructor()
      ->getMock();
    $result = $extractorPlugin->buildConfigurationForm([], $form_state);
    $this->assertEmpty($result);

    // Test with settings.
    $extractorPlugin = new TestExtractor(
      [
        'settings' => [
          'foo' => [
            'type' => 'string',
          ],
        ],
      ],
      $this->pluginId,
      [],
    );

    $subform_state = $this->createMock(SubformStateInterface::class);
    $form_state = $this->createMock(SubformStateInterface::class);
    $form_state->method('getCompleteFormState')->willReturn($subform_state);
    $result = $extractorPlugin->buildConfigurationForm([], $form_state);

    $expected = [
      'foo' => [
        '#title' => 'foo',
        '#type' => 'textfield',
      ],
    ];
    $this->assertSame($expected, $result);
  }

  /**
   * Test the IconExtractorBase:createIcon method.
   */
  public function testCreateIcon(): void {
    $extractorPlugin = new TestExtractor(
      [
        'id' => $this->pluginId,
        'template' => '_bar_',
      ],
      $this->pluginId,
      [],
    );

    $icon = $extractorPlugin->createIcon('foo');

    $expected = IconDefinition::create(
      $this->pluginId,
      'foo',
      '_bar_',
      NULL,
      NULL,
      [
        'id' => $this->pluginId,
        'template' => '_bar_',
      ],
    );

    $this->assertEquals($expected, $icon);

    $icon = $extractorPlugin->createIcon('foo', 'bar', 'baz', ['corge' => 'qux']);

    $expected = IconDefinition::create(
      $this->pluginId,
      'foo',
      '_bar_',
      'bar',
      'baz',
      [
        'id' => $this->pluginId,
        'template' => '_bar_',
        'corge' => 'qux',
      ],
    );

    $this->assertEquals($expected, $icon);
  }

  /**
   * Test the IconExtractorBase:createIcon method with Exception.
   */
  public function testCreateIconExceptionTemplate(): void {
    $extractorPlugin = new TestExtractor(
      [],
      $this->pluginId,
      [],
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `template` in your definition, extractor test_extractor require this value.');
    $extractorPlugin->createIcon('foo');
  }

  /**
   * Test the IconExtractorWithFinder:checkRequireConfigSources method.
   */
  public function testDiscoverIconsExceptionSource(): void {
    $extractorPlugin = new TestExtractorWithFinder(
      [],
      $this->pluginId,
      [],
      $this->createMock(IconFinder::class),
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_extractor require this value.');
    $extractorPlugin->discoverIcons();
  }

  /**
   * Test the IconExtractorWithFinder:checkRequireConfigSources method.
   */
  public function testDiscoverIconsExceptionSourceEmpty(): void {
    $pathExtractorPlugin = new TestExtractorWithFinder(
      [
        'config' => ['sources' => []],
      ],
      $this->pluginId,
      [],
      $this->createMock(IconFinder::class),
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_extractor require this value.');
    $pathExtractorPlugin->discoverIcons();
  }

  /**
   * Test the IconExtractorWithFinder::getFilesFromSources method.
   */
  public function testGetFilesFromSourcesExceptionRelativePath(): void {
    $pathExtractorPlugin = new TestExtractorWithFinder(
      [
        'config' => ['sources' => ['foo/bar']],
      ],
      $this->pluginId,
      [
        'label' => 'Test',
        'description' => 'Test description',
      ],
      $this->createMock(IconFinder::class),
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Empty relative path for extractor test_extractor.');
    $pathExtractorPlugin->discoverIcons();
  }

}
