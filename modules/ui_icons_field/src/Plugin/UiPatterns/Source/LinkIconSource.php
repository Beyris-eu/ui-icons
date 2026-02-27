<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\UiPatterns\Source;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginPropValue;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'link_icon',
  label: new TranslatableMarkup('[Field item] Icon from the link field component.'),
  description: new TranslatableMarkup('Provides the link icon data as source.'),
  prop_types: ['icon'],
)]
class LinkIconSource extends SourcePluginPropValue {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'icon' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): array {
    if (!$this->moduleHandler->moduleExists('ui_icons_field')) {
      return [];
    }
    elseif (!$this->moduleHandler->moduleExists('ui_icons_field_link_attributes')) {
      return [];
    }

    $field_items = $this->getContextValue("ui_patterns:field:items");
    $field_index = $this->getContextValue("ui_patterns:field:index");

    if (!$field_items instanceof FieldItemListInterface) {
      return [];
    }

    $icon_value = $field_items->getValue();
    if (isset($icon_value[$field_index]['options']['icon']['target_id'])) {
      [$icon_pack, $icon] = explode(':', $icon_value[$field_index]['options']['icon']['target_id']);
    }

    if (isset($icon_pack) && isset($icon)) {
      return [
        'pack_id' => $icon_pack,
        'icon_id' => $icon,
        'settings' => $icon_value[$field_index]['options']['icon']['settings'] ?? []
      ];
    }


    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    if (!$this->moduleHandler->moduleExists('ui_icons_field')) {
      $error_message = $this->t('You need to enable the UI Icons Fields module to use this source.');
    }
    elseif (!$this->moduleHandler->moduleExists('ui_icons_field_link_attributes')) {
      $error_message = $this->t('You need to enable the UI Icons Link Attributes module to use this source.');
    }

    if (isset($error_message)) {
      $form['warning'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'error' => [
            $error_message,
          ],
        ],
      ];
      return $form;
    }

    return $form;
  }

}
