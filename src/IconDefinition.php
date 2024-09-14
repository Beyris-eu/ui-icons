<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\ui_icons\Exception\IconDefinitionInvalidDataException;

/**
 * Handle a UI Icon definition.
 */
class IconDefinition implements IconDefinitionInterface {

  private const DEFAULT_TEMPLATE = '<img class="icon icon-{{ icon_id|clean_class }}" src="{{ source }}" title="{{ title|default(name) }}" alt="{{ alt|default(name) }}" width="{{ width|default(24) }}" height="{{ height|default(24) }}">';

  /**
   * Constructor for IconDefinition.
   *
   * @param string $icon_id
   *   The id of the icon.
   * @param string $source
   *   The source of the icon.
   * @param array $data
   *   The additional data of the icon.
   * @param string|null $group
   *   The group of the icon (optional).
   */
  private function __construct(
    private string $icon_id,
    private string $source,
    private array $data,
    private ?string $group = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(string $icon_id, string $source, array $data, ?string $group = NULL): self {
    self::validateData($icon_id, $source, $data);
    return new self($icon_id, $source, $data, $group);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return ucfirst(str_replace(['-', '_', '.'], ' ', $this->icon_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getIconPackId() . ':' . $this->getIconId();
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
  public function getSource(): string {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): string {
    return $this->group ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): string {
    return $this->data['content'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPackId(): string {
    return $this->data['icon_pack_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPackLabel(): string {
    return $this->data['icon_pack_label'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderable(array $options = []): array {
    $context = [
      'icon_label' => $this->getLabel(),
      'icon_full_id' => $this->getId(),
      'icon_id' => $this->icon_id,
      'source' => $this->source,
      'content' => new FormattableMarkup($this->getContent(), []),
      'icon_pack_label' => $this->getIconPackLabel(),
    ];

    $template = $this->data['template'] ?? self::DEFAULT_TEMPLATE;

    $renderable = [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => $context + $options,
    ];

    if (!empty($this->data['library'])) {
      $renderable['#attached'] = ['library' => [$this->data['library']]];
    }

    return $renderable;
  }

  /**
   * Basic validation before creating the icon.
   *
   * @param string $icon_id
   *   The id of the icon.
   * @param string $source
   *   The source of the icon.
   * @param array $data
   *   The additional data of the icon.
   */
  private static function validateData(string $icon_id, string $source, array $data): void {
    $errors = NULL;

    // Empty can have "0" as false positive.
    if ('' === $icon_id) {
      $errors[] = 'Empty icon_id provided';
    }
    // @todo test source is valid? ie path or url?
    if (empty($source)) {
      $errors[] = 'Empty source provided';
    }

    if (!isset($data['icon_pack_id']) || empty($data['icon_pack_id'])) {
      $errors[] = 'Missing Icon Pack Id in data';
    }

    if ($errors) {
      throw new IconDefinitionInvalidDataException(implode('. ', $errors) . '.');
    }
  }

}
