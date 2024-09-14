<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Drupal\ui_icons_picker\Ajax\UpdateIconSelectionCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a UI Icons picker selector form.
 */
final class IconSelectForm extends FormBase {

  private const AJAX_WRAPPER_ID = 'icon-results-wrapper';
  private const MESSAGE_WRAPPER_ID = 'icon-message-wrapper';
  private const NUM_PER_PAGE = 308;
  private const SEARCH_MIN_LENGTH = 2;

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.ui_icons_pack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_picker_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $options = NULL): array {

    $request = $this->getRequest();
    if (!$request->query->has('dialogOptions')) {
      return [];
    }

    $options = $request->query->all('dialogOptions');
    $wrapper_id = $options['query']['wrapper_id'] ?? NULL;

    if (NULL === $wrapper_id) {
      return [];
    }

    $allowed_icon_pack = $options['query']['allowed_icon_pack'] ?? '';

    if (!$modal_state = static::getModalState($form_state)) {
      $modal_state = [
        'page' => 0,
        'icon_list' => $this->pluginManagerIconPack->getIcons(),
      ];
      static::setModalState($form_state, $modal_state);
    }

    $input = $form_state->getUserInput();
    $search = $input['filter'] ?? '';
    $icons_list = $modal_state['icon_list'];

    if (!empty($search) && strlen($search) > self::SEARCH_MIN_LENGTH) {
      $icons_list = array_filter($icons_list, fn($id) => str_contains($id, $search), ARRAY_FILTER_USE_KEY);
    }

    $display_settings = [
      'width' => 32,
      'height' => 32,
    ];

    $icons = $this->filterIcons($icons_list, $allowed_icon_pack, $display_settings);
    $pager = $this->createPager($modal_state['page'], count($icons));
    $icons = array_slice($icons, $pager['offset'], self::NUM_PER_PAGE);

    $form['#prefix'] = '<div id="' . self::AJAX_WRAPPER_ID . '"><div id="' . self::MESSAGE_WRAPPER_ID . '"></div>';
    $form['#suffix'] = '</div>';

    $form['wrapper_id'] = [
      '#type' => 'hidden',
      '#value' => $wrapper_id,
    ];

    $ajax_settings = [
      'callback' => [$this, 'searchAjax'],
      'wrapper' => self::AJAX_WRAPPER_ID,
      'effect' => 'fade',
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    $form['filters']['filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter'),
      '#placeholder' => $this->t('Filter by name'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($input['filter']) ? $input['filter'] : '',
      '#attributes' => [
        'class' => [
          'icon-filter-input',
        ],
      ],
    ];

    $form['filters']['search'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'searchSubmit']],
      '#ajax' => $ajax_settings,
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => [
          'icon-ajax-search-submit',
          'hidden',
        ],
      ],
    ];

    if (empty($icons)) {
      $form['list'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No icon found, please adjust your filters and try again.'),
      ];
      return $form;
    }

    $form['list'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-picker-modal__content'],
      ],
      '#attached' => ['library' => ['ui_icons_picker/library']],
    ];

    $form['list']['icons_preview'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hidden'],
      ],
    ];

    $options = [];
    foreach ($icons as $icon_full_id => $icon) {
      $form['list']['icons_preview'][$icon_full_id] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'icon-preview',
          ],
          'data-icon-id' => $icon_full_id,
        ],
        'icon' => $icon,
      ];
      $options[$icon_full_id] = $icon['#context']['icon_label'] ?? '';
    }

    $form['list']['icon_full_id'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#attributes' => [
        'class' => ['icon-radio', 'hidden'],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
      '#submit' => [],
      '#ajax' => [
        'callback' => [$this, 'selectIconAjax'],
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'icon-ajax-select-submit',
          'hidden',
        ],
      ],
    ];

    if ($pager['total_page'] <= 1) {
      return $form;
    }

    $form['pagination'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pagination'],
      ],
    ];

    $form['pagination']['page_previous'] = $pager['page_previous'];
    $form['pagination']['page_next'] = $pager['page_next'];
    $form['pagination']['page_info'] = $pager['page_info'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);
    if ('submit' !== $clicked_button) {
      return;
    }

    $icon_full_id = $form_state->getValue('icon_full_id');
    if (!$icon_full_id) {
      $form_state->setError($form['list'], $this->t('Pick an icon to insert.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Submission handler for the "Previous page" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function previousPageSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page']--;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Next page" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function nextPageSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page']++;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Search" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function searchSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page'] = 0;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * When searching, simply return a ajax response.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to replace the form.
   */
  public function searchAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    return $this->ajaxRenderFormAndMessages($form);
  }

  /**
   * Renders form and status messages and returns an ajax response.
   *
   * Used for both submission buttons.
   *
   * @param array $form
   *   The form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to replace the form.
   */
  protected function ajaxRenderFormAndMessages(array &$form): AjaxResponse {
    $response = new AjaxResponse();
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $renderer = \Drupal::service('renderer');

    $status_messages = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $output = $renderer->renderRoot($form);
    $messages = $renderer->renderRoot($status_messages);

    $message_wrapper_id = '#' . self::MESSAGE_WRAPPER_ID;

    $response->setAttachments($form['#attached']);
    $response->addCommand(new ReplaceCommand('', $output));
    $response->addCommand(new HtmlCommand($message_wrapper_id, ''));
    $response->addCommand(new AppendCommand($message_wrapper_id, $messages));

    return $response;
  }

  /**
   * Handles the AJAX request to select an icon.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function selectIconAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $errors = $form_state->getErrors();
    if ($errors) {
      return self::ajaxRenderFormAndMessages($form);
    }
    $response = new AjaxResponse();

    $icon_full_id = $form_state->getValue('icon_full_id');
    $wrapper_id = $form_state->getValue('wrapper_id');

    $response->addCommand(new UpdateIconSelectionCommand($icon_full_id, $wrapper_id));
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * Retrieves the modal state from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state from which to retrieve the modal state.
   *
   * @return mixed
   *   The modal state value.
   */
  public static function getModalState(FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), ['list_state']);
  }

  /**
   * Sets the modal state in the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state in which to set the modal state.
   * @param array $field_state
   *   The modal state value to set.
   */
  public static function setModalState(FormStateInterface $form_state, array $field_state): void {
    NestedArray::setValue($form_state->getStorage(), ['list_state'], $field_state);
  }

  /**
   * Filter icons based on criteria.
   *
   * @param array $icons_list
   *   The list of icons to filter.
   * @param string $icon_pack
   *   The icon packs to filter by.
   * @param array $display_settings
   *   The display options for the icons.
   *
   * @return array
   *   The filtered list of icons.
   */
  private function filterIcons(array $icons_list, string $icon_pack = '', array $display_settings = []): array {
    if (!empty($icon_pack)) {
      $icon_pack = explode('+', $icon_pack);
    }

    $icons = [];
    foreach ($icons_list as $id => $icon) {
      if (!empty($icon_pack) && !in_array($icon->getIconPackId(), $icon_pack)) {
        continue;
      }
      $icons[$id] = $icon->getRenderable($display_settings);
    }

    return $icons;
  }

  /**
   * Create the pager list.
   *
   * @param int $current_page
   *   The current page.
   * @param int $total
   *   The icons total.
   *
   * @return array
   *   The pager information and form elements.
   */
  private function createPager(int $current_page, int $total): array {
    $ajax_pager = [
      'callback' => [$this, 'searchAjax'],
      'wrapper' => self::AJAX_WRAPPER_ID,
    ];

    $total_page = (int) round($total / self::NUM_PER_PAGE) + 1;
    $arg = ['@current_page' => $current_page + 1, '@total_page' => $total_page];

    return [
      'current_page' => $current_page + 1,
      'total_page' => $total_page,
      'offset' => self::NUM_PER_PAGE * $current_page,
      'total' => $total,
      // Form elements.
      'page_previous' => [
        '#type' => 'submit',
        '#value' => $this->t('Previous page'),
        '#submit' => [[$this, 'previousPageSubmit']],
        '#ajax' => $ajax_pager,
        '#disabled' => !($current_page > 0),
      ],
      'page_next' => [
        '#type' => 'submit',
        '#value' => $this->t('Next page'),
        '#submit' => [[$this, 'nextPageSubmit']],
        '#ajax' => $ajax_pager,
        '#disabled' => !($total_page > $current_page + 1),
      ],
      'page_info' => [
        '#markup' => $this->t('Page @current_page/@total_page', $arg),
      ],
    ];
  }

}
