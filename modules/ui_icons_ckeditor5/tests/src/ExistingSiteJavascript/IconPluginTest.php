<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_ckeditor5\ExistingSiteJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\ConstraintViolation;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;

/**
 * Test the UI icons CKEditor features on a local existing site.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[Group('ui_icons_local')]
class IconPluginTest extends ExistingSiteSelenium2DriverTestBase {

  use CKEditor5TestTrait;
  use ScreenShotTrait;

  /**
   * Provide values for ::testIconPlugin.
   */
  public static function providerIconPlugin(): array {
    return [
      'icon with default settings' => [
        'icon_id' => 'drupal',
        'expected_icon_id' => 'core:drupal-logo',
        'expected_class' => 'icon icon-drupal-logo',
        'expected_filename' => '/core/misc/logo/drupal-logo.svg',
        'fill_settings' => FALSE,
        'settings' => [
          'width' => 32,
          'height' => 32,
        ],
      ],
      // 'icon with new settings' => [
      //   'icon_id' => 'drupal',
      //   'expected_icon_id' => 'core:drupal-logo',
      //   'expected_class' => 'icon icon-drupal-logo',
      //   'expected_filename' => '/core/misc/logo/drupal-logo.svg',
      //   'fill_settings' => TRUE,
      //   'settings' => [
      //     'width' => 98,
      //     'height' => 99,
      //   ],
      // ],
    ];
  }

  /**
   * Test the CKEditor icon plugin on local running site.
   */
  #[DataProvider('providerIconPlugin')]
  public function testIconPlugin(string $icon_id, string $expected_icon_id, string $expected_class, string $expected_filename, bool $fill_settings, array $settings): void {
    $this->getSession()->resizeWindow(1400, 1024);

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $user = User::load(2);
    $user->passRaw = 'password';
    $this->drupalLogin($user);

    $this->drupalGet('/node/add/article');
    $this->waitForEditor();

    // Ensure that CKEditor 5 is focused.
    $this->click('.ck-content');

    // $this->captureScreenshot();

    $this->assertEditorButtonEnabled('Insert Icon');
    $this->pressEditorButton('Insert Icon');

    // Our modal appear with input selector.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $input_field = $assert_session->waitForElementVisible('css', '[name="icon[icon_id]"]');
    $this->assertNotNull($input_field);

    // Make sure the input field can have focus and we can type into it.
    $input_field->setValue($icon_id);
    $this->getSession()->getDriver()->keyDown($input_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();

    // Check the autocomplete results.
    $results = $page->findAll('css', '.ui-autocomplete li');
    $this->assertCount(2, $results);

    $this->assertSession()->elementTextContains('css', '.ui-autocomplete li .ui-icons-result-icon-name', 'Drupal Logo');
    $page->find('css', 'ul.ui-autocomplete li')->click();

    if (TRUE === $fill_settings) {
      // Need to open settings to be able to interact.
      // $this->getSession()->getPage()->find('css', '.ui-icons-settings-wrapper')->click();

      // $this->captureScreenshot();
      // $setting_name = '[name="icon[icon_settings][%s][%s]"]';
      // // Fill settings with value, printed and form id are not the same.
      // foreach ($settings as $key => $value) {
      //   $assert_session->elementExists('css', sprintf($setting_name, self::TEST_ICON_PACK_ID, $key))->setValue($value);
      // }
      // $this->captureScreenshot();
    }

    $assert_session->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Save');
    $this->captureScreenshot();

    $icon_ckeditor_preview = $assert_session->waitForElementVisible('css', '.ck-content .drupal-icon span img');

    $this->assertNotNull($icon_ckeditor_preview);
    $this->assertIconValues($icon_ckeditor_preview, $expected_filename, $expected_class, $settings);

    // Check the text filter <drupal-icon> inserted properly.
    $xpath = new \DOMXPath($this->getEditorDataAsDom());
    $drupal_icon = $xpath->query('//drupal-icon')[0];
    $this->assertSame($expected_icon_id, $drupal_icon->getAttribute('data-icon-id'));

    // Compare settings in the html.
    $data_icon_settings = json_decode($drupal_icon->getAttribute('data-icon-settings'), TRUE);
    foreach ($settings as $key => $setting) {
      // Because of json we lost types.
      $this->assertSame((string) $data_icon_settings[$key], (string) $setting);
    }

    $this->submitForm([
      'title[0][value]' => 'My test content',
    ], 'Save');

    $display_icon = $assert_session->elementExists('css', '.drupal-icon img');

    $this->assertNotNull($display_icon);
    $this->assertIconValues($display_icon, $expected_filename, $expected_class, $settings);
  }

  /**
   * Test icon values.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The NodeElement whose icon values are to be asserted.
   * @param string $filename
   *   The expected filename that the 'src' attribute should end with.
   * @param string $class
   *   The expected class that the 'class' attribute should match.
   * @param array $settings
   *   An associative array of additional attributes and their expected values.
   */
  private function assertIconValues(NodeElement $element, string $filename, string $class, array $settings = []): void {
    $this->assertStringEndsWith($filename, $element->getAttribute('src'));
    $this->assertEquals($class, $element->getAttribute('class'));
    foreach ($settings as $key => $expected) {
      $this->assertSame((string) $expected, (string) $element->getAttribute($key));
    }
  }
}