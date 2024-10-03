<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\ui_icons\IconDefinitionInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for UI Icons routes.
 *
 * @todo provide the icon rendered in the result or after selection
 */
class IconAutocompleteController extends ControllerBase {

  private const SEARCH_MIN_LENGTH = 2;

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.ui_icons_pack'),
      $container->get('renderer'),
    );
  }

  /**
   * Menu callback for UI Icons autocompletion.
   *
   * This function inspects the 'q' query parameter for the string to use to
   * search for icons, and allowed_icon_pack if set.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for Icons.
   */
  public function handleSearchIcons(Request $request): JsonResponse {
    $query = trim((string) $request->query->get('q', ''));

    // @todo global match length with autocomplete in
    // web/modules/ui_icons/js/icon.autocomplete.js
    if (empty($query) || mb_strlen($query) < self::SEARCH_MIN_LENGTH) {
      return new JsonResponse([]);
    }

    $allowed_icon_pack = NULL;
    if ($request->query->get('allowed_icon_pack', NULL)) {
      $allowed_icon_pack = explode('+', (string) $request->query->get('allowed_icon_pack', ''));
    }

    $max_result = (int) $request->query->get('max_result', 20);

    $icons = $this->pluginManagerIconPack->getIcons($allowed_icon_pack);
    if (empty($icons)) {
      return new JsonResponse([]);
    }

    $query = preg_replace('/[^ \w-]/', '', preg_split('/\s+/', $query));
    if (empty($query) || empty($query[0] ?? '')) {
      return new JsonResponse([]);
    }

    $result = $this->searchIcon($icons, $query, $max_result, $allowed_icon_pack);

    return new JsonResponse($result);
  }

  /**
   * Find an icon based on search string.
   *
   * The search is fuzzy on words with a priority:
   *  - Words in order
   *  - Words in any order
   *  - Any parts of words
   * For example if I search.
   *
   * @param \Drupal\ui_icons\IconDefinitionInterface[] $icons
   *   The list of icons definitions.
   * @param array $words
   *   The keywords to search.
   * @param int $max_result
   *   Maximum result to show.
   * @param array $allowed_icon_pack
   *   Restrict to an icon pack list.
   *
   * @return array
   *   The icons matching the search.
   */
  private function searchIcon(array $icons, array $words, int $max_result, ?array $allowed_icon_pack = NULL): array {
    // First is exact words order.
    $exactOrderPattern = '/' . implode('\s+', array_map(function ($word) {
      return '\b' . preg_quote($word, '/') . '\b';
    }, $words)) . '/i';
    // Then any order.
    $anyOrderPattern = '/' . implode('.*', array_map(function ($word) {
      return '\b' . preg_quote($word, '/') . '\b';
    }, $words)) . '/i';
    // Any words part.
    $anyPartPattern = '/' . implode('.*', array_map('preg_quote', $words)) . '/i';

    $matches = [];
    foreach ($icons as $icon) {
      // Search is based on icon clean label and pack_label.
      $item = trim($icon->getLabel() . ' ' . $icon->getData('pack_label'));

      if (preg_match($exactOrderPattern, $item)) {
        $matches[] = $this->createResultEntry($icon);
      }
      elseif (preg_match($anyOrderPattern, $item)) {
        $matches[] = $this->createResultEntry($icon);
      }
      elseif (preg_match($anyPartPattern, $item)) {
        $matches[] = $this->createResultEntry($icon);
      }

      if (count($matches) >= $max_result) {
        break;
      }
    }

    return $matches;
  }

  /**
   * Create icon result.
   *
   * @param \Drupal\ui_icons\IconDefinitionInterface $icon
   *   The icon definition.
   *
   * @return array
   *   The icon result with keys 'value' and 'label'.
   */
  private function createResultEntry(IconDefinitionInterface $icon): array {
    $icon_renderable = $icon->getPreview(['size' => 24]);
    $renderable = $this->renderer->renderInIsolation($icon_renderable);

    $label = sprintf('%s (%s)', $icon->getLabel(), $icon->getData('pack_label') ?? $icon->getPackId());
    $param = ['@icon' => $renderable, '@name' => $label];
    $label = new FormattableMarkup('<span class="ui-menu-icon">@icon</span> @name', $param);

    return ['value' => $icon->getId(), 'label' => $label];
  }

}
