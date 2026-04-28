<?php

declare(strict_types=1);

namespace Drupal\searxng_ai_agent\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\searxng\Service\SearxngClient;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the SearXNG tool.
 */
#[Tool(
  id: 'ai_agent:searxng',
  label: new TranslatableMarkup('Searxng agent'),
  description: new TranslatableMarkup('Search the web using searxng engine.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'search_term' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Search term"),
      description: new TranslatableMarkup("The search query used to perform a web search."),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'search_results' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Search results."),
      description: "A list of results found by searxng.",
    ),
  ]
)]
class SearxngTool extends ToolBase implements ContainerFactoryPluginInterface {

  /**
   * The SearXNG client service.
   *
   * @var \Drupal\searxng\Service\SearxngClient
   */
  protected SearxngClient $client;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The instantiated plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, mixed $plugin_definition): static {
    /** @var \Drupal\Core\Session\AccountInterface $current_user */
    $current_user = $container->get('current_user');

    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $current_user,
    );

    /** @var \Drupal\searxng\Service\SearxngClient $client */
    $client = $container->get('searxng.client');
    $instance->client = $client;

    return $instance;
  }

  /**
   * Executes the tool.
   *
   * @param array<string, mixed> $values
   *   The input values.
   */
  protected function doExecute(array $values): ExecutableResult {
    [
      'search_term' => $search_term,
    ] = $values;

    if (empty($search_term)) {
      return new ExecutableResult(FALSE, $this->t("Please provide a search term"));
    }

    // Call the SearXNG client and fetch results.
    $results = $this->client->search($search_term);
    if ($results) {
      // Shrink the results array to provide only the important data.
      $results = array_map(function ($result) {
        return [
          'title' => $result['title'] ?? '',
          'url' => $result['url'] ?? '',
          'content' => $result['content'] ?? '',
        ];
      }, $results);
      return new ExecutableResult(TRUE, $this->t("Found @resultsCount results for @searchTerm : @results", [
        '@resultsCount' => count($results),
        '@searchTerm' => $search_term,
        '@results' => Yaml::dump($results, 2, 10),
      ]
      ), [
        'search_results' => $results,
      ]);
    }

    return new ExecutableResult(FALSE, $this->t("Could not find any results based on your search"));

  }

  /**
   * Checks access.
   *
   * @param array<string, mixed> $values
   *   The input values.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param bool $return_as_object
   *   Whether to return as object.
   *
   * @return bool|AccessResultInterface
   *   The access result.
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // If no account is provided, use the current user.
    $account = $this->currentUser;
    $access = AccessResult::allowedIfHasPermissions($account, ['access content']);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
