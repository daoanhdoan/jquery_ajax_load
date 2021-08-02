<?php
/**
 *
 */

namespace Drupal\jquery_ajax_load\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Render\RenderContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class JqueryAjaxLoadController extends ControllerBase
{
  public function load(Request $request)
  {
    $requestUrl = $request->query->get('requestUrl');
    $selector = $request->query->get('selector');
    $behaviour = $request->query->get('behaviour', 'replace');

    /** @var Request $new_request */
    $new_request = Request::createFromGlobals();
    $replace_request = \Drupal::requestStack()->pop();
    $system_path = \Drupal::service('path_alias.manager')->getPathByAlias($requestUrl);
    $new_request->attributes = $replace_request->attributes;
    $router = \Drupal::service('router.no_access_checks');
    $route = $router->match($system_path);
    $new_request->attributes->replace($route);

    $response = \Drupal::service('jquery_ajax_load')->getKernelHandleRaw($new_request);

    \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use (&$response) {
      return \Drupal::service('renderer')->render($response);
    });

    $main_content = $response;
    $main_content['#printed'] = FALSE;

    switch ($behaviour) {
      /*case "prepend":
        $command = new PrependCommand("#{$selector}", $main_content);
        break;
      case "append":
        $command = new AppendCommand("#{$selector}", $main_content);
        break;*/
      case "dialog":
        $main_content['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $title = $this->t('Edit entity @entity');
        $command = new OpenDialogCommand("#{$selector}", $title, $main_content, ['modal' => TRUE, 'width' => 800]);
        break;
      default:
        $command = new HtmlCommand("#{$selector}", $main_content);
    }

    $response = AjaxResponse::create()->addCommand($command);
    return $response;
  }

  /**
   * Returns only content part of page for Delivery Callback function.
   */

  public function deliveryCallback($render_array) {
    $main_content = $render_array;
    $main_content['#printed'] = FALSE;

    $rendered_page_markup = NULL;
    if ($render_array) {
      /** @var BareHtmlPageRendererInterface $bare_html_page_renderer */
      $bare_html_page_renderer = \Drupal::service('bare_html_page_renderer');
      $rendered_page = $bare_html_page_renderer->renderBarePage($main_content, 'preview-title', 'page');
      $rendered_page_markup = $rendered_page->getContent();
    }

    if ($rendered_page_markup) {
      // JS
      preg_match_all('#<script(.*?)<\/script>#is', $rendered_page_markup, $matches);
      // Remove all js from response to avoid useless requests
      foreach ($matches[0] as $value) {
        $pos = strpos($rendered_page_markup, $value);
        if ($pos !== false) {
          $rendered_page_markup = substr_replace($rendered_page_markup, '', $pos, strlen($value));
        }
      }

      // CSS
      preg_match_all('#<style(.*?)<\/style>#is', $rendered_page_markup, $css_style_matches);
      // Remove all css from response to avoid useless requests
      foreach ($css_style_matches[0] as $value) {
        $pos = strpos($rendered_page_markup, $value);
        if ($pos !== false) {
          $rendered_page_markup = substr_replace($rendered_page_markup, '', $pos, strlen($value));
        }
      }

      // Inline CSS
      preg_match_all('#<link rel="stylesheet"(.*?) \/>#is', $rendered_page_markup, $css_matches);
      // Remove all inline css from response to avoid useless requests
      foreach ($css_matches[0] as $value) {
        $pos = strpos($rendered_page_markup, $value);
        if ($pos !== false) {
          $rendered_page_markup = substr_replace($rendered_page_markup, '', $pos, strlen($value));
        }
      }

      // Body
      preg_match_all('#<body(.*?)<\/body>#is', $rendered_page_markup, $body_matches);

      // Title Site name
      $config = \Drupal::config('system.site');
      $site_variables['site']['name'] = $config->get('name');

      $response_markup = [
        'inline_css' => $css_style_matches,
        'title' => ' | ' . $site_variables['site']['name'],
        'js' => $matches[0],
        'css' => $css_matches[0],
        'content' => $body_matches[0][0],
      ];

      $html = '<html><head><title></title>';
      $config = \Drupal::config('jquery_ajax_load.settings');
      if ($config->get('css')) $html .= implode("\n", $response_markup['css']);
      if ($config->get('js')) $html .= implode("\n", $response_markup['js']);
      $html .= '</head><body class="jquery-ajax-load">' . $response_markup['content'] . '</body></html>';

      return new Response($html);
    }
    throw new NotFoundHttpException();

  }
}
