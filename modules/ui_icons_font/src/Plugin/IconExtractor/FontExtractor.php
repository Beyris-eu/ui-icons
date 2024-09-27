<?php

declare(strict_types=1);

namespace Drupal\ui_icons_font\Plugin\IconExtractor;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Exception\IconPackConfigErrorException;
use Drupal\ui_icons\Plugin\IconExtractorBase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;
use FontLib\Font;

/**
 * Plugin implementation of the ui_icons_extractor.
 */
#[IconExtractor(
  id: 'font',
  label: new TranslatableMarkup('Web Font'),
  description: new TranslatableMarkup('Provide Icons from web fonts.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class FontExtractor extends IconExtractorBase {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    if (!isset($this->configuration['config']['sources'])) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: sources` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    $icons = [];
    foreach ($this->configuration['config']['sources'] as $filename) {
      $filepath = sprintf('%s/%s', $this->configuration['definition_absolute_path'], $filename);
      $fileinfo = pathinfo($filepath);

      if (!isset($fileinfo['extension'])) {
        continue;
      }

      switch ($fileinfo['extension']) {
        case 'codepoints':
          $icons = array_merge($icons, $this->getCodePoints($filepath, $this->configuration['icon_pack_id']));
          break;

        case 'ttf':
        case 'woff':
          $icons = array_merge($icons, $this->getFontIcons($filepath, $this->configuration['icon_pack_id']));
          break;

        case 'json':
          $icons = array_merge($icons, $this->getJsonIcons($filepath, $this->configuration['icon_pack_id']));
          break;

        case 'yml':
        case 'yaml':
          $icons = array_merge($icons, $this->getYamlIcons($filepath, $this->configuration['icon_pack_id']));
          break;

        default:
          break;
      }
    }

    if (isset($this->configuration['config']['offset'])) {
      $icons = array_slice($icons, (int) $this->configuration['config']['offset']);
    }

    return $icons;
  }

  /**
   * Extract Icon names from TTF or Woff file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   * @param string $icon_pack_id
   *   The Icon pack ID.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getFontIcons(string $filepath, string $icon_pack_id): array {
    $icons = [];

    if (!class_exists('\FontLib\Font')) {
      throw new IconPackConfigErrorException(sprintf('Missing PHP library for Font extractor, run `composer require dompdf/php-font-lib` to install.'));
    }

    $font = Font::load($filepath);

    if (NULL === $font) {
      return [];
    }

    $font->parse();

    $icons_names = $font->getData('post')['names'] ?? NULL;

    if (NULL === $icons_names) {
      return [];
    }

    $icons = [];
    foreach ($icons_names as $name) {
      $icon_full_id = $icon_pack_id . ':' . $name;
      $icons[$icon_full_id] = $this->createIcon($name, $this->configuration);
    }

    return $icons;
  }

  /**
   * Extract Icon names from Json file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   * @param string $icon_pack_id
   *   The Icon pack ID.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getJsonIcons(string $filepath, string $icon_pack_id): array {
    $data = file_get_contents($filepath);
    if (FALSE === $data) {
      return [];
    }

    if (!json_validate($data)) {
      throw new IconPackConfigErrorException(sprintf('The %s contains invalid json: %s', $filepath, json_last_error_msg()));
    }

    $icons = [];
    foreach (array_keys(json_decode($data, TRUE)) as $icon_id) {
      $icons[$icon_pack_id . ':' . (string) $icon_id] = $this->createIcon((string) $icon_id, $this->configuration);
    }

    return $icons;
  }

  /**
   * Extract Icon names from Yaml file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   * @param string $icon_pack_id
   *   The Icon pack ID.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getYamlIcons(string $filepath, string $icon_pack_id): array {
    $data = file_get_contents($filepath);
    if (FALSE === $data) {
      return [];
    }

    try {
      $data = Yaml::decode($data);
    }
    catch (InvalidDataTypeException $e) {
      throw new IconPackConfigErrorException(sprintf('The %s contains invalid YAML: %s', $filepath, $e->getMessage()));
    }

    $icons = [];
    foreach (array_keys($data) as $icon_id) {
      $icons[$icon_pack_id . ':' . (string) $icon_id] = $this->createIcon((string) $icon_id, $this->configuration);
    }

    return $icons;
  }

  /**
   * Extract Icon codepoints from codepoints file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   * @param string $icon_pack_id
   *   The Icon pack ID.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getCodePoints(string $filepath, string $icon_pack_id): array {
    $icons = [];

    $handle = fopen($filepath, 'r');
    if ($handle) {
      while (($line = fgets($handle)) !== FALSE) {
        $values = explode(' ', $line);
        $this->configuration['content'] = $values[1];
        $icons[$icon_pack_id . ':' . $values[0]] = $this->createIcon($values[0], $this->configuration);
      }
      fclose($handle);
    }

    return $icons;
  }

}
