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
   * @param string $pack_id
   *   The id of the icon pack.
   * @param string $icon_id
   *   The id of the icon.
   * @param string $template
   *   The icon template from definition.
   * @param string|null $source
   *   The source, url or path of the icon.
   * @param string|null $group
   *   The group of the icon.
   * @param array $data
   *   The additional data of the icon.
   *
   * @return self
   *   The icon definition.
   */
  public static function create(
    string $pack_id,
    string $icon_id,
    string $template,
    ?string $source = NULL,
    ?string $group = NULL,
    ?array $data = NULL,
  ): self;

  /**
   * Create an icon full id.
   *
   * @param string $pack_id
   *   The id of the icon pack.
   * @param string $icon_id
   *   The id of the icon.
   *
   * @return string
   *   The icon full id.
   */
  public static function createIconId(string $pack_id, string $icon_id): string;

  /**
   * Get the Icon label as human friendly.
   *
   * @return string
   *   The icon label.
   */
  public function getLabel(): string;

  /**
   * Get the full Icon id.
   *
   * @return string
   *   The icon id as pack_id:icon_id.
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
   * Get the Icon Pack id.
   *
   * @return string
   *   The Icon Pack id.
   */
  public function getPackId(): string;

  /**
   * Get the Icon source, path or url.
   *
   * @return string|null
   *   The Icon source.
   */
  public function getSource(): ?string;

  /**
   * Get the Icon Group.
   *
   * @return string|null
   *   The Icon Group.
   */
  public function getGroup(): ?string;

  /**
   * Get the Icon Twig template.
   *
   * @return string
   *   The Icon template.
   */
  public function getTemplate(): string;

  /**
   * Get the Icon pack label.
   *
   * @return string|null
   *   The Icon pack label.
   */
  public function getPackLabel(): ?string;

  /**
   * Get the Icon Twig library.
   *
   * @return string|null
   *   The Icon library.
   */
  public function getLibrary(): ?string;

  /**
   * Get the Icon data.
   *
   * @param string $key
   *   The ata key to find.
   *
   * @return mixed
   *   The icon data if exist or null.
   */
  public function getData(string $key): mixed;

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
   * Get the Icon preview renderable.
   *
   * There is 2 modes for preview, an internal preview based on icon_preview
   * theme and template, generic and compatible with core extractors.
   * For specific cases like fonts icons where the display is unknown, the
   * definition can provide a `preview` key with a specific Twig template used
   * to render the Icon in a preview context, ie: admin and library.
   *
   * @param array $settings
   *   Settings to pass to the renderable for context.
   *
   * @return array
   *   The Icon preview render element.
   */
  public function getPreview(array $settings = []): array;

}
