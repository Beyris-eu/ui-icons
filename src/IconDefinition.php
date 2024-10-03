<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\ui_icons\Exception\IconDefinitionInvalidDataException;

/**
 * Handle an Icon definition.
 */
class IconDefinition implements IconDefinitionInterface {

  public const ICON_SEPARATOR = ':';

  /**
   * Constructor for IconDefinition.
   *
   * @param string $pack_id
   *   The id of the icon pack.
   * @param string $icon_id
   *   The id of the icon.
   * @param string $template
   *   The template of the icon.
   * @param string|null $source
   *   The source, url or path of the icon.
   * @param string|null $group
   *   The group of the icon.
   * @param array $data
   *   The additional data of the icon.
   */
  private function __construct(
    private string $pack_id,
    private string $icon_id,
    private string $template,
    private ?string $source,
    private ?string $group,
    private ?array $data,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(
    string $pack_id,
    string $icon_id,
    string $template,
    ?string $source = NULL,
    ?string $group = NULL,
    ?array $data = NULL,
  ): self {
    $errors = [];
    if (empty($pack_id)) {
      $errors[] = 'Empty pack_id provided!';
    }
    if (empty($icon_id)) {
      $errors[] = 'Empty icon_id provided!';
    }
    if (empty($template)) {
      $errors[] = 'Empty template provided!';
    }

    if (count($errors)) {
      throw new IconDefinitionInvalidDataException(implode(' ', $errors));
    }

    // @todo cleanup of data, check we don't need to pass these anywhere.
    unset($data['config']['sources'], $data['relative_path'], $data['absolute_path']);

    return new self($pack_id, $icon_id, $template, $source, $group, $data);
  }

  /**
   * {@inheritdoc}
   */
  public static function createIconId(string $pack_id, string $icon_id): string {
    return sprintf('%s%s%s', $pack_id, self::ICON_SEPARATOR, $icon_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return self::humanize($this->icon_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return sprintf('%s%s%s', $this->pack_id, self::ICON_SEPARATOR, $this->icon_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getIconId(): string {
    return $this->icon_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackId(): string {
    return $this->pack_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource(): ?string {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): ?string {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate(): string {
    return $this->template;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(string $key): ?string {
    return $this->data[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderable(array $settings = []): array {
    return [
      '#type' => 'ui_icon',
      '#icon_pack' => $this->pack_id,
      '#icon' => $this->icon_id,
      '#settings' => $settings,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPreview(array $settings = []): array {
    $label = $this->getLabel();
    if (isset($this->data['pack_label'])) {
      $label = ' - ' . $this->data['pack_label'];
    }

    if ($preview = $this->data['preview'] ?? NULL) {
      $renderable = [
        '#type' => 'inline_template',
        '#template' => $preview,
        '#context' => [
          'label' => $label,
          'icon_id' => $this->icon_id,
          'pack_id' => $this->pack_id,
          'extractor' => $this->data['extractor'] ?? NULL,
          'source' => $this->source ?? NULL,
          'content' => $this->data['content'] ?? NULL,
          'size' => $settings['size'] ?? 48,
        ],
      ];

      if (isset($this->data['library']) && !empty($this->data['library'])) {
        $renderable['#attached'] = ['library' => [$this->data['library']]];
      }

      return $renderable;
    }

    // Fallback to template based preview.
    $renderable = [
      '#theme' => 'icon_preview',
      '#icon_label' => $label,
      '#icon_id' => $this->icon_id,
      '#pack_id' => $this->pack_id,
      '#extractor' => $this->data['extractor'] ?? NULL,
      '#source' => $this->source ?? NULL,
      '#library' => $this->data['library'] ?? NULL,
      '#settings' => $settings,
    ];

    return $renderable;
  }

  /**
   * Humanize a text for admin display.
   *
   * Inspired by https://github.com/coduo/php-humanizer/blob/5.x/src/Coduo/PHPHumanizer/String/Humanize.php.
   *
   * @param string $text
   *   The text to humanize.
   *
   * @return string
   *   The human friendly text.
   */
  public static function humanize(string $text): string {
    $humanized = mb_strtolower((string) preg_replace(['/([A-Z])/', sprintf('/[%s\s]+/', '_')], ['_$1', ' '], $text));

    return ucfirst(trim($humanized));
  }

}
