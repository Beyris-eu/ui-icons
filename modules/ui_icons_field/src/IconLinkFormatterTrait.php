<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Theme\Icon\IconDefinition;

/**
 * Provides a trait for icon link formatter.
 */
trait IconLinkFormatterTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'icon_settings' => [],
      'icon_display' => 'icon_only',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();
    $widget_settings = $this->getWidgetSettings();

    if ($settings['icon_settings']) {
      $summary[] = $this->t('Specific settings saved');
    }

    if (!(isset($widget_settings['icon_position']) && TRUE === $widget_settings['icon_position'])
      && !empty($settings['icon_display'])
    ) {
      $summary[] = $this->t('Icon display: %position', ['%position' => $this->getDisplayPositions()[$settings['icon_display']]]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $widget_settings = $this->getWidgetSettings();

    if (isset($widget_settings['icon_position']) && FALSE === $widget_settings['icon_position']) {
      $elements['icon_display'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon display'),
        '#options' => $this->getDisplayPositions(),
        '#default_value' => $this->getSetting('icon_display') ?? 'icon_only',
      ];
    }

    $this->pluginManagerIconPack->getExtractorPluginForms(
      $elements,
      $form_state,
      $this->getSetting('icon_settings') ?: [],
      $widget_settings['allowed_icon_pack'] ? array_filter($widget_settings['allowed_icon_pack']) : [],
      TRUE
    );

    // Placeholder to get all settings serialized as the form keys are dynamic
    // and based on icon pack definition options.
    // @todo change to #element_submit when available.
    // @see https://drupal.org/i/2820359
    $elements['icon_settings'] = [
      '#type' => 'hidden',
      '#element_validate' => [
        [$this, 'validateSettings'],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    $formatter_icon_display = $this->getSetting('icon_display');

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $icon_full_id = $item->options['icon']['target_id'] ?? NULL;
      if (NULL === $icon_full_id || !is_string($icon_full_id)) {
        continue;
      }

      $formatter_settings = $this->getSetting('icon_settings') ?? [];
      if (!is_array($formatter_settings)) {
        $formatter_settings = [];
      }

      $icon_display = $item->options['icon_display'] ?? $formatter_icon_display ?? NULL;
      $icon_element = IconDefinition::getRenderable($icon_full_id, $formatter_settings);

      switch ($icon_display) {
        case 'before':
          $elements[$delta]['#title'] = new FormattableMarkup('@icon @title', [
            '@title' => $elements[$delta]['#title'],
            '@icon' => $this->renderIcon($icon_element, $elements[$delta]),
          ]);
          break;

        case 'after':
          $elements[$delta]['#title'] = new FormattableMarkup('@title @icon', [
            '@title' => $elements[$delta]['#title'],
            '@icon' => $this->renderIcon($icon_element, $elements[$delta]),
          ]);
          break;

        default:
          $elements[$delta]['#title'] = $icon_element;
          break;
      }

      // Mark processed to avoid double pass with
      // \Drupal\ui_icons_menu\Hook\UiIconsMenuHooks::linkAlter().
      if (isset($elements[$delta]['#url'])) {
        $elements[$delta]['#url']->setOption('ui_icons_processed', TRUE);
      }
      if (isset($elements[$delta]['link']['#url'])) {
        $elements[$delta]['link']['#url']->setOption('ui_icons_processed', TRUE);
      }
    }

    return $elements;
  }

  /**
   * Render an icon to put in a link title.
   *
   * The icon pack library and cache metadata do not survive the cast to a
   * string, so they are carried over to the link element.
   *
   * @param array|null $icon_element
   *   The icon renderable.
   * @param array $element
   *   The link element.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered icon.
   */
  private function renderIcon(?array $icon_element, array &$element): MarkupInterface|string {
    $icon = $this->renderer->renderInIsolation($icon_element);
    if (NULL !== $icon_element) {
      BubbleableMetadata::createFromRenderArray($element)
        ->merge(BubbleableMetadata::createFromRenderArray($icon_element))
        ->applyTo($element);
    }

    return $icon;
  }

  /**
   * Get the widget settings.
   *
   * @return array
   *   The widget settings.
   */
  protected function getWidgetSettings(): array {
    // Access FieldWidget settings to match this formatter settings.
    // Except in some context like views where we don't have the bundle.
    if (!$bundle = $this->fieldDefinition->getTargetBundle()) {
      $widget_settings = [
        'icon_position' => FALSE,
        'allowed_icon_pack' => [],
      ];
    }
    else {
      $field_name = $this->fieldDefinition->getName();
      $form_display = $this->entityDisplayRepository->getFormDisplay(
        $this->fieldDefinition->getTargetEntityTypeId(),
        $bundle,
        // @todo is it possible to support form display?
        'default'
      );
      $component = $form_display->getComponent($field_name);
      if (isset($component['settings'])) {
        $widget_settings = $component['settings'];
      }
      else {
        $widget_settings = [
          'icon_position' => FALSE,
          'allowed_icon_pack' => [],
        ];
      }
    }
    return $widget_settings;
  }

}
