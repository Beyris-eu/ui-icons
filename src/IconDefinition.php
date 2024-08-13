<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\ui_icons\Exception\IconDefinitionInvalidDataException;

/**
 * Handle a UI Icon definition.
 */
class IconDefinition implements IconDefinitionInterface {

  protected const DEFAULT_TEMPLATE = '<img src="{{ source }}" title="{{ title|default(icon_id|capitalize) }}" alt="{{ alt|default(icon_id|capitalize) }}" width="{{ width|default(24) }}" height="{{ height|default(24) }}">';

  /**
   * Constructor for IconDefinition.
   *
   * @param string $name
   *   The name of the icon.
   * @param string $source
   *   The source of the icon.
   * @param array $data
   *   The additional data of the icon.
   * @param string|null $group
   *   The group of the icon (optional).
   */
  private function __construct(
    private string $name,
    private string $source,
    private array $data,
    private ?string $group = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(string $name, string $source, array $data, ?string $group = NULL): self {
    self::validateData($name, $source, $data);
    return new self($name, $source, $data, $group);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getIconPackId() . ':' . $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name;
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
      'icon_id' => $this->name,
      'source' => $this->source,
      'content' => new FormattableMarkup($this->getContent(), []),
      'icon_pack_label' => $this->getIconPackLabel(),
    ];

    if (!isset($this->data['template']) || empty($this->data['template'])) {
      $template = self::DEFAULT_TEMPLATE;
    }
    else {
      $template = $this->data['template'];
    }

    $template = [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => array_merge($context, $options),
    ];

    if (isset($this->data['library']) && !empty($this->data['library'])) {
      $template['#attached'] = ['library' => [$this->data['library']]];
    }

    return $template;
  }

  /**
   * Basic validation before creating the icon.
   *
   * @param string $name
   *   The name of the icon.
   * @param string $source
   *   The source of the icon.
   * @param array $data
   *   The additional data of the icon.
   */
  private static function validateData(string $name, string $source, array $data): void {
    $errors = NULL;

    // Empty can have "0" as false positive.
    if ('' === $name) {
      $errors[] = 'Empty name provided';
    }
    // @todo test source is valid? ie path or url?
    if (empty($source)) {
      $errors[] = 'Empty source provided';
    }

    if (!isset($data['icon_pack_id'])) {
      $errors[] = 'Missing Icon Pack Id in data';
    }

    if ($errors) {
      throw new IconDefinitionInvalidDataException(implode('. ', $errors));
    }
  }

}
