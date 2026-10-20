<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\SampleValueGenerator;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\field_sample_value\Attribute\SampleValueGenerator;
use Drupal\field_sample_value\SampleValueGeneratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Core's random link, with an icon from the packs the link widget allows.
 *
 * Core link sample values never set the icon the link formatter reads, so a
 * sample entity shows its link without icon. Only discovered when
 * field_sample_value is enabled.
 */
#[SampleValueGenerator(
  id: 'icon_link',
  label: new TranslatableMarkup('Random link with icon'),
  field_types: ['link'],
)]
class IconLink extends SampleValueGeneratorBase implements ContainerFactoryPluginInterface {

  /**
   * The icon pack manager.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected IconPackManagerInterface $pluginManagerIconPack;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->pluginManagerIconPack = $container->get('plugin.manager.icon_pack');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function generateSampleValue(FieldItemListInterface $item_list): void {
    $item_list->generateSampleItems();

    $icons = $this->pluginManagerIconPack->getIcons($this->getAllowedIconPack($item_list->getFieldDefinition()));
    if (empty($icons)) {
      return;
    }

    foreach ($item_list as $item) {
      $options = $item->get('options')->getValue() ?? [];
      $options['icon'] = ['target_id' => array_rand($icons)];
      $item->set('options', $options);
    }
  }

  /**
   * Get the icon packs allowed by the link widget of the default form display.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The link field definition.
   *
   * @return array
   *   The allowed icon pack ids, empty to allow all.
   */
  private function getAllowedIconPack(FieldDefinitionInterface $field_definition): array {
    if (!$bundle = $field_definition->getTargetBundle()) {
      return [];
    }

    $component = $this->entityDisplayRepository
      ->getFormDisplay($field_definition->getTargetEntityTypeId(), $bundle)
      ->getComponent($field_definition->getName());

    return array_filter($component['settings']['allowed_icon_pack'] ?? []);
  }

}
