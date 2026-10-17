<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\IconRenderableSource;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\Source\IconSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings form of the icon sources.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconSource::class)]
#[CoversClass(IconRenderableSource::class)]
#[Group('ui_icons')]
class IconSourceSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_patterns',
    'ui_icons_test',
    'ui_patterns',
    'user',
  ];

  /**
   * Tests both sources suggest icons as a grid.
   *
   * Source forms are often narrow, a builder sidebar for instance, where a
   * grid shows more icons than a list of names.
   */
  #[DataProvider('providerSourceIds')]
  public function testResultFormatGrid(string $source_id): void {
    $source = $this->container->get('plugin.manager.ui_patterns_source')
      ->createInstance($source_id, ['settings' => ['value' => ['target_id' => 'test_path:foo']]]);

    $form = $source->settingsForm([], new FormState());

    $this->assertSame('icon_autocomplete', $form['value']['#type']);
    $this->assertSame('grid', $form['value']['#result_format']);
  }

  /**
   * Data provider for ::testResultFormatGrid().
   */
  public static function providerSourceIds(): array {
    return [
      'prop' => ['icon'],
      'slot' => ['icon_renderable'],
    ];
  }

}
