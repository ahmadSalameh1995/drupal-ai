<?php

namespace Drupal\searxng\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\searxng\Service\SearxngClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

/**
 * Provides a SearXNG search block.
 *
 * @Block(
 *   id = "searxng_search_block",
 *   admin_label = @Translation("SearXNG search"),
 *   category = @Translation("Search")
 * )
 */
class SearxngSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The SearXNG client service.
   *
   * @var \Drupal\searxng\Service\SearxngClient
   */
  protected SearxngClient $client;

  /**
   * Request stack to access the current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * SearxNGSearchBlock constructor.
   *
   * @param array<mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\searxng\Service\SearxngClient $client
   *   The SearXNG client service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, SearxngClient $client, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->client = $client;
    $this->requestStack = $request_stack;
  }

  /**
   * The creator.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array<mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $container->get('form_builder');
    /** @var \Drupal\searxng\Service\SearxngClient $client */
    $client = $container->get('searxng.client');
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $container->get('request_stack');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $form_builder,
      $client,
      $request_stack
    );
  }

  /**
   * The block builder.
   *
   * @return array<mixed>
   *   Returns an array.
   */
  public function build(): array {
    // Render the search form using the injected form builder.
    $form = $this->formBuilder->getForm('\Drupal\searxng\Form\SearxngSearchForm');

    // If a query is provided via the request (GET parameter 'q'), perform a
    // server-side search and render results under the form.
    $request = $this->requestStack->getCurrentRequest();
    $q = $request ? $request->query->get('q') : NULL;

    if (!empty($q)) {
      // Sanitize and validate the search term.
      $search_term = $this->sanitizeSearchTerm((string) $q);

      if (empty($search_term)) {
        $form['error'] = [
          '#markup' => $this->t('Invalid search term provided.'),
          '#prefix' => '<div class="messages messages--error">',
          '#suffix' => '</div>',
        ];
      }
      else {
        $results = $this->client->search($search_term);
        if (!empty($results)) {
          $items = [];
          foreach ($results as $r) {
            $title = $r['title'] ?? ($r['url'] ?? $this->t('Untitled'));
            $url = $r['url'] ?? ($r['link'] ?? NULL);
            $snippet = $r['content'] ?? ($r['snippet'] ?? '');

            $item_render = [];
            if (!empty($url)) {
              try {
                $link_url = Url::fromUri($url);
                $item_render[] = [
                  '#type' => 'link',
                  '#title' => Html::escape($title),
                  '#url' => $link_url,
                ];
              }
              catch (\Exception $e) {
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
      }
    }

    // Ensure the block isn't cached.
    $form['#cache'] = ['max-age' => 0];

    return $form;
  }

  /**
   * Sanitizes and validates the search term.
   *
   * @param string $term
   *   The raw search term from the query parameter.
   *
   * @return string
   *   The sanitized search term, or empty string if invalid.
   */
  protected function sanitizeSearchTerm(string $term): string {
    // Trim whitespace.
    $term = trim($term);

    // Remove any null bytes.
    $term = str_replace("\0", '', $term);

    // Limit length to prevent abuse.
    $term = substr($term, 0, 255);

    // Only allow alphanumeric, spaces, and common search characters.
    if (!preg_match('/^[a-zA-Z0-9\s\-\.\+]+$/u', $term)) {
      return '';
    }

    return $term;
  }

}
