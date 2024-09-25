<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_icons\Form\IconExtractorSettingsForm;
use Drupal\ui_icons\IconDefinition;

/**
 * Base class for ui_icons_extractor plugins.
 */
abstract class IconExtractorBase extends PluginBase implements IconExtractorInterface, PluginWithFormsInterface {

  use StringTranslationTrait;
  use PluginWithFormsTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if (!isset($this->configuration['settings'])) {
      return $form;
    }

    return IconExtractorSettingsForm::generateSettingsForm($this->configuration['settings'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public static function createIcon(string $icon_id, array $data, ?string $source = NULL, ?string $group = NULL): IconDefinition {
    return IconDefinition::create($icon_id, $data, $source, $group);
  }

}
