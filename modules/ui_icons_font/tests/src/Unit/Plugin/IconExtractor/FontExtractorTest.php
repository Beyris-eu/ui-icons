<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_font\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons_font\Plugin\IconExtractor\FontExtractor;

/**
 * @coversDefaultClass \Drupal\ui_icons_font\Plugin\IconExtractor\FontExtractor
 *
 * @group ui_icons
 */
class FontExtractorTest extends UnitTestCase {

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_font';

  /**
   * Test the FontExtractor::discoverIcons() method.
   */
  public function testDiscoverIconsFont(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/foo.codepoints',
            'icons/foo.json',
            'icons/foo.yml',
            'icons/foo.yaml',
            'icons/foo',
            'icons/foo.none',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => DRUPAL_ROOT . '/modules/custom/ui_icons/modules/ui_icons_font/tests/modules/ui_icons_font_test',

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();

    $expected = [
      'codepoints_foo',
      'codepoints_baz',
      'json_foo',
      'json_baz',
      'yml_foo',
      'yml_baz',
      'yaml_foo',
      'yaml_baz',
    ];
    foreach ($result as $index => $icon) {
      $this->assertSame($this->pluginId . ':' . $expected[$index], $icon->getId());
    }
  }

  /**
   * Test the FontExtractor::discoverIcons() method with empty files.
   */
  public function testDiscoverIconsFontEmpty(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/empty.codepoints',
            'icons/empty.json',
            'icons/empty.ttf',
            'icons/empty.yml',
            'icons/empty.yaml',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => DRUPAL_ROOT . '/modules/custom/ui_icons/modules/ui_icons_font/tests/modules/ui_icons_font_test',

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();
    $this->assertEmpty($result);
  }

  /**
   * Test the FontExtractor::discoverIcons() method with empty files.
   */
  public function testDiscoverIconsFontInvalid(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/invalid.json',
            'icons/invalid.yml',
            'icons/invalid.yaml',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => DRUPAL_ROOT . '/modules/custom/ui_icons/modules/ui_icons_font/tests/modules/ui_icons_font_test',

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();
    $this->assertEmpty($result);
  }

  /**
   * Test the FontExtractor::discoverIcons() method with non existent files.
   */
  public function testDiscoverIconsFontNoFile(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/do_not_exist.codepoints',
            'icons/do_not_exist.json',
            'icons/do_not_exist.yml',
            'icons/do_not_exist.yaml',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => DRUPAL_ROOT . '/modules/custom/ui_icons/modules/ui_icons_font/tests/modules/ui_icons_font_test',

      ],
      $this->pluginId,
      [],
    );

    // PHPUnit 10 cannot expect warnings, so we have to catch them ourselves.
    // Thanks to: Drupal\Tests\Component\PhpStorage\FileStorageTest.
    $messages = [];
    set_error_handler(function (int $errno, string $errstr) use (&$messages): void {
      $messages[] = [$errno, $errstr];
    });

    $result = $fontExtractorPlugin->discoverIcons();

    restore_error_handler();

    $this->assertCount(4, $messages);
    $this->assertSame(E_WARNING, $messages[0][0]);
    $this->assertStringContainsString('Failed to open stream: No such file or directory', $messages[0][1]);
  }

  /**
   * Test the FontExtractor::discoverIcons() method with ttf file.
   */
  public function testDiscoverIconsFontTtf(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/foo.ttf',
          ],
          'offset' => 3,
        ],
        'template' => '_foo_',
        'absolute_path' => DRUPAL_ROOT . '/modules/custom/ui_icons/modules/ui_icons_font/tests/modules/ui_icons_font_test',

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();

    $expected = [
      'at',
      'A',
    ];
    foreach ($result as $index => $icon) {
      $this->assertSame($this->pluginId . ':' . $expected[$index], $icon->getId());

    }
  }

  /**
   * Test the PathExtractor::discoverIcons() method with no sources.
   */
  public function testDiscoverIconsExceptionNoSources(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'config' => [],
      ],
      $this->pluginId,
      [],
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_font require this value.');
    $fontExtractorPlugin->discoverIcons();
  }

}
