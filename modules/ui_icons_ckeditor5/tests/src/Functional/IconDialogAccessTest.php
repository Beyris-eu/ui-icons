<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_ckeditor5\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\ui_icons_ckeditor5\Form\IconDialog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the icon dialog is only reachable with access to its text format.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconDialog::class)]
#[Group('ui_icons')]
class IconDialogAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Grants 'access content' to everyone, as on a real site.
    'node',
    'ui_icons_ckeditor5',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the dialog follows the 'use' access of its text format.
   */
  public function testDialogAccess(): void {
    $format = FilterFormat::create([
      'format' => 'icon_format',
      'name' => 'Icon format',
      'filters' => [
        'icon_embed' => ['status' => TRUE],
      ],
    ]);
    $format->save();
    $url = Url::fromRoute('ui_icons_ckeditor5.icon_dialog', ['filter_format' => 'icon_format']);

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser([$format->getPermissionName()]));
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('icon[icon_id]');
  }

}
