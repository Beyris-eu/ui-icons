<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_library\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ui_icons_library\Controller\LibraryIndex;
use Drupal\ui_icons_library\Form\LibrarySearchForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the choices made in the icon library.
 *
 * The testing profile turns the dynamic page cache on, as on most sites: a
 * page built from the choices of one user must not reach another one.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(LibraryIndex::class)]
#[CoversClass(LibrarySearchForm::class)]
#[Group('ui_icons')]
class LibraryStateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_icons_library',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests each user keeps their own choices.
   */
  public function testStatePerUser(): void {
    $first = $this->drupalCreateUser(['access ui icons library']);
    $second = $this->drupalCreateUser(['access ui icons library']);

    $this->drupalLogin($first);
    $this->drupalGet('admin/appearance/ui/icons');
    $this->clickLink('show disabled pack');
    $this->assertSession()->linkExists('hide disabled pack');
    $this->assertSession()->pageTextContains('(Disabled)');

    $this->drupalGet('admin/appearance/ui/icons/test_path');
    $this->submitForm(['search' => 'foo'], 'Submit');
    $this->assertSession()->pageTextContains('icons for keyword: foo');

    $this->drupalLogin($second);
    $this->drupalGet('admin/appearance/ui/icons');
    $this->assertSession()->linkExists('show disabled pack');
    $this->assertSession()->pageTextNotContains('(Disabled)');

    $this->drupalGet('admin/appearance/ui/icons/test_path');
    $this->assertSession()->fieldValueEquals('search', '');
    $this->assertSession()->pageTextNotContains('for keyword');
  }

  /**
   * Tests the show disabled toggle changes only from a link with a token.
   */
  public function testModeNeedsToken(): void {
    $this->drupalLogin($this->drupalCreateUser(['access ui icons library']));

    $this->drupalGet('admin/icons/on');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/appearance/ui/icons');
    $this->assertSession()->linkExists('show disabled pack');

    $this->clickLink('show disabled pack');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('hide disabled pack');

    $this->clickLink('hide disabled pack');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('show disabled pack');
  }

}
