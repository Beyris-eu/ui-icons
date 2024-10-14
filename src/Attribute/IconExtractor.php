<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The icon_extractor attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class IconExtractor extends AttributeBase {

  /**
   * Constructs a new IconExtractor instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param string[] $forms
   *   (optional) An array of form class names keyed by a string.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
    public readonly array $forms = [],
  ) {}

}
