<?php

namespace Drupal\jquery_ajax_load\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * JQuery Autosize settings form include file.
 */
class JqueryAjaxLoadSettingsForm extends ConfigFormBase
{
  /**
   * Admin settings menu callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('jquery_ajax_load.presets');
    $elements = [];
    $items = array_merge($config->getRawData(), [['trigger' => '.jquery_ajax_load', 'target' => '', 'behaviour' => 'replace']]);
    foreach ($items as $id => $item) {
      $elements[$id]['trigger'] = array(
        '#type' => 'textarea',
        '#rows' => 3,
        '#title' => t('Valid jQuery Classes/IDs to trigger TB Modal via Ajax (One per line)'),
        '#title_display' => 'invisible',
        '#default_value' => $item['trigger'],
      );
      $elements[$id]['target'] = array(
        '#type' => 'textfield',
        '#title' => t('A valid jQuery ID where AJAX callback will be rendered'),
        '#title_display' => 'invisible',
        '#default_value' => $item['target'],
        '#size' => 60,
        '#maxlength' => 128,
      );
      $elements[$id]['behaviour'] = array(
        '#type' => 'select',
        '#title' => t('Behaviour'),
        '#title_display' => 'invisible',
        '#options' => ['append' => t('Append'), 'prepend' => t('Prepend'), 'replace' => t('Replace'), 'dialog' => t('Dialog')],
        '#default_value' => $item['behaviour'],
      );
    }
    $description[] = t('(*): Specify the class/ID of links to convert that link to AJAX, one per line. For example by providing ".jquery_ajax_load" will convert any link with class="jquery_ajax_load"');
    $description[] = t('(**): A valid jQuery ID where AJAX callback will be rendered');
    $form['presets_wrapper'] = array(
      '#type' => 'details',
      '#title' => t('Presets'),
      '#tree' => FALSE,
      '#open' => count($config->getRawData())
    );
    $form['presets_wrapper']['presets'] = [
      '#type' => 'table',
      '#header' => [t('Valid jQuery Classes/IDs to trigger load content via Ajax (One per line) (*)'), t('A valid jQuery ID where AJAX callback will be rendered (**)'), t('Behaviour')],
      '#tree' => TRUE,
    ] + $elements;
    $form['presets_wrapper']['help'] = [
      '#theme' => 'item_list',
      '#items' => $description
    ];
    $form['presets_wrapper']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
    ];

    $settings = $this->config('jquery_ajax_load.settings');
    $form['settings'] = array(
      '#type' => 'details',
      '#title' => t('Settings'),
      '#tree' => TRUE,
    );
    $form['settings']['toggle'] = array(
      '#type' => 'checkbox',
      '#title' => t('Check if you want link to act as toggle buttom'),
      '#default_value' => $settings->get('toggle'),
      '#description' => t('If toggle is activated, content on target will desappear when link is clicked twice.'),
    );
    $form['settings']['animation'] = array(
      '#type' => 'checkbox',
      '#title' => t('Check if you want link to use jQuery show and hide effects'),
      '#default_value' => $settings->get('animation'),
      '#description' => t('If animation is activated, content on target will show and desappear using jQuery show and hide standard effects.'),
    );
    $form['settings']['css'] = array(
      '#type' => 'checkbox',
      '#title' => t('Check if you want to load css files'),
      '#default_value' => $settings->get('css'),
      '#description' => t('If CSS loading is enabled AJAX will call CSS files and loaded with content. This affects performance.'),
    );
    $form['settings']['js'] = array(
      '#type' => 'checkbox',
      '#title' => t('Check if you want to load js files'),
      '#default_value' => $settings->get('js'),
      '#description' => t('If JS loading is enabled AJAX will call JS files and loaded with content. This affects performance.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->configFactory()->getEditable('jquery_ajax_load.presets');
    $config->delete();
    foreach ($form_state->getValue('presets') as $key => $value) {
      if (!empty($value['trigger']) && !empty($value['target'])) {
        $config->set($key, $value);
      }
    }
    $config->save();
    $config = $this->configFactory()->getEditable('jquery_ajax_load.settings');
    foreach ($form_state->getValue('settings') as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
    $this->configFactory()->clearStaticCache();
    drupal_flush_all_caches();
    $this->messenger()->addStatus($this->t('All settings have been saved.'));
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames()
  {
    return ['jquery_ajax_load.settings', 'jquery_ajax_load.presets'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return "jquery_ajax_load_settings";
  }
}
