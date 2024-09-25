<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

/**
 * Interface for UI Icons icon definition.
 */
interface IconDefinitionInterface {

  /**
   * Create an icon definition.
   *
   * @param string $icon_id
   *   The id of the icon.
   * @param array $data
   *   The additional data of the icon.
   * @param string|null $source
   *   The source of the icon (optional).
   * @param string|null $group
   *   The group of the icon (optional).
   *
   * @return self
   *   The icon definition.
   */
  public static function create(string $icon_id, array $data, ?string $source = NULL, ?string $group = NULL): self;

  /**
   * Get the Icon name.
   *
   * @return string
   *   The icon name.
   */
  public function getLabel(): string;

  /**
   * Get the full Icon id.
   *
   * @return string
   *   The icon id as icon_pack_id:icon_id.
   */
  public function getId(): string;

  /**
   * Get the Icon id.
   *
   * @return string
   *   The icon id as icon_id.
   */
  public function getIconId(): string;

  /**
   * Get the Icon source.
   *
   * @return string|null
   *   The icon source.
   */
  public function getSource(): ?string;

  /**
   * Get the Icon group.
   *
   * @return string|null
   *   The icon group.
   */
  public function getGroup(): ?string;

  /**
   * Get the Icon data.
   *
   * @return array
   *   The icon data, include definition and any extractor added data.
   */
  public function getData(): array;

  /**
   * Get the Icon content.
   *
   * @return string|null
   *   The icon content if set.
   */
  public function getContent(): ?string;

  /**
   * Get the Icon template.
   *
   * @return string
   *   The icon template.
   */
  public function getTemplate(): string;

  /**
   * Get the Icon library.
   *
   * @return string|null
   *   The icon library.
   */
  public function getLibrary(): ?string;

  /**
   * Get the Icon Pack id.
   *
   * @return string
   *   The Icon Pack id.
   */
  public function getIconPackId(): string;

  /**
   * Get the Icon Pack label.
   *
   * @return string
   *   The Icon Pack label.
   */
  public function getIconPackLabel(): string;

  /**
   * Get the Icon renderable array.
   *
   * @param array $settings
   *   Settings to pass to the renderable for context.
   *
   * @return array
   *   The Icon renderable.
   */
  public function getRenderable(array $settings = []): array;

  /**
   * Get the Icon preview array.
   *
   * @param array $settings
   *   Settings to pass to the renderable for context.
   *
   * @return array
   *   The Icon preview.
   */
  public function getPreview(array $settings = []): array;

}
