<?php

declare(strict_types=1);

namespace Drupal\ui_icons_menu\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\MenuInterface;

/**
 * Hook implementations for ui_icons_menu.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
class UiIconsMenuHooks {

  use StringTranslationTrait;

  /**
   * Widgets of the menu link field, set by ::entityBaseFieldInfoAlter().
   */
  private const LINK_WIDGETS = ['icon_link_widget', 'icon_link_attributes_widget'];

  /**
   * Icon settings of a menu that has none of its own.
   */
  private const DEFAULT_SETTINGS = [
    'icon_selector' => 'icon_autocomplete',
    'result_format' => 'list',
    'icon_required' => FALSE,
    'icon_position' => TRUE,
    'allowed_icon_pack' => [],
  ];

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RendererInterface $renderer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly IconPackManagerInterface $pluginManagerIconPack,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ('help.page.ui_icons_menu' === $route_name) {
      $output = '';
      $output .= '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t('The UI Icons Menu module overtakes the core default widget for menu link content entities, allowing you to set icons on menu links.') . '</p>';
      $output .= '<p>' . $this->t('The icon selector and the allowed icon packs of each menu are set on the menu edit form.') . '</p>';
      return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   *
   * Each menu can override these settings, see ::formMenuFormAlter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() !== 'menu_link_content') {
      return;
    }

    $type = 'icon_link_widget';
    if ($this->moduleHandler->moduleExists('ui_icons_field_link_attributes')) {
      $type = 'icon_link_attributes_widget';
    }

    $fields['link']->setDisplayOptions('form', [
      'type' => $type,
      // Allow icon position and settings.
      'settings' => ['show_settings' => TRUE] + self::DEFAULT_SETTINGS,
    ]);
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for menu_form.
   *
   * Each menu sets the icon widget of its own links.
   */
  #[Hook('form_menu_form_alter')]
  public function formMenuFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    $menu = method_exists($form_object, 'getEntity') ? $form_object->getEntity() : NULL;
    if (!$menu instanceof MenuInterface) {
      return;
    }
    $own_settings = $menu->getThirdPartySettings('ui_icons_menu');
    $settings = $own_settings + self::DEFAULT_SETTINGS;

    $selectors = ['icon_autocomplete' => $this->t('Autocomplete')];
    if ($this->moduleHandler->moduleExists('ui_icons_picker')) {
      $selectors['icon_picker'] = $this->t('Picker');
    }

    // Same fields and labels as IconLinkWidgetTrait::settingsForm().
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Icons'),
      '#description' => $this->t('How icons are selected on the links of this menu.'),
      '#open' => $own_settings !== [],
      '#tree' => TRUE,
    ];
    $element['icon_selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon selector'),
      '#options' => $selectors,
      '#default_value' => $settings['icon_selector'],
    ];
    $element['result_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Result format'),
      '#options' => ['list' => $this->t('List'), 'grid' => $this->t('Grid')],
      '#default_value' => $settings['result_format'],
      '#states' => [
        'visible' => [
          ':input[name="ui_icons_menu[icon_selector]"]' => ['value' => 'icon_autocomplete'],
        ],
      ],
    ];
    $element['icon_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon required'),
      '#default_value' => $settings['icon_required'],
    ];
    $element['icon_position'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow icon display position selection'),
      '#description' => $this->t('If selected, a "position" select will be made available.'),
      '#default_value' => $settings['icon_position'],
    ];
    $element['allowed_icon_pack'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed icon packs'),
      '#description' => $this->t('If none are selected, all will be allowed.'),
      '#options' => $this->pluginManagerIconPack->listIconPackOptions(TRUE),
      '#default_value' => $settings['allowed_icon_pack'],
    ];

    // Above the links table of the edit form, not below it.
    $position = array_search('links', array_keys($form), TRUE);
    if ($position === FALSE) {
      $form['ui_icons_menu'] = $element;
    }
    else {
      $form = array_slice($form, 0, $position, TRUE) + ['ui_icons_menu' => $element] + array_slice($form, $position, NULL, TRUE);
    }

    $form['#entity_builders'][] = [static::class, 'menuEntityBuilder'];
  }

  /**
   * Entity builder storing the icon settings on the menu.
   *
   * @see ::formMenuFormAlter()
   */
  public static function menuEntityBuilder(string $entity_type_id, MenuInterface $menu, array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('ui_icons_menu');
    $settings = [
      'icon_selector' => (string) $values['icon_selector'],
      'result_format' => (string) $values['result_format'],
      'icon_required' => (bool) $values['icon_required'],
      'icon_position' => (bool) $values['icon_position'],
      'allowed_icon_pack' => array_filter($values['allowed_icon_pack']),
    ];
    foreach ($settings as $key => $value) {
      $menu->setThirdPartySetting('ui_icons_menu', $key, $value);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_prepare_form() for menu_link_content.
   *
   * Applies the icon settings of the link's menu to its widget. Core has set
   * the form display by now. It is cloned, as the loaded one may serve another
   * form of the request.
   */
  #[Hook('menu_link_content_prepare_form')]
  public function menuLinkContentPrepareForm(MenuLinkContentInterface $menu_link, string $operation, FormStateInterface $form_state): void {
    $menu = $this->entityTypeManager->getStorage('menu')->load($menu_link->getMenuName());
    $settings = $menu instanceof MenuInterface ? $menu->getThirdPartySettings('ui_icons_menu') : [];
    $display = $form_state->get('form_display');
    $component = $display?->getComponent('link');
    if ($settings === [] || !in_array($component['type'] ?? NULL, self::LINK_WIDGETS, TRUE)) {
      return;
    }

    $component['settings'] = $settings + $component['settings'];
    $display = clone $display;
    $display->setComponent('link', $component);
    $form_state->set('form_display', $display);
  }

  /**
   * Implements hook_preprocess_menu().
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    // Ignore preprocess if there are no items or the menu is a navigation one.
    if (empty($variables['items']) ||
      str_starts_with($variables['theme_hook_original'] ?? '', 'navigation_menu__')
    ) {
      return;
    }
    $this->processMenuItems($variables['items']);
  }

  /**
   * Implements hook_link_alter().
   *
   * Allow the icon to be displayed on the menu administration context in
   * /admin/structure/menu/manage.
   */
  #[Hook('link_alter')]
  public function linkAlter(array &$variables): void {
    if (!isset($variables['url']) || !isset($variables['text'])) {
      return;
    }

    if (!$icon = $variables['url']->getOption('icon')) {
      return;
    }

    // Check if the link is from menu.
    if (isset($variables['options']['ui_icons_processed'])) {
      return;
    }

    // Do not handle link handled by field link in IconLinkFormatter.
    if ($variables['url']->getOption('ui_icons_processed')) {
      return;
    }

    // Do not handle link if no position found, possible if we have an icon but
    // the display is not set.
    if (!$icon_display = $variables['url']->getOption('icon_display') ?? NULL) {
      return;
    }

    $variables['url']->setOption('ui_icons_processed', TRUE);
    $this->generateMarkup($variables['text'], $icon['target_id'], $icon['settings'] ?? [], $icon_display);
  }

  /**
   * Implements hook_navigation_menu_link_tree_alter().
   */
  #[Hook('navigation_menu_link_tree_alter')]
  public function navigationMenuLinkTreeAlter(array &$tree): void {
    foreach ($tree as $item) {
      $definition = $item->link->getPluginDefinition()['options']['icon'] ?? NULL;
      if (!isset($definition['target_id']) || !$icon_data = IconDefinition::getIconDataFromId($definition['target_id'])) {
        continue;
      }

      $definition['settings'] = NestedArray::mergeDeep($definition['settings'][$icon_data['pack_id']] ?? [], ['class' => 'toolbar-button__icon']);

      $item->options['icon'] = $definition + $icon_data;
    }
  }

  /**
   * Handle menu items to add our icon.
   *
   * @param array $items
   *   The menu items.
   */
  protected function processMenuItems(array &$items): void {
    foreach ($items as &$item) {
      if (empty($item['url'])) {
        continue;
      }

      $this->processMenuItem($item);

      if (!empty($item['below'])) {
        $this->processMenuItems($item['below']);
      }
    }
  }

  /**
   * Handle a single menu item.
   *
   * @param array $item
   *   The menu item.
   */
  protected function processMenuItem(array &$item): void {
    // Being extra defensive on the menu as other themes/modules can alter in
    // unknown ways.
    if (!isset($item['url'])) {
      return;
    }

    if (!$item['url'] instanceof Url) {
      return;
    }

    /** @var \Drupal\Core\Url $url */
    $url = &$item['url'];

    if (!$icon = $url->getOption('icon')) {
      return;
    }

    if (empty($icon['target_id'])) {
      return;
    }
    if ($url->getOption('ui_icons_processed')) {
      return;
    }

    $url->setOption('ui_icons_processed', TRUE);
    $this->generateMarkup($item['title'], $icon['target_id'] ?? '', $icon['settings'] ?? [], $url->getOption('icon_display') ?? 'before');
  }

  /**
   * Helper to generate the expected markup for the link with icon.
   *
   * @param mixed $text
   *   The text reference to generate markup for.
   * @param string $icon_full_id
   *   The icon full id.
   * @param array $icon_settings
   *   The icon settings.
   * @param string $icon_display
   *   The icon position, `icon_only`, `after` or default `before`.
   */
  protected function generateMarkup(mixed &$text, string $icon_full_id, array $icon_settings, string $icon_display = 'before'): void {
    $icon_renderable = IconDefinition::getRenderable($icon_full_id, $icon_settings);
    // Render in the active context so the icon pack library bubbles up to the
    // page. ::linkAlter() runs on every generated link, including outside a
    // render pipeline, where ::render() would throw, so isolate there.
    $icon = $this->renderer->hasRenderContext()
      ? $this->renderer->render($icon_renderable)
      : $this->renderer->renderInIsolation($icon_renderable);

    switch ($icon_display) {
      case 'before':
        $text = new FormattableMarkup('@icon <span class="ui-icons-menu-text">@title</span>', [
          '@title' => $text,
          '@icon' => $icon,
        ]);
        break;

      case 'after':
        $text = new FormattableMarkup('<span class="ui-icons-menu-text">@title</span> @icon', [
          '@title' => $text,
          '@icon' => $icon,
        ]);
        break;

      default:
        $text = new FormattableMarkup('@icon', [
          '@icon' => $icon,
        ]);
        break;
    }
  }

}
