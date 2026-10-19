<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ui_icons_field\Plugin\Field\FieldFormatter\IconLinkFormatter;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconLinkWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the icon link widget and formatter through the UI.
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconLinkWidget::class)]
#[CoversClass(IconLinkFormatter::class)]
#[Group('ui_icons')]
class IconLinkFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'link',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The field name.
   */
  private const FIELD_NAME = 'field_link';

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  private $displayRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Link',
    ])->save();

    $this->displayRepository = \Drupal::service('entity_display.repository');

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'administer nodes',
      'administer node display',
      'administer node fields',
      'administer node form display',
      'view the administration theme',
      'create article content',
    ]));
  }

  /**
   * Tests a link with an icon is saved and displayed.
   */
  public function testIconLinkSave(): void {
    $this->displayRepository->getFormDisplay('node', 'article')
      ->setComponent(self::FIELD_NAME, [
        'type' => 'icon_link_widget',
        'settings' => ['icon_position' => TRUE],
      ])
      ->save();
    $this->displayRepository->getViewDisplay('node', 'article')
      ->setComponent(self::FIELD_NAME, ['type' => 'icon_link_formatter'])
      ->save();

    $this->drupalGet('/node/add/article');
    $this->submitForm([
      'title[0][value]' => 'Test Article',
      self::FIELD_NAME . '[0][uri]' => 'https://www.drupal.org',
      self::FIELD_NAME . '[0][title]' => 'Drupal',
      self::FIELD_NAME . '[0][options][icon][icon_id]' => 'test_path:foo',
      self::FIELD_NAME . '[0][options][icon_display]' => 'after',
    ], 'Save');

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Article Test Article has been created.');
    $node = $this->drupalGetNodeByTitle('Test Article');
    $options = $node->get(self::FIELD_NAME)->first()->get('options')->getValue();
    $this->assertSame('test_path:foo', $options['icon']['target_id']);
    $this->assertSame('after', $options['icon_display']);

    $link = $assert_session->elementExists('css', 'a[href="https://www.drupal.org"]');
    $this->assertStringStartsWith('Drupal', trim($link->getText()));
    $assert_session->elementExists('css', 'a[href="https://www.drupal.org"] img.icon-foo');
  }

  /**
   * Tests the allowed icon packs are saved without unchecked packs.
   */
  public function testWidgetSettingsSave(): void {
    $this->displayRepository->getFormDisplay('node', 'article')
      ->setComponent(self::FIELD_NAME, ['type' => 'icon_link_widget'])
      ->save();

    $this->drupalGet('/admin/structure/types/manage/article/form-display');
    $this->submitForm([], self::FIELD_NAME . '_settings_edit');
    $this->submitForm([
      'fields[' . self::FIELD_NAME . '][settings_edit_form][settings][allowed_icon_pack][test_path]' => TRUE,
    ], 'Update');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');

    $settings = $this->displayRepository->getFormDisplay('node', 'article')
      ->getComponent(self::FIELD_NAME)['settings'];
    $this->assertSame(['test_path' => 'test_path'], $settings['allowed_icon_pack']);
  }

  /**
   * Tests the link formatter settings stay out of the icon settings.
   */
  public function testFormatterSettingsSave(): void {
    $this->displayRepository->getFormDisplay('node', 'article')
      ->setComponent(self::FIELD_NAME, [
        'type' => 'icon_link_widget',
        'settings' => ['allowed_icon_pack' => ['test_settings' => 'test_settings']],
      ])
      ->save();
    $this->displayRepository->getViewDisplay('node', 'article')
      ->setComponent(self::FIELD_NAME, ['type' => 'icon_link_formatter'])
      ->save();

    $prefix = 'fields[' . self::FIELD_NAME . '][settings_edit_form][settings]';
    $this->drupalGet('/admin/structure/types/manage/article/display/default');
    $this->submitForm([], self::FIELD_NAME . '_settings_edit');
    $this->submitForm([
      $prefix . '[trim_length]' => 40,
      $prefix . '[rel]' => TRUE,
      $prefix . '[target]' => TRUE,
      $prefix . '[icon_display]' => 'before',
      $prefix . '[test_settings][width]' => 64,
    ], 'Update');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');

    $settings = $this->displayRepository->getViewDisplay('node', 'article')
      ->getComponent(self::FIELD_NAME)['settings'];
    $this->assertSame(['test_settings'], array_keys($settings['icon_settings']));
    $this->assertEquals(64, $settings['icon_settings']['test_settings']['width']);
    $this->assertEquals(40, $settings['trim_length']);
    $this->assertSame('nofollow', $settings['rel']);
    $this->assertSame('_blank', $settings['target']);
    $this->assertSame('before', $settings['icon_display']);
  }

}
