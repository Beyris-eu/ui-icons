<?php

declare(strict_types=1);

namespace Drupal\ui_icons_ckeditor5\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\ui_icons\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller which renders a preview of the provided icon.
 */
final class IconFilterController implements ContainerInjectionInterface {

  public function __construct(
    protected IconPackManagerInterface $pluginManagerIconPack,
    protected RendererInterface $renderer,
  ) {
    $this->pluginManagerIconPack = $pluginManagerIconPack;
    $this->renderer = $renderer;
  }

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
   * Preview an icon.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The icon string rendered.
   */
  public function preview(Request $request): Response {
    $icon_id = (string) $request->query->get('icon_id');
    if ($icon_id == '') {
      throw new NotFoundHttpException();
    }

    $settings = [];
    $query_settings = (string) $request->query->get('settings');
    if ($query_settings !== '' && json_validate($query_settings)) {
      $settings = json_decode($query_settings, TRUE);
    }

    $icon = $this->pluginManagerIconPack->getIcon($icon_id);
    // Use default settings if none set.
    if (empty($settings)) {
      [$icon_pack_id] = explode(':', $icon_id);
      $settings = $this->pluginManagerIconPack->getExtractorFormDefaults($icon_pack_id);
    }

    $build = $icon->getRenderable($settings);
    $html = $this->renderer->renderInIsolation($build);

    return (new Response((string) $html, 200))
      // Do not allow any intermediary to cache the response, only the end user.
      ->setPrivate()
      // Allow the end user to cache it for up to 5 minutes.
      ->setMaxAge(300);
  }

}
