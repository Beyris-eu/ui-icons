<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'icon',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('Get an icon from UI Icons module.'),
  prop_types: ['icon'],
  tags: ['widget', 'widget:dismissible']
)]
class IconSource extends SourcePluginBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected ElementInfoManagerInterface $elementInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->elementInfo = $container->get('plugin.manager.element_info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $value = $this->getSetting('value');
    if (!$icon_data = IconDefinition::getIconDataFromId($value['target_id'] ?? $value['icon_id'] ?? '')) {
      return NULL;
    }

    $icon_settings = $value['settings'] ?? $value['icon_settings'] ?? [];
    $icon_data['settings'] = $icon_settings[$icon_data['pack_id']] ?? [];

    return $icon_data;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $value = $this->getSetting('value');
    $element = [
      'value' => [
        '#type' => $this->getIconSelector(),
        '#default_value' => $value['target_id'] ?? '',
        '#default_settings' => $value['settings'] ?? [],
        '#show_settings' => TRUE,
        '#return_id' => TRUE,
        // Source forms are often narrow, a builder sidebar for instance.
        '#result_format' => 'grid',
      ],
    ];

    if (isset($this->propDefinition['properties']['pack_id']['enum'])) {
      $icon_packs = $this->propDefinition['properties']['pack_id']['enum'];
      $element['value']['#allowed_icon_pack'] = $icon_packs;
      $element['value']['#description'] = $this->t('Allowed icon packs: @values', ['@values' => implode(',', $icon_packs)]);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $value = $this->getSetting('value');
    $icon_full_id = $value['target_id'] ?? $value['icon_id'] ?? NULL;
    if (!is_string($icon_full_id) || !$icon_data = IconDefinition::getIconDataFromId($icon_full_id)) {
      return [];
    }

    return [
      $icon_data['icon_id'] . ' (' . $icon_data['pack_id'] . ')',
    ];
  }

  /**
   * Get the icon selector element type.
   *
   * 'icon_popover' and 'icon_picker' come from optional submodules. An unknown
   * '#type' renders as nothing rather than failing, which would leave the
   * source with no icon input at all, so fall back when it is gone.
   *
   * @return string
   *   The form element type.
   */
  private function getIconSelector(): string {
    $icon_selector = (string) $this->configFactory->get('ui_icons_patterns.settings')->get('icon_selector');
    if (!$this->elementInfo->hasDefinition($icon_selector)) {
      return 'icon_autocomplete';
    }

    return $icon_selector;
  }

}
