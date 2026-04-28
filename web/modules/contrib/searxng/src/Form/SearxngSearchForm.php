<?php

namespace Drupal\searxng\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\searxng\Service\SearxngClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Simple search form that demonstrates SearXNG queries.
 */
class SearxngSearchForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The SearXNG client service.
   *
   * @var \Drupal\searxng\Service\SearxngClient
   */
  protected SearxngClient $client;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : static {
    $instance = new static();
    /** @var \Drupal\searxng\Service\SearxngClient $client */
    $client = $container->get('searxng.client');
    $instance->client = $client;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'searxng_search_form';
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
    $form['q'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#size' => 60,
      '#maxlength' => 255,
      '#default_value' => $form_state->getValue('q') ?: '',
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search SearXNG'),
    ];

    // If we have results from a previous submit, render them here.
    $results = $form_state->get('searxng_results');
    if (!empty($results) && is_array($results)) {
      $items = [];
      foreach ($results as $r) {
        $title = $r['title'] ?? ($r['url'] ?? $this->t('Untitled'));
        $url = $r['url'] ?? ($r['link'] ?? NULL);
        $snippet = $r['content'] ?? ($r['snippet'] ?? '');

        $item_render = [];
        if (!empty($url)) {
          // If URL looks like a path or uri, create a link render array safely.
          try {
            $link_url = Url::fromUri($url);
            $item_render[] = [
              '#type' => 'link',
              '#title' => Html::escape($title),
              '#url' => $link_url,
            ];
          }
          catch (\Exception $e) {
            // Fallback to escaped text if URL is invalid.
            $item_render[] = ['#markup' => Html::escape($title)];
          }
        }
        else {
          $item_render[] = ['#markup' => Html::escape($title)];
        }

        if (!empty($snippet)) {
          $item_render[] = ['#markup' => '<div class="searxng-snippet">' . Html::escape($snippet) . '</div>'];
        }

        $items[] = ['#type' => 'container', 'content' => $item_render];
      }

      $form['results'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['searxng-results']],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $items,
        ],
      ];
    }

    return $form;
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
    $q = trim((string) $form_state->getValue('q'));
    if ($q === '') {
      $form_state->setErrorByName('q', $this->t('Please enter a search query.'));
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
    $q = (string) $form_state->getValue('q');

    // Call the SearXNG client and save results to form state for rebuild.
    $results = $this->client->search($q);

    $form_state->set('searxng_results', $results);
    $form_state->setRebuild(TRUE);
  }

}
