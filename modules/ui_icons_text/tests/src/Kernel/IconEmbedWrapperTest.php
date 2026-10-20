<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_text\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\ui_icons_text\Plugin\Filter\IconEmbed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the IconEmbed filter with and without the span wrapping icons.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconEmbed::class)]
#[Group('ui_icons')]
class IconEmbedWrapperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'filter',
    'ui_icons',
    'ui_icons_text',
    'ui_icons_test',
    'ui_icons_text_test',
  ];

  /**
   * An icon tag with the attributes the wrapper carries.
   */
  private const TAG = '<drupal-icon data-icon-id="%s" class="bi custom" aria-label="Star"></drupal-icon>';

  /**
   * Processes an icon tag in a sentence.
   */
  private function process(string $icon_full_id, array $settings): string {
    $filter = $this->container->get('plugin.manager.filter')
      ->createInstance('icon_embed', ['settings' => $settings]);
    $html = '<p>' . sprintf(self::TAG, $icon_full_id) . ' Text</p>';
    return $filter->process($html, 'en')->getProcessedText();
  }

  /**
   * Tests icons keep their wrapper by default.
   */
  public function testWrapperByDefault(): void {
    $processed = $this->process('test_single_root:star', []);

    $this->assertStringStartsWith('<p><span class="bi custom drupal-icon" aria-label="Star"><svg', $processed);
  }

  /**
   * Tests a single element icon takes the attributes without the wrapper.
   *
   * Utility classes, Bootstrap's icon-link for one, expect the icon and the
   * text side by side.
   */
  public function testSingleElementIconWithoutWrapper(): void {
    $processed = $this->process('test_single_root:star', ['wrapper' => FALSE]);

    $this->assertStringNotContainsString('<span', $processed);
    $this->assertMatchesRegularExpression('/^<p><svg [^>]*class="bi custom drupal-icon"/', $processed);
    $this->assertStringContainsString('aria-label="Star"', $processed);
    $this->assertMatchesRegularExpression('/viewbox="0 0 16 16"/i', $processed);
    $this->assertStringEndsWith('</svg> Text</p>', $processed);
  }

  /**
   * Tests other icons keep the wrapper, as their attributes need one.
   */
  public function testOtherIconsKeepTheWrapper(): void {
    // The test_path template renders text before its image.
    $processed = $this->process('test_path:foo', ['wrapper' => FALSE]);
    $this->assertStringStartsWith('<p><span class="bi custom drupal-icon" aria-label="Star">foo: <img', $processed);

    $processed = $this->process('test_single_root:missing', ['wrapper' => FALSE]);
    $this->assertSame('<p><span class="bi custom drupal-icon" aria-label="Star"></span> Text</p>', $processed);
  }

  /**
   * Tests the post update keeps the wrapper of the existing text formats.
   */
  public function testPostUpdateKeepsTheWrapper(): void {
    FilterFormat::create([
      'format' => 'icons',
      'name' => 'Icons',
      'filters' => [
        'icon_embed' => [
          'status' => TRUE,
        ],
      ],
    ])->save();
    // A format saved before the wrapper setting existed.
    $this->config('filter.format.icons')->clear('filters.icon_embed.settings.wrapper')->save();
    $this->assertNull($this->config('filter.format.icons')->get('filters.icon_embed.settings.wrapper'));

    require_once $this->root . '/' . $this->container->get('extension.list.module')->getPath('ui_icons_text') . '/ui_icons_text.post_update.php';
    $sandbox = [];
    ui_icons_text_post_update_icon_embed_wrapper($sandbox);

    $this->assertTrue($this->config('filter.format.icons')->get('filters.icon_embed.settings.wrapper'));
  }

}
