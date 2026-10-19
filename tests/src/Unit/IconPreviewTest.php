<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\IconPreview;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconPreview class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPreview::class)]
#[Group('ui_icons')]
class IconPreviewTest extends UnitTestCase {

  use IconTestTrait;

  /**
   * Test the preview template of a pack with a library.
   */
  public function testGetPreviewTemplateWithLibrary(): void {
    $icon = $this->createTestIcon([
      'pack_id' => 'pack_id',
      'icon_id' => 'icon_id',
      'source' => 'foo/bar.svg',
      'preview' => '_preview_ {{ icon_id }}',
      'library' => 'foo/bar',
    ]);

    $preview = IconPreview::getPreview($icon, [
      'size' => 24,
      'label' => 'Overridden',
      'color' => 'red',
    ]);

    $this->assertSame([
      '#type' => 'inline_template',
      '#template' => '_preview_ {{ icon_id }}',
      // Icon values win over settings of the same name.
      '#context' => [
        'size' => 24,
        'label' => 'pack_id:icon_id',
        'color' => 'red',
        'icon_id' => 'icon_id',
        'pack_id' => 'pack_id',
        'extractor' => NULL,
        'source' => 'foo/bar.svg',
        'content' => NULL,
      ],
      '#attached' => ['library' => ['foo/bar']],
    ], $preview);
  }

}
