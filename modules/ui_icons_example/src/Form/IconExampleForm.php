<?php

declare(strict_types=1);

namespace Drupal\ui_icons_example\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ui_icons\Plugin\IconExtractorPluginManager;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a UI Icons example form.
 *
 * phpcs:disable
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 *
 * @codeCoverageIgnore
 */
final class IconExampleForm extends FormBase {

  public function __construct(
    protected IconPackManagerInterface $pluginManagerIconPack,
    protected IconExtractorPluginManager $iconPackExtractorManager,
  ) {
    $this->pluginManagerIconPack = $pluginManagerIconPack;
    $this->iconPackExtractorManager = $iconPackExtractorManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.ui_icons_pack'),
      $container->get('plugin.manager.ui_icons_extractor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_example_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $iconPack = $this->pluginManagerIconPack->getDefinitions();

    $form = [];
    $form['#tree'] = TRUE;

    $form['icon_pack'] = [
      '#type' => 'details',
      '#title' => $this->t('Icon Pack field examples'),
      '#open' => TRUE,
      '#weight' => 2,
    ];

    $form['icon_pack']['icon_pack_checkboxes'] = [
      '#type' => 'details',
      '#title' => $this->t('Icon Pack multiple checkbox with description and link'),
      '#open' => TRUE,
    ];

    $options = $this->pluginManagerIconPack->listIconPackWithDescriptionOptions();
    $options_title = [];
    foreach ($options as $key => $title) {
      $part = explode(' - ', $title);
      $options_title[$key] = $part[0] ?? 'n/a';
      $form['icon_pack']['icon_pack_checkboxes'][$key] = [
        '#type' => 'checkbox',
        '#title' => $part[0] ?? 'n/a',
        '#description' => $part[1] ?? '',
      ];
    }

    $form['icon_pack']['icon_pack_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon Pack selector with description'),
      '#options' => $options,
    ];

    $form['icon_pack']['icon_pack_select_title'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon Pack selector title only'),
      '#options' => $options_title,
    ];

    $form['icon_pack']['icon_pack_select_multiple'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon Pack selector multiple'),
      '#options' => $options_title,
      '#multiple' => TRUE,
    ];

    $form['icons'] = [
      '#type' => 'details',
      '#title' => $this->t('Icons selector examples'),
      '#open' => TRUE,
      '#weight' => 1,
    ];

    $form['icons']['icon_autocomplete'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Icon selector'),
      '#placeholder' => $this->t('Test me'),
    ];

    $form['icons']['icon_autocomplete_settings'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Icon selector with settings'),
      '#show_settings' => TRUE,
    ];
    
    $allowed = array_slice(array_keys($iconPack), 0, 1);
    $names = $this->pluginManagerIconPack->listIconPackOptions();
    $form['icons']['icon_autocomplete_limit'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Icon selector limited'),
      '#description' => $this->t('Limited to: @name.', ['@name' => $names[$allowed[0]]]),
      '#allowed_icon_pack' => $allowed,
    ];

    // No full select as we could have thousands of icons.
    $options = ['' => $this->t('- Select -')];
    $options += $this->pluginManagerIconPack->listOptions($allowed);
    $form['icons']['icons_select_limited'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon select limited'),
      '#description' => $this->t('Limited to 30 first: @name.', ['@name' => $names[$allowed[0]]]),
      '#options' => array_slice($options, 0, 30),
      '#sort_options' => TRUE,
    ];

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Extractor settings'),
      '#open' => TRUE,
      '#weight' => 1,
    ];

    // We store the icon pack definition to have access in validate and submit
    // and avoid reloading it.
    $form['icon_pack_definition'] = [
      '#type' => 'hidden',
      '#value' => serialize($iconPack),
    ];

    // Add our extractor forms.
    $this->pluginManagerIconPack->getExtractorPluginForms($form['settings'], $form_state);
    foreach (Element::children($form['settings']) as $icon_pack_id) {
      $form['settings'][$icon_pack_id]['#type'] = 'details';
      $form['settings'][$icon_pack_id]['#title'] = $icon_pack_id;
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Process the form, only display values for example.
    $values = $form_state->getValues();

    foreach ($values['icons'] as $key_form => $icon) {
      if (isset($icon['icon'])) {
        $this->messenger()->addStatus($this->t('Saved icon for @key: @label', ['@key' => $key_form, '@label' => $icon['icon']->getLabel()]));
      }
    }
    foreach ($values['icon_pack'] as $key_form => $iconPack) {
      $this->messenger()->addStatus($this->t('Saved icon pack for @pack', ['@pack' => (is_array($iconPack) ? implode(', ', $iconPack) : $iconPack)]));
    }

    foreach ($values['settings'] as $settings) {
      if (!is_array($settings)) {
        continue;
      }
      foreach ($settings as $plugin_id => $plugin_values) {
        if (!is_array($plugin_values)) {
          continue;
        }
        $this->messenger()->addStatus($this->t('Plugin @name submitted:', ['@name' => $plugin_id]));
        foreach ($plugin_values as $key => $value) {
          if (FALSE !== strpos($key, 'form_') || 'op' === $key || 'submit' === $key) {
            continue;
          }
          $this->messenger()->addStatus($this->t('Save value @key: @value', ['@key' => $key, '@value' => $value]));
        }
      }
    }
  }

}
