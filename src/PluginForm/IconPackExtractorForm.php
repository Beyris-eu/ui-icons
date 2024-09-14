<?php

declare(strict_types=1);

namespace Drupal\ui_icons\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * The ui icon pack extractor form plugin.
 *
 * @internal
 */
class IconPackExtractorForm extends PluginFormBase implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\Core\Plugin\PluginFormInterface $this->plugin */
    return $this->plugin->buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Plugin\PluginFormInterface $this->plugin */
    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Plugin\PluginFormInterface $this->plugin */
    $this->plugin->submitConfigurationForm($form, $form_state);
  }

}
