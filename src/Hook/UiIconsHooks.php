<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Hook implementations for ui_icons.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
class UiIconsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.ui_icons':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('UI Icons is a module that aims to simplify the front-end management of icons. For more information, see the <a href=":docs">online documentation for the UI Icons module</a>.', [
          ':docs' => 'https://git.drupalcode.org/project/ui_icons/-/blob/1.0.x/README.md',
        ]) . '</p>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('General') . '</dt>';
        $output .= '<dd>' . $this->t('<a href=":docs">UI Icons</a> allow the declaration of icons in a Yaml file to be used globally on your website. The format allow to integrate many source provider for your icons and expose them in all Drupal display tools.', [
          ':docs' => 'https://git.drupalcode.org/project/ui_icons/-/blob/1.0.x/README.md',
        ]) . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'icon_selector' => [
        'render element' => 'element',
      ],
      'icon_preview' => [
        'variables' => [
          'pack_id' => '',
          'icon_id' => '',
          'icon_label' => '',
          'extractor' => NULL,
          'source' => NULL,
          'library' => NULL,
          'settings' => [],
        ],
      ],
    ];
  }

  /**
   * Prepares variables for input icon template.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the properties of the element.
   *
   * @see src/Element/IconAutocomplete.php
   */
  #[Hook('preprocess_icon_selector')]
  public function preprocessIconSelector(array &$variables): void {
    $variables['has_settings'] = $variables['element']['#show_settings'] ?? FALSE;
    $variables['icon_form'] = $variables['element']['icon_id'] ?? '';

    if ($this->isThemeActive('default_admin')) {
      $variables['icon_form']['#attached']['library'][] = 'ui_icons/ui_icons.default_admin_autocomplete';
    }

    if ($this->isThemeActive('gin')) {
      $variables['icon_form']['#attached']['library'][] = 'ui_icons/ui_icons.gin_autocomplete';
    }

    if ($this->isThemeActive('ui_suite_daisyui')) {
      $variables['icon_form']['#attached']['library'][] = 'ui_icons/ui_icons.daisyui_autocomplete';
    }

    if ($this->isThemeActive('ui_suite_dsfr')) {
      $variables['icon_form']['#attached']['library'][] = 'ui_icons/ui_icons.dsfr_autocomplete';
    }

    if (isset($variables['element']['icon_id']['#value'])) {
      if (!$icon_data = IconDefinition::getIconDataFromId($variables['element']['icon_id']['#value'])) {
        return;
      }
      $variables['pack_id'] = $icon_data['pack_id'];
      $variables['icon_id'] = $icon_data['icon_id'];
    }
    elseif (isset($variables['element']['#value']['object']) && is_object($variables['element']['#value']['object']) && method_exists($variables['element']['#value']['object'], 'getPackId') && method_exists($variables['element']['#value']['object'], 'getId')) {
      $variables['pack_id'] = $variables['element']['#value']['object']->getPackId();
      $variables['icon_id'] = $variables['element']['#value']['object']->getId();
    }
    elseif (!empty($variables['element']['#default_value'])) {
      if (!$icon_data = IconDefinition::getIconDataFromId($variables['element']['#default_value'])) {
        return;
      }
      $variables['pack_id'] = $icon_data['pack_id'];
      $variables['icon_id'] = $icon_data['icon_id'];
    }

    if (isset($variables['element']['icon_settings']) && $variables['has_settings']) {
      $variables['settings_form'] = $variables['element']['icon_settings'];
    }
  }

  /**
   * Determines whether the active theme is a specific theme or a sub-theme.
   *
   * @param string $name
   *   Name of the theme or sub theme.
   *
   * @return bool
   *   TRUE if the active theme is $name or inherits from it.
   */
  protected function isThemeActive(string $name): bool {
    $theme = $this->themeManager->getActiveTheme();
    return $theme->getName() === $name ||
      isset($theme->getBaseThemeExtensions()[$name]);
  }

}
