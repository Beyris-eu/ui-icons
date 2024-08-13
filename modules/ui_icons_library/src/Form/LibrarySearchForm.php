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
    $iconPack = $values['icon_pack'] ?? '';
    $group = $values['group'] ?? '';
    $num_per_page = $values['num_per_page'] ?? 200;

    $form['icon_pack'] = [
      '#type' => 'select',
      '#title_display' => 'invisible',
      '#title' => $this->t('Icon Pack'),
      '#default_value' => $iconPack,
      '#options' => ['' => $this->t('- Select Icon Pack -')] + $this->pluginManagerIconPack->listIconPackOptions(),
      '#weight' => -11,
    ];

    $form['search'] = [
      '#type' => 'textfield',
      '#size' => 20,
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

    $form['#access'] = !empty($icons_list);

    if (!empty($iconPack)) {
      $group_options = [];
      foreach ($icons_list as $icon) {
        if ($iconPack !== $icon->getIconPackId()) {
          continue;
        }
        $group_id = $icon->getGroup();
        $group_options[$group_id] = ucfirst($group_id);
      }
      ksort($group_options);

      $form['group'] = [
        '#type' => 'select',
        '#title_display' => 'invisible',
        '#title' => $this->t('Group'),
        '#default_value' => $group,
        '#options' => ['' => $this->t('- Select Group -')] + $group_options,
        '#weight' => -11,
      ];
    }

    if (!empty($search)) {
      $icons_list = array_filter($icons_list, fn($id) => str_contains($id, $search), ARRAY_FILTER_USE_KEY);
    }
    $display_options = [
      'width' => 50,
      'height' => 50,
    ];
    $icons = $this->filterIcons($icons_list, $iconPack, $group, $display_options);

    $total = count($icons);
    if ($total > 200) {
      $options = [200, 600, 1200];
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
    if ('reset' === $form_state->getTriggeringElement()['#name']) {
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
   * @param string $iconPack
   *   The icon set to filter by.
   * @param string $group
   *   The group to filter by.
   * @param array $display_options
   *   The display options for the icons.
   *
   * @return array
   *   The filtered list of icons.
   */
  private function filterIcons(array $icons_list, string $iconPack, string $group, array $display_options): array {
    $icons = [];
    foreach ($icons_list as $id => $icon) {
      if (!empty($iconPack) && $iconPack !== $icon->getIconPackId()) {
        continue;
      }
      if (!empty($group) && $group !== $icon->getGroup()) {
        continue;
      }
      $icons[$id] = $icon->getRenderable($display_options);
      $icons[$id]['#group'] = $icon->getGroup();
    }
    return $icons;
  }

}
