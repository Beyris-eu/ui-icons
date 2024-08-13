<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Tests IconPackExtractorForm pluginForm.
 *
 * @group ui_icons
 */
class IconPackExtractorFormTest extends UnitTestCase {

  /**
   * The Icon Pack form.
   *
   * @var \Drupal\ui_icons\PluginForm\IconPackExtractorForm
   */
  private IconPackExtractorForm $iconPackForm;

  /**
   * The plugin form.
   *
   * @var \Drupal\Core\Plugin\PluginWithFormsInterface
   */
  private PluginWithFormsInterface $plugin;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private $formState;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->plugin = new TestPluginWithForm();
    $this->formState = $this->prophesize(FormStateInterface::class);

    $this->iconPackForm = new IconPackExtractorForm();
    $this->iconPackForm->setPlugin($this->plugin);
  }

  /**
   * Test the buildConfigurationForm method.
   */
  public function testBuildConfigurationForm() {
    $form = [
      'test_form' => 'test_form',
    ];
    $formState = $this->formState->reveal();

    $result = $this->iconPackForm->buildConfigurationForm($form, $formState);

    $this->assertEquals('container', $result['#type']);
    $this->assertEquals('<div id="ui-icons-settings-wrapper">', $result['#prefix']);
    $this->assertEquals('</div>', $result['#suffix']);
    $this->assertEquals('plugin_build_form', $result['plugin_build_form']);

    $this->assertArrayNotHasKey('test_form', $result);
    $this->assertArrayNotHasKey('ui-icons-settings', $result);
  }

  /**
   * Test the validateConfigurationForm method.
   */
  public function testValidateConfigurationForm() {
    $form = [];
    $formState = $this->formState->reveal();

    $this->iconPackForm->validateConfigurationForm($form, $formState);
    $this->assertArrayHasKey('plugin_validate_form', $form);
  }

  /**
   * Test the submitConfigurationForm method.
   */
  public function testSubmitConfigurationForm() {
    $form = [];
    $formState = $this->formState->reveal();

    $this->iconPackForm->submitConfigurationForm($form, $formState);
    $this->assertArrayHasKey('plugin_submit_form', $form);
  }

}

/**
 * Test class for form.
 */
class TestPluginWithForm implements PluginWithFormsInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'test';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasFormClass($operation) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormClass($operation) {
    return 'form_class';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['plugin_build_form'] = 'plugin_build_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form['plugin_validate_form'] = 'plugin_validate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form['plugin_submit_form'] = 'plugin_submit_form';
  }

}
