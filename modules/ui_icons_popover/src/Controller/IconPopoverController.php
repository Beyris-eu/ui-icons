<?php

declare(strict_types=1);

namespace Drupal\ui_icons_popover\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\IconPreview;
use Drupal\ui_icons\IconSearch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the icon data the popover selector browses and searches.
 *
 * Both responses are cached per query until the icon packs change, the
 * popover then filters and searches in the browser.
 */
class IconPopoverController extends ControllerBase {

  // Matches the 24px icon in a popover tile, see css/icon.popover.css.
  public const PREVIEW_SIZE = 24;
  // Each preview is a render, so cap what one request can ask for.
  public const PREVIEWS_MAX = IconSearch::SEARCH_RESULT_MAX;
  // A group derived from icon names needs one icon in this many of its pack,
  // and never fewer than DERIVED_GROUP_MIN: Bootstrap Icons gets groups of 5.
  private const DERIVED_GROUP_SHARE = 400;
  private const DERIVED_GROUP_MIN = 3;

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly IconSearch $iconSearch,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('ui_icons.search'),
      $container->get('renderer'),
    );
  }

  /**
   * Lists the icons of the allowed packs with their label and group.
   *
   * Groups come from the icon discovery, `{group}` in a pack source path, or
   * from the icon names for a pack declaring none, see ::deriveGroups(). The
   * same name in two packs is one group, so the editor browses groups and
   * never needs to know which pack an icon comes from.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with `allowed_icon_pack` joined with `+`, or none for all.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The pack labels, the groups sorted by label, and each icon as a list of
   *   its full id, label and group key, sorted by label.
   */
  public function index(Request $request): CacheableJsonResponse {
    $allowed_icon_pack = $this->getAllowedIconPack($request);

    $discovered = $this->pluginManagerIconPack->getIcons($allowed_icon_pack);
    $derived = $this->deriveGroups($discovered);

    $packs = $groups = $icons = [];
    foreach ($discovered as $icon_full_id => $icon_data) {
      ['pack_id' => $pack_id, 'icon_id' => $icon_id] = IconDefinition::getIconDataFromId($icon_full_id);
      $packs[$pack_id] = $pack_id;

      $group = trim((string) ($icon_data['group'] ?? $derived[$icon_full_id] ?? ''));
      $group_key = $group === '' ? NULL : mb_strtolower($group);
      if (NULL !== $group_key) {
        $groups[$group_key] ??= ['key' => $group_key, 'label' => IconDefinition::humanize($group)];
      }

      $icons[] = [$icon_full_id, IconDefinition::humanize($icon_id), $group_key];
    }

    foreach ($this->pluginManagerIconPack->getDefinitions() ?? [] as $pack_id => $definition) {
      if (isset($packs[$pack_id])) {
        $packs[$pack_id] = $definition['label'] ?? $pack_id;
      }
    }

    usort($groups, static fn (array $one, array $other): int => strnatcasecmp($one['label'], $other['label']));
    usort($icons, static fn (array $one, array $other): int => strnatcasecmp($one[1], $other[1]) ?: strcmp($one[0], $other[0]));

    $response = new CacheableJsonResponse([
      'packs' => (object) $packs,
      'groups' => $groups,
      'icons' => $icons,
    ]);

    return $response->addCacheableDependency($this->getCacheability(['url.query_args:allowed_icon_pack']));
  }

  /**
   * Groups the icons of the packs declaring no group by their first word.
   *
   * Trailing digits go, so `calendar2` joins `calendar`, and a plural joins
   * its singular when the pack has both. A group too small for the size of
   * its pack is dropped, its icons go to Other.
   *
   * @param array $icons
   *   The discovered icons keyed by full id, as getIcons() returns them.
   *
   * @return array
   *   The derived group keyed by icon full id, for the icons getting one.
   */
  private function deriveGroups(array $icons): array {
    $words = $sizes = $grouped = [];
    foreach ($icons as $icon_full_id => $icon_data) {
      ['pack_id' => $pack_id, 'icon_id' => $icon_id] = IconDefinition::getIconDataFromId($icon_full_id);
      $sizes[$pack_id] = ($sizes[$pack_id] ?? 0) + 1;
      if (trim((string) ($icon_data['group'] ?? '')) !== '') {
        $grouped[$pack_id] = TRUE;
      }
      $word = preg_replace('/\d+$/', '', preg_split('/[-_.\s]/', mb_strtolower($icon_id))[0]);
      if ($word !== '') {
        $words[$pack_id][$icon_full_id] = $word;
      }
    }

    $derived = [];
    foreach (array_diff_key($words, $grouped) as $pack_id => $pack_words) {
      $known = array_flip($pack_words);
      foreach ($pack_words as $icon_full_id => $word) {
        $singular = substr($word, 0, -1);
        if (str_ends_with($word, 's') && isset($known[$singular])) {
          $pack_words[$icon_full_id] = $singular;
        }
      }

      $minimum = max(self::DERIVED_GROUP_MIN, (int) round($sizes[$pack_id] / self::DERIVED_GROUP_SHARE));
      $counts = array_count_values($pack_words);
      foreach ($pack_words as $icon_full_id => $word) {
        if ($counts[$word] >= $minimum) {
          $derived[$icon_full_id] = $word;
        }
      }
    }

    return $derived;
  }

  /**
   * Renders the previews of the icons the popover shows.
   *
   * Icons load straight from their extractor, see IconSearch::loadIcon(), so
   * scrolling through a large pack does not grow the icon collector cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with the icon full ids as a repeated `ids[]` argument, and
   *   `allowed_icon_pack` joined with `+`, or none for all.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The preview markup keyed by icon full id. Icons outside the allowed
   *   packs or unknown are left out.
   */
  public function previews(Request $request): CacheableJsonResponse {
    $allowed_icon_pack = $this->getAllowedIconPack($request);
    $ids = array_slice($request->query->all('ids'), 0, self::PREVIEWS_MAX);

    $previews = [];
    foreach (array_filter($ids, 'is_string') as $icon_full_id) {
      $icon_data = IconDefinition::getIconDataFromId($icon_full_id);
      if (!$icon_data || ($allowed_icon_pack && !in_array($icon_data['pack_id'], $allowed_icon_pack, TRUE))) {
        continue;
      }

      if (!$icon = $this->iconSearch->loadIcon($icon_full_id)) {
        continue;
      }

      $preview = IconPreview::getPreview($icon, ['size' => self::PREVIEW_SIZE]);
      $previews[$icon_full_id] = (string) $this->renderer->renderInIsolation($preview);
    }

    $response = new CacheableJsonResponse((object) $previews);

    return $response->addCacheableDependency($this->getCacheability([
      'url.query_args:ids',
      'url.query_args:allowed_icon_pack',
    ]));
  }

  /**
   * Reads the icon packs the element limits the selection to.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The pack ids, empty for all packs.
   */
  private function getAllowedIconPack(Request $request): array {
    $allowed_icon_pack = (string) $request->query->get('allowed_icon_pack', '');
    return $allowed_icon_pack === '' ? [] : explode('+', $allowed_icon_pack);
  }

  /**
   * Builds the cacheability shared by both responses.
   *
   * @param array $contexts
   *   The query arguments the response varies on.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Cached until the icon packs or their icons change.
   */
  private function getCacheability(array $contexts): CacheableMetadata {
    return (new CacheableMetadata())
      ->setCacheTags(['icon_pack_plugin', 'icon_pack_collector'])
      ->setCacheContexts($contexts);
  }

}
