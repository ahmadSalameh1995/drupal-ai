<?php

namespace Drupal\searxng\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Simple client for querying a SearXNG instance.
 *
 * Reads configuration from the `searxng.settings` config object (endpoint_url,
 * api_key, timeout, categories, language, format, safesearch) and performs a
 * GET request to the configured endpoint.
 */
class SearxngClient {

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The active configuration object for searxng.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Logger channel for searxng.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->config = $config_factory->get('searxng.settings');
    $this->logger = $logger_factory->get('searxng');
  }

  /**
   * Perform a search against the configured SearXNG endpoint.
   *
   * @param string $q
   *   The search query.
   * @param array<mixed> $options
   *   Optional additional query parameters to pass to SearXNG (e.g. engines,
   *   categories). These will be merged with configured defaults and required
   *   parameters. Per-call $options override configured defaults.
   *
   * @return array<mixed>
   *   Parsed JSON response as an associative array. Returns an empty array on
   *   error. For successful responses the typical SearXNG JSON includes a
   *   `results` key with individual results — this method will return that
   *   `results` array when present, otherwise the full decoded payload.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function search(string $q, array $options = []): array {
    $endpoint = (string) $this->config->get('endpoint_url');
    if (empty($endpoint)) {
      $this->logger->error('Searxng endpoint is not configured.');
      return [];
    }

    $timeout = (int) ($this->config->get('timeout') ?? 30);
    $api_key = (string) $this->config->get('api_key');

    // Load configured defaults and allow per-call options to override them.
    $defaultCategories = trim((string) $this->config->get('categories'));
    $defaultLanguage = trim((string) $this->config->get('language'));
    $defaultSafesearch = $this->config->get('safesearch');

    $defaultParams = [];
    if ($defaultCategories !== '') {
      $defaultParams['categories'] = $defaultCategories;
    }
    if ($defaultLanguage !== '') {
      $defaultParams['language'] = $defaultLanguage;
    }
    // Always set format default (can be overridden by $options).
    $defaultParams['format'] = 'json';
    if ($defaultSafesearch !== NULL && $defaultSafesearch !== '') {
      $defaultParams['safesearch'] = (int) $defaultSafesearch;
    }

    // Merge defaults, then options (options override defaults).
    $params = array_merge($defaultParams, $options);

    // Ensure the query parameter is set.
    $params['q'] = $q;
    $params['format'] = 'json';
    $params['engines'] = 'duckduckgo';

    // Normalize categories: if provided as array, join with commas.
    if (isset($params['categories']) && is_array($params['categories'])) {
      $params['categories'] = implode(',', $params['categories']);
    }

    // Ensure safesearch is an integer if present.
    if (isset($params['safesearch'])) {
      $params['safesearch'] = (int) $params['safesearch'];
    }

    try {
      $headers = [];
      if (!empty($api_key)) {
        // Many SearXNG instances expect a token header; adapt if your instance
        // requires a different header (for example X-API-Key). This uses
        // Authorization: Bearer <token> by default.
        $headers['Authorization'] = 'Bearer ' . $api_key;
      }

      try {
        $response = $this->httpClient->request('GET', $endpoint, [
          'query' => $params,
          'timeout' => $timeout,
          'headers' => $headers,
        ]);
      }
      catch (\Exception $e) {
        $this->logger->error('Searxng cannot be reached: @error', ['@error' => $e->getMessage()]);
        return [];
      }

      $status = $response->getStatusCode();
      $body = (string) $response->getBody();

      if ($status >= 200 && $status < 300) {
        $data = json_decode($body, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $this->logger->error('Searxng returned invalid JSON: @error', ['@error' => json_last_error_msg()]);
          return [];
        }
        return $data['results'] ?? $data;
      }

      $this->logger->warning('Searxng returned HTTP @status: @body',
        ['@status' => $status, '@body' => substr($body, 0, 200)]);
      return [];
    }
    catch (\Exception $e) {
      $this->logger->error('Searxng request failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
