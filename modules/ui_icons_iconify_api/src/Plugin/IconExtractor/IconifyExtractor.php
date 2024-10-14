<?php

declare(strict_types=1);

namespace Drupal\ui_icons_iconify_api\Plugin\IconExtractor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\Plugin\IconExtractorBase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;
use Drupal\ui_icons_iconify_api\IconifyApi;
use Drupal\ui_icons_iconify_api\IconifyApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the icon_extractor.
 */
#[IconExtractor(
  id: 'iconify',
  label: new TranslatableMarkup('Iconify'),
  description: new TranslatableMarkup('Provide Iconify list of Icons.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class IconifyExtractor extends IconExtractorBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a IconifyExtractor object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ui_icons_iconify_api\IconifyApiInterface $iconifyApi
   *   The Iconify API service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected IconifyApiInterface $iconifyApi,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ui_icons_iconify.iconify_api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    $config = $this->configuration['config'] ?? [];

    if (!isset($config['collections'])) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: collections` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    unset($this->configuration['config']);

    $icons = [];
    foreach ($config['collections'] as $collection) {
      $icons_collection = $this->iconifyApi->getIconsByCollection($collection);
      if (empty($icons_collection)) {
        continue;
      }

      foreach ($icons_collection as $icon_id) {
        if (!is_string($icon_id)) {
          continue;
        }

        $source = sprintf('%s/%s/%s.svg', IconifyApi::API_ENDPOINT, $collection, $icon_id);
        $icons[] = $this->createIcon($icon_id, $source);
      }
    }

    return $icons;
  }

}
