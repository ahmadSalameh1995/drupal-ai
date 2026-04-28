<?php

declare(strict_types=1);

namespace Drupal\ai_provider_openrouter\Service;

use OpenAI\Factory;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for communicating with the OpenRouter API.
 *
 * OpenRouter uses an API that is compatible with the OpenAI API format,
 * which allows us to leverage the OpenAI PHP client library for most
 * operations.
 * This approach reduces code duplication and maintenance overhead while
 * providing a robust implementation for API communication.
 *
 * The only exception is the models endpoint, which has slight differences
 * in the response format that we handle with a custom implementation.
 */
class OpenRouterClient {
  /**
   * The PSR-18 HTTP client used for direct API requests.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The OpenAI PHP client instance.
   *
   * @var \OpenAI\Client
   */
  protected $client;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * OpenRouterClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel for this module.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    KeyRepositoryInterface $key_repository,
    LoggerInterface $logger,
    ClientInterface $http_client,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->keyRepository = $key_repository;
    $this->logger = $logger;

    // Initialize the client.
    $this->initializeClient();
  }

  /**
   * Perform a streamed chat completion request.
   *
   * @param array<string, mixed> $options
   *   The options for the chat completion.
   *
   * @return \Traversable
   *   A traversable stream of response chunks.
   */
  public function chatCompletionStream(array $options): \Traversable {
    try {
      // Some clients honor 'stream' flag; include it for clarity.
      $options['stream'] = TRUE;
      return $this->client->chat()->createStreamed($options);
    }
    catch (\Throwable $e) {
      $this->logger->error('OpenRouter streamed chat error: @msg', ['@msg' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Initialize the OpenAI client with OpenRouter configuration.
   */
  protected function initializeClient(): void {
    $config = $this->configFactory->get('ai_provider_openrouter.settings');
    $key_name = $config->get('api_key');
    $base_url = $config->get('base_url') ?: 'https://openrouter.ai/api/v1';
    $api_key = '';
    if ($key_name) {
      $key_entity = $this->keyRepository->getKey($key_name);
      $api_key = $key_entity ? (string) $key_entity->getKeyValue() : '';
    }

    $this->client = (new Factory())
      ->withApiKey($api_key)
      ->withBaseUri($base_url)
      ->make();
  }

  /**
   * Perform a chat completion request.
   *
   * @param array<string, mixed> $options
   *   The options for the chat completion.
   *
   * @return array<string, mixed>
   *   The response from the API.
   */
  public function chatCompletion(array $options): array {
    try {
      // Use the OpenAI PHP client consistently for chat completions.
      return $this->client->chat()->create($options)->toArray();
    }
    catch (\Throwable $e) {
      $this->logger->error('OpenRouter chat completion error: @msg', ['@msg' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Perform an embeddings request.
   *
   * @param array<string, mixed> $options
   *   The options for the embeddings request.
   *
   * @return array<string, mixed>
   *   The response from the API.
   */
  public function embeddings(array $options): array {
    try {
      return $this->client->embeddings()->create($options)->toArray();
    }
    catch (\Throwable $e) {
      $this->logger->error('OpenRouter embeddings error: @msg', ['@msg' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * List available models from OpenRouter.
   *
   * OpenRouter has separate endpoints for chat/image models and embedding models.
   * This method fetches both and merges them.
   *
   * @return array<string, array<string, mixed>>
   *   Array keyed by model ID with model metadata arrays as values.
   */
  public function listModels(): array {
    try {
      $config = $this->configFactory->get('ai_provider_openrouter.settings');
      $key_name = $config->get('api_key');
      $base_url = $config->get('base_url') ?: 'https://openrouter.ai/api/v1';
      $api_key = '';
      if ($key_name) {
        $key_entity = $this->keyRepository->getKey($key_name);
        $api_key = $key_entity ? (string) $key_entity->getKeyValue() : '';
      }

      $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Accept' => 'application/json',
        'HTTP-Referer' => 'https://drupal.org',
        'X-Title' => 'Drupal AI Module',
      ];

      $models = [];

      // Fetch chat/image generation models from /models endpoint.
      $response = $this->httpClient->request('GET', rtrim($base_url, '/') . '/models', [
        'headers' => $headers,
        'timeout' => 15,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($data['data'])) {
        foreach ($data['data'] as $model) {
          $models[$model['id']] = $model;
        }
      }

      // Fetch embedding models from /embeddings/models endpoint.
      // OpenRouter maintains a separate list of embedding-specific models.
      try {
        $embeddingsResponse = $this->httpClient->request('GET', rtrim($base_url, '/') . '/embeddings/models', [
          'headers' => $headers,
          'timeout' => 15,
        ]);

        $embeddingsData = json_decode($embeddingsResponse->getBody()->getContents(), TRUE);

        if (!empty($embeddingsData['data'])) {
          foreach ($embeddingsData['data'] as $model) {
            // Mark these as embedding models for filtering.
            $model['_is_embedding_model'] = TRUE;
            $models[$model['id']] = $model;
          }
        }
      }
      catch (\Throwable $embeddingsError) {
        // Log but don't fail if embeddings endpoint fails.
        $this->logger->warning('Failed to fetch embedding models from OpenRouter: @msg', ['@msg' => $embeddingsError->getMessage()]);
      }

      return $models;
    }
    catch (\Throwable $fallbackError) {
      $this->logger->error('Failed to fetch models from OpenRouter: @msg', ['@msg' => $fallbackError->getMessage()]);
      return [];
    }
  }

}
