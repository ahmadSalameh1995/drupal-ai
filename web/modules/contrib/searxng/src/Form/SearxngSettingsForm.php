<?php

namespace Drupal\searxng\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure SearXNG settings for this site.
 */
class SearxngSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'searxng_settings_form';
  }

  /**
   * Get the config name.
   *
   * @return array<mixed>
   *   The config names as array.
   */
  protected function getEditableConfigNames(): array {
    return ['searxng.settings'];
  }

  /**
   * Form builder.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<mixed>
   *   The built form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('searxng.settings');

    $form['endpoint_url'] = [
      '#type' => 'url',
      '#title' => $this->t('SearXNG endpoint URL'),
      '#description' => $this->t('Full URL to the SearXNG API endpoint (for example: https://searx.example/search). Must include the search path ("/search").'),
      '#default_value' => $config->get('endpoint_url') ?? '',
      '#required' => TRUE,
    ];

    // Optional API key if your SearXNG instance requires one.
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Optional API key or token for your SearXNG instance (leave empty if not required). Do not include bearer prefix.'),
      '#default_value' => $config->get('api_key') ?? '',
      '#required' => FALSE,
    ];

    // Timeout in seconds for outbound requests.
    $form['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout (seconds)'),
      '#description' => $this->t('Maximum number of seconds to wait for a response from SearXNG.'),
      '#default_value' => $config->get('timeout') ?? 30,
      '#min' => 1,
      '#required' => TRUE,
    ];

    // Default search options (categories, language, format, safesearch).
    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default search options'),
      '#open' => TRUE,
    ];

    $form['defaults']['categories'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default categories'),
      '#description' => $this->t('Comma-separated list of SearXNG categories to use by default (for example: "general,images"). Leave empty to not set a default. <br />See <a href="https://docs.searxng.org/user/configured_engines.html#configured-engines">https://docs.searxng.org/user/configured_engines.html#configured-engines</a>'),
      '#default_value' => $config->get('categories') ?? '',
      '#required' => FALSE,
    ];

    $form['defaults']['language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default language'),
      '#description' => $this->t('Optional language code to pass to SearXNG (for example: "en"). Leave empty to not set a default.<br />See <a href="https://github.com/searxng/searxng/blob/master/searx/sxng_locales.py">https://github.com/searxng/searxng/blob/master/searx/sxng_locales.py</a>'),
      '#default_value' => $config->get('language') ?? '',
      '#required' => FALSE,
    ];

    $form['defaults']['safesearch'] = [
      '#type' => 'select',
      '#title' => $this->t('Default safesearch level'),
      '#options' => [
        '0' => $this->t('Off'),
        '1' => $this->t('Moderate'),
        '2' => $this->t('Strict'),
      ],
      '#default_value' => (string) ($config->get('safesearch') ?? '1'),
      '#description' => $this->t('Default safesearch level to send to SearXNG.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form validator.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @param-out array<mixed> $form
   *    The form array.
   *
   * @return void
   *   Does not return.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $endpoint = (string) $form_state->getValue('endpoint_url');
    if (strpos($endpoint, '/search') === FALSE) {
      $form_state->setErrorByName('endpoint_url', $this->t('The endpoint URL should include the search path, for example: /search.'));
    }

    $timeout = $form_state->getValue('timeout');
    if (!is_numeric($timeout) || (int) $timeout < 1) {
      $form_state->setErrorByName('timeout', $this->t('Timeout must be a positive integer.'));
    }

    $safesearch = $form_state->getValue('safesearch');
    if (!in_array((string) $safesearch, ['0', '1', '2'], TRUE)) {
      $form_state->setErrorByName('safesearch', $this->t('Invalid safesearch value.'));
    }
  }

  /**
   * Form submit.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void
   *   Does not return.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('searxng.settings')
      ->set('endpoint_url', trim((string) $form_state->getValue('endpoint_url')))
      ->set('api_key', trim((string) $form_state->getValue('api_key')))
      ->set('timeout', (int) $form_state->getValue('timeout'))
      ->set('categories', trim((string) $form_state->getValue('categories')))
      ->set('language', trim((string) $form_state->getValue('language')))
      ->set('safesearch', (int) $form_state->getValue('safesearch'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
