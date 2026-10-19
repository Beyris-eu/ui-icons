<?php

declare(strict_types=1);

// cspell:ignore dompdf
namespace Drupal\Tests\ui_icons_font\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_font\Hook\UiIconsFontRequirements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ui_icons_font runtime requirements.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsFontRequirements::class)]
#[Group('ui_icons')]
class UiIconsFontRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_font',
  ];

  /**
   * Test the warning when the font library is missing.
   */
  public function testLibraryMissing(): void {
    if (class_exists('\FontLib\Font')) {
      $this->markTestSkipped('The dompdf/php-font-lib library is installed.');
    }

    $this->assertSame([
      'title' => 'UI Icons Font',
      'value' => 'Missing Font library!',
      'description' => 'Install with: composer require dompdf/php-font-lib',
      'severity' => RequirementSeverity::Warning,
    ], $this->getRequirement());
  }

  /**
   * Test the requirement is met when the font library is available.
   */
  public function testLibraryDetected(): void {
    if (!class_exists('\FontLib\Font')) {
      // The hook only checks the class exists. Each test runs in its own
      // process, so the stand-in does not leak.
      class_alias(\stdClass::class, 'FontLib\Font');
    }

    $this->assertSame([
      'title' => 'UI Icons Font',
      'value' => 'Font library detected',
      'description' => '',
      'severity' => RequirementSeverity::OK,
    ], $this->getRequirement());
  }

  /**
   * Get the requirement of the module, with its strings rendered.
   */
  private function getRequirement(): array {
    $requirements = $this->container->get('module_handler')->invoke('ui_icons_font', 'runtime_requirements');

    return array_map(
      static fn (mixed $value): mixed => $value instanceof \Stringable ? (string) $value : $value,
      $requirements['ui_icons_font'],
    );
  }

}
