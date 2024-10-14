<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\IconFinder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for icon_extractor plugins.
 *
 * This is a wrapper for the IconFinder class to load icon files based on path
 * or urls.
 */
abstract class IconExtractorWithFinder extends IconExtractorBase implements IconExtractorWithFinderInterface, PluginWithFormsInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use PluginWithFormsTrait;

  /**
   * Constructs a IconExtractorWithFinder object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ui_icons\IconFinder $iconFinder
   *   The icons finder service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected IconFinder $iconFinder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ui_icons.finder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilesFromSources(): array {
    $this->checkRequireConfigSources();

    if (!isset($this->configuration['relative_path'])) {
      throw new IconPackConfigErrorException(sprintf('Empty relative path for extractor %s.', $this->getPluginId()));
    }

    return $this->iconFinder->getFilesFromSources(
      $this->configuration['config']['sources'],
      $this->configuration['relative_path']
    );
  }

  /**
   * Check the required `config > sources` value from definition.
   *
   * @throws \Drupal\ui_icons\Exception\IconPackConfigErrorException
   *   If the config:sources value in definition is not set or not valid.
   */
  private function checkRequireConfigSources(): void {
    if (
      !isset($this->configuration['config']['sources']) ||
      empty($this->configuration['config']['sources']) ||
      !is_array($this->configuration['config']['sources'])
    ) {
      throw new IconPackConfigErrorException(sprintf('Missing or invalid `config: sources` in your definition, extractor %s require this value as array.', $this->getPluginId()));
    }
  }

}
