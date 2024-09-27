<?php

declare(strict_types=1);

namespace Drupal\ui_icons_library\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a UI Icons form.
 *
 * @codeCoverageIgnore
 */
final class LibrarySearchForm extends FormBase {

  private const NUM_PER_PAGE = 196;

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.ui_icons_pack'),
      $container->get('pager.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_library_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $session = $this->getRequest()->getSession();
    $values = $session->get('ui_icons_library_search');
    $search = $values['search'] ?? '';
    $icon_pack = $values['icon_pack'] ?? '';
    $group = $values['group'] ?? '';
    $num_per_page = $values['num_per_page'] ?? self::NUM_PER_PAGE;

    $form['icon_pack'] = [
      '#type' => 'select',
      '#title_display' => 'invisible',
      '#title' => $this->t('Icon Pack'),
      '#default_value' => $icon_pack,
      '#options' => ['' => $this->t('- Select pack -')] + $this->pluginManagerIconPack->listIconPackOptions(),
      '#weight' => -11,
    ];

    $form['search'] = [
      '#type' => 'textfield',
      '#size' => 12,
      '#default_value' => $search,
      '#title' => $this->t('Keywords'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Keywords'),
      '#weight' => -10,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#name' => 'reset',
      '#value' => $this->t('Reset'),
    ];

    $form['actions']['#weight'] = -9;

    $icons_list = $this->pluginManagerIconPack->getIcons();

    if (!empty($icon_pack)) {
      $group_options = [];
      foreach ($icons_list as $icon) {
        if ($icon_pack !== $icon->getIconPackId()) {
          continue;
        }
        $group_id = $icon->getGroup();
        if (empty($group_id)) {
          continue;
        }
        $group_options[$group_id] = ucfirst($group_id);
      }

      if (!empty($group_options)) {
        ksort($group_options);
        $form['group'] = [
          '#type' => 'select',
          '#title_display' => 'invisible',
          '#title' => $this->t('Group'),
          '#default_value' => $group,
          '#options' => ['' => $this->t('- Select group -')] + $group_options,
          '#weight' => -11,
        ];
      }
    }

    if (!empty($search)) {
      $icons_list = array_filter($icons_list, fn($id) => str_contains($id, $search), ARRAY_FILTER_USE_KEY);
    }

    $icons = $this->filterIcons($icons_list, $icon_pack, $group);
    $icons_keys = array_keys($icons);
    array_multisort($icons_keys, SORT_NATURAL, $icons);

    $total = count($icons);
    if ($total > self::NUM_PER_PAGE) {
      $options = [self::NUM_PER_PAGE, self::NUM_PER_PAGE * 2, self::NUM_PER_PAGE * 4];
      $form['num_per_page'] = [
        '#type' => 'select',
        '#title' => $this->t('Icons per page'),
        '#title_display' => 'invisible',
        '#options' => array_combine($options, $options),
        '#default_value' => $num_per_page,
        '#weight' => -10,
      ];
    }

    $pager = $this->pagerManager->createPager($total, (int) $num_per_page);
    $page = $pager->getCurrentPage();
    $offset = (int) $num_per_page * $page;

    $icons = array_slice($icons, $offset, (int) $num_per_page);

    $form['list'] = [
      '#theme' => 'ui_icons_library',
      '#search' => $search,
      '#icons' => $icons,
      '#total' => $total,
      '#available' => count($icons_list),
      '#weight' => 1,
    ];

    $form['pager-top'] = [
      '#type' => 'pager',
      '#weight' => 0,
    ];

    $form['pager'] = [
      '#type' => 'pager',
      '#weight' => 2,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();

    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#name']) && 'reset' === $trigger['#name']) {
      $session->remove('ui_icons_library_search');
      return;
    }
    $values = $form_state->getValues();
    foreach (array_keys($values) as $key) {
      if (FALSE !== strpos($key, 'form_') || 'op' === $key || 'submit' === $key) {
        unset($values[$key]);
      }
    }

    $session->set('ui_icons_library_search', $values);
  }

  /**
   * Filter icons based on criteria.
   *
   * @param array $icons_list
   *   The list of icons to filter.
   * @param string $icon_pack
   *   The icon set to filter by.
   * @param string $group
   *   The group to filter by.
   *
   * @return array
   *   The filtered list of icons.
   */
  private function filterIcons(array $icons_list, string $icon_pack, string $group): array {
    $icons = [];
    foreach ($icons_list as $icon) {
      if (!empty($icon_pack) && $icon_pack !== $icon->getIconPackId()) {
        continue;
      }
      if (!empty($group) && $group !== $icon->getGroup()) {
        continue;
      }
      // Generate a key for sorting.
      $key = $icon->getLabel() . ' ' . $icon->getIconPackLabel();
      $icons[$key] = $icon->getPreview(['size' => 48]);
      $icons[$key]['#group'] = $icon->getGroup();
      $icons[$key]['#label'] = $icon->getLabel();
      $icons[$key]['#pack_label'] = $icon->getIconPackLabel();
    }

    return $icons;
  }

}
