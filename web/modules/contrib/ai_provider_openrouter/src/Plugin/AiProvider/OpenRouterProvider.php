<?php

declare(strict_types=1);

namespace Drupal\ai_provider_openrouter\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_openrouter\Service\OpenRouterClient;
use Drupal\ai_provider_openrouter\OperationType\Chat\OpenRouterStreamedChatMessageIterator;
use Drupal\Component\Serialization\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Plugin implementation of the 'openrouter' AI provider.
 */
#[AiProvider(
  id: 'openrouter',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('OpenRouter'),
)]
class OpenRouterProvider extends AiProviderClientBase implements ChatInterface, EmbeddingsInterface, TextToImageInterface, ContainerFactoryPluginInterface {

  use ChatTrait;

  /**
   * The OpenRouter API client service.
   *
   * @var \Drupal\ai_provider_openrouter\Service\OpenRouterClient
   */
  protected OpenRouterClient $client;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array<string, mixed> $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('ai_provider_openrouter.client');
    $instance->logger = $container->get('logger.factory')->get('ai_provider_openrouter');
    $instance->requestStack = $container->get('request_stack');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, \Drupal\ai\Enum\AiProviderCapability>
   *   A list of supported capabilities for this provider.
   */
  public function getSupportedCapabilities(): array {
    return [
      AiProviderCapability::StreamChatOutput,
    ];
  }

  /**
   * Handles a chat request.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput|array<int, array<string, mixed>>|string $input
   *   The chat input.
   * @param string $model_id
   *   The model identifier to use.
   * @param array<string, mixed> $tags
   *   Optional tags.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output.
   */
  public function chat(ChatInput|array|string $input, string $model_id, array $tags = []): ChatOutput {
    try {
      // Provider-side compatibility: if DeepChat posts stream=1 and assistant
      // is NOT agent-based, ensure streaming is enabled. This mirrors OpenAI
      // behavior where the runner sets streamedOutput(TRUE), but guards
      // against payload type mismatches upstream.
      try {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
          // Prefer attributes set by our event subscriber to avoid re-reading
          // the request body.
          $attr_stream_flag = (bool) $request->attributes->get('ai_deepchat_stream_flag', FALSE);
          $assistant_id = $request->attributes->get('ai_deepchat_assistant_id');

          $should_stream = FALSE;
          $force_stream = FALSE;
          $agent_based = FALSE;

          if ($attr_stream_flag) {
            $should_stream = TRUE;
          }
          else {
            // Fallback: parse JSON body if attributes were not set.
            $raw = $request->getContent();
            if ($raw !== '') {
              $data = Json::decode($raw) ?? [];
              $stream_flag = $data['stream'] ?? NULL;
              if ($stream_flag === 1 || $stream_flag === '1' || $stream_flag === TRUE) {
                $should_stream = TRUE;
              }
              $assistant_id = $assistant_id ?: ($data['assistant_id'] ?? NULL);
            }
          }

          // If we can identify an assistant, disable streaming for agent-based
          // assistants.
          if (!empty($assistant_id)) {
            try {
              $assistant = $this->entityTypeManager->getStorage('ai_assistant')->load($assistant_id);
              if ($assistant) {
                /** @var array<string, mixed> $assistantData */
                $assistantData = $assistant->toArray();
                $raw = $assistantData['ai_agent'] ?? NULL;
                $agent_based = !empty($raw);
              }
            }
            catch (\Throwable $e) {
              // Ignore; keep defaults.
            }
          }

          if ($should_stream && !$agent_based) {
            $force_stream = TRUE;
            $this->logger->debug('OpenRouterProvider: Auto-enabled streaming (assistant_id=@aid, agent_based=@agent).', [
              '@aid' => (string) $assistant_id,
              '@agent' => 'false',
            ]);
          }
          if ($should_stream && $agent_based) {
            $this->logger->debug('OpenRouterProvider: Streaming requested but disabled due to agent-based assistant (assistant_id=@aid).', [
              '@aid' => (string) $assistant_id,
            ]);
          }
        }
      }
      catch (\Throwable $e) {
        // Non-fatal.
      }

      // Normalize the input if needed.
      $chat_input = $input;
      if ($input instanceof ChatInput) {
        $chat_input = [];
        // Add a system role if wanted.
        if ($this->chatSystemRole) {
          // If it's o1 or o3, we add it as a user message (similar to the
          // OpenAI provider).
          if (preg_match('/(o1|o3)/i', $model_id)) {
            $chat_input[] = [
              'role' => 'user',
              'content' => $this->chatSystemRole,
            ];
          }
          else {
            $chat_input[] = [
              'role' => 'system',
              'content' => $this->chatSystemRole,
            ];
          }
        }

        /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
        foreach ($input->getMessages() as $message) {
          $content = [
            [
              'type' => 'text',
              'text' => $message->getText(),
            ],
          ];

          // Support for files (images, PDFs, videos, etc.) if the model supports it.
          if (method_exists($message, 'getFiles') && count($message->getFiles())) {
            foreach ($message->getFiles() as $file) {
              if ($file instanceof \Drupal\ai\OperationType\GenericType\ImageFile) {
                $content[] = [
                  'type' => 'image_url',
                  'image_url' => [
                    'url' => $file->getAsBase64EncodedString(),
                  ],
                ];
              }
              elseif ($file instanceof \Drupal\ai\OperationType\GenericType\VideoFile) {
                // OpenRouter uses video_url type for video inputs.
                $content[] = [
                  'type' => 'video_url',
                  'video_url' => [
                    'url' => $file->getAsBase64EncodedString(),
                  ],
                ];
              }
              elseif ($file->getMimeType() === 'application/pdf') {
                $content[] = [
                  'type' => 'file',
                  'file' => [
                    'filename' => $file->getFilename(),
                    'file_data' => $file->getAsBase64EncodedString(),
                  ],
                ];
              }
            }
          }
          // Fallback to getImages() for backward compatibility.
          elseif (count($message->getImages())) {
            foreach ($message->getImages() as $image) {
              $content[] = [
                'type' => 'image_url',
                'image_url' => [
                  'url' => $image->getAsBase64EncodedString(),
                ],
              ];
            }
          }

          $new_message = [
            'role' => $message->getRole(),
            'content' => $content,
          ];

          // If it's a tools response.
          if ($message->getToolsId()) {
            $new_message['tool_call_id'] = $message->getToolsId();
          }

          // If we want the results from some older tools call.
          if ($message->getTools()) {
            $new_message['tool_calls'] = $message->getRenderedTools();
          }

          $chat_input[] = $new_message;
        }
      }
      elseif (is_array($input)) {
        $chat_input = $input;
      }
      else {
        // Normalize plain string input to a messages array with typed content.
        $chat_input = [
          [
            'role' => 'user',
            'content' => [
              [
                'type' => 'text',
                'text' => (string) $input,
              ],
            ],
          ],
        ];
      }

      $payload = [
        'model' => $model_id,
        'messages' => $chat_input,
      ] + $this->configuration;

      // If we want to add tools to the input.
      if ($input instanceof ChatInput && $input->getChatTools()) {
        $payload['tools'] = $input->getChatTools()->renderToolsArray();
        foreach ($payload['tools'] as $key => $tool) {
          $payload['tools'][$key]['function']['strict'] = FALSE;
        }
      }

      // Check for structured json schemas.
      if ($input instanceof ChatInput && $input->getChatStructuredJsonSchema()) {
        $payload['response_format'] = [
          'type' => 'json_schema',
          'json_schema' => $input->getChatStructuredJsonSchema(),
        ];
      }

      // If streamed output is requested, return an iterator-based ChatOutput.
      // Check both the new way (ChatInput->isStreamedOutput()) and the old way ($this->streamed)
      // for backward compatibility with AI module 1.2.x and 1.3.x.
      $stream_from_input = ($input instanceof ChatInput && $input->isStreamedOutput());
      $enable_streaming = $stream_from_input || ($this->streamed === TRUE) || (!empty($force_stream));
      if ($enable_streaming) {
        // Ensure usage metrics are included in streamed chunks when supported.
        $payload['stream_options']['include_usage'] = TRUE;
        $stream = $this->client->chatCompletionStream($payload);
        $iterator = new OpenRouterStreamedChatMessageIterator($stream);
        return new ChatOutput($iterator, [], []);
      }

      $this->logger->debug(
        'OpenRouterProvider: streaming disabled for model @model. Returning non-stream response.',
        ['@model' => $model_id]
      );
      $response = $this->client->chatCompletion($payload);

      // Process tools if present in the response.
      $tools = [];
      if (!empty($response['choices'][0]['message']['tool_calls']) && $input instanceof ChatInput && $input->getChatTools()) {
        $toolsInput = $input->getChatTools();
        foreach ($response['choices'][0]['message']['tool_calls'] as $tool) {
          $arguments = Json::decode($tool['function']['arguments']);
          /** @var \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface $functionDef */
          $functionDef = $toolsInput->getFunctionByName($tool['function']['name']);
          $tools[] = new ToolsFunctionOutput($functionDef, $tool['id'], $arguments);
        }
      }

      $message = new ChatMessage(
        $response['choices'][0]['message']['role'],
        $response['choices'][0]['message']['content'] ?? "",
        []
      );

      if (!empty($tools)) {
        // @phpstan-ignore-next-line ToolsFunctionOutput implements a compatible API.
        $message->setTools($tools);
      }

      $output = new ChatOutput($message, $response, []);
      // Propagate token usage when available on non-stream responses.
      if (!empty($response['usage']) && is_array($response['usage'])) {
        $usage = $response['usage'];
        $output->setTokenUsage(new TokenUsageDto(
          total: (int) ($usage['total_tokens'] ?? 0),
          input: (int) ($usage['prompt_tokens'] ?? 0),
          output: (int) ($usage['completion_tokens'] ?? 0),
          reasoning: (int) ($usage['completion_tokens_details']['reasoning_tokens'] ?? 0),
          cached: (int) ($usage['completion_tokens_details']['cached_tokens'] ?? 0),
        ));
      }
      return $output;
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      // Handle unsafe content errors.
      if (strpos($e->getMessage(), 'content policy violation') !== FALSE) {
        throw new AiUnsafePromptException($e->getMessage());
      }
      // Handle general API errors.
      throw new AiResponseErrorException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\ai\OperationType\Embeddings\EmbeddingsInput|string|array<int, string> $input
   *   The embeddings input, string prompt, or array of string prompts for batch processing.
   * @param string $model_id
   *   The model identifier to use.
   * @param array<string, mixed> $tags
   *   Optional tags for the request.
   */
  public function embeddings(EmbeddingsInput|string|array $input, string $model_id, array $tags = []): EmbeddingsOutput {
    try {
      // Normalize input to string or array of strings.
      if ($input instanceof EmbeddingsInput) {
        $prompt = $input->getPrompt();
      }
      elseif (is_array($input)) {
        // Batch processing: validate all items are strings.
        $prompt = array_map(static fn($item) => (string) $item, $input);
      }
      else {
        $prompt = (string) $input;
      }

      $result = $this->client->embeddings([
        'model' => $model_id,
        'input' => $prompt,
      ]);

      // For batch requests, return all embeddings; for single requests, return the first.
      $normalized = [];
      if (is_array($prompt)) {
        // Batch processing: return array of embeddings.
        foreach ($result['data'] as $item) {
          if (!empty($item['embedding']) && is_array($item['embedding'])) {
            /** @var array<int, float|int> $vec */
            $vec = $item['embedding'];
            $normalized[] = array_map(static fn($v) => (float) $v, $vec);
          }
        }
      }
      else {
        // Single embedding: return as before for backward compatibility.
        if (!empty($result['data'][0]['embedding']) && is_array($result['data'][0]['embedding'])) {
          /** @var array<int, float|int> $vec */
          $vec = $result['data'][0]['embedding'];
          $normalized = array_map(static fn($v) => (float) $v, $vec);
        }
      }

      $metadata = $result['usage'] ?? [];

      return new EmbeddingsOutput($normalized, $result, $metadata);
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      // Handle general API errors.
      throw new AiResponseErrorException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\ai\OperationType\TextToImage\TextToImageInput|string $input
   *   The text prompt for image generation.
   * @param string $model_id
   *   The model identifier to use.
   * @param array<string, mixed> $tags
   *   Optional tags for the request.
   */
  public function textToImage(TextToImageInput|string $input, string $model_id, array $tags = []): TextToImageOutput {
    try {
      // Normalize the input.
      $prompt = $input instanceof TextToImageInput ? $input->getText() : (string) $input;

      // OpenRouter uses chat completions API with modalities for image generation.
      $payload = [
        'model' => $model_id,
        'messages' => [
          [
            'role' => 'user',
            'content' => $prompt,
          ],
        ],
        'modalities' => ['image', 'text'],
      ] + $this->configuration;

      // Add image_config if present in configuration for Gemini models.
      if (!empty($this->configuration['image_config'])) {
        $payload['image_config'] = $this->configuration['image_config'];
      }

      $response = $this->client->chatCompletion($payload);

      $images = [];
      // Extract images from the assistant message.
      if (!empty($response['choices'][0]['message']['images'])) {
        foreach ($response['choices'][0]['message']['images'] as $imageData) {
          if (!empty($imageData['image_url']['url'])) {
            $url = $imageData['image_url']['url'];
            // Handle base64 data URLs.
            if (str_starts_with($url, 'data:image/')) {
              // Extract mime type and base64 data.
              preg_match('/^data:(image\/[^;]+);base64,(.+)$/', $url, $matches);
              if (count($matches) === 3) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                $binaryData = base64_decode($base64Data);
                $extension = match($mimeType) {
                  'image/png' => 'png',
                  'image/jpeg' => 'jpg',
                  'image/webp' => 'webp',
                  default => 'png',
                };
                $images[] = new ImageFile($binaryData, $mimeType, 'openrouter-generated.' . $extension);
              }
            }
          }
        }
      }

      if (empty($images)) {
        throw new AiResponseErrorException('No images were generated in the response.');
      }

      return new TextToImageOutput($images, $response, []);
    }
    catch (\Exception $e) {
      // Handle rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Handle quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      // Handle general API errors.
      throw new AiResponseErrorException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    // Return the module's config object via injected config.factory.
    return $this->configFactory->get('ai_provider_openrouter.settings');
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The API definition.
   */
  public function getApiDefinition(): array {
    // Return an empty array or define the API schema if needed.
    /** @var array<string, mixed> $def */
    $def = [];
    return $def;
  }

  /**
   * {@inheritdoc}
   *
   * @param string $model_id
   * @param string|null $operation_type
   *   The operation type.
   * @param array<string, mixed> $capabilities
   *   Capabilities to consider.
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    $config = $this->getConfig();
    $api_key = $config->get('api_key');
    $usable = !empty($api_key);
    return $usable;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    // Support chat, embeddings, and text-to-image.
    return ['chat', 'embeddings', 'text_to_image'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Stub: not used in this provider yet.
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The available configuration.
   */
  public function getAvailableConfiguration(string $operation_type, string $model_id): array {
    // Stub: no advanced config yet.
    /** @var array<string, mixed> $cfg */
    $cfg = [];
    return $cfg;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The default configuration values.
   */
  public function getDefaultConfigurationValues(string $operation_type, string $model_id): array {
    // Stub: no advanced config yet.
    /** @var array<string, mixed> $cfg */
    $cfg = [];
    return $cfg;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // Add reasoning_effort parameter for reasoning models (o1, o3, gpt-5).
    if ($this->isReasoningModel($model_id)) {
      // OpenRouter supports reasoning.effort parameter for o1/o3/gpt-5 models.
      // See https://openrouter.ai/docs/guides/best-practices/reasoning-tokens
      $generalConfig['reasoning_effort'] = [
        'type' => 'select',
        'label' => 'Reasoning Effort',
        'description' => 'Constrains effort on reasoning for reasoning models. Higher effort uses more tokens for internal reasoning.',
        'default' => 'medium',
        'constraints' => [
          'options' => [
            'none' => 'None (disable reasoning)',
            'minimal' => 'Minimal (~10% of tokens)',
            'low' => 'Low (~20% of tokens)',
            'medium' => 'Medium (~50% of tokens)',
            'high' => 'High (~80% of tokens)',
            'xhigh' => 'Extra High (~95% of tokens)',
          ],
        ],
      ];
    }
    
    return $generalConfig;
  }

  /**
   * Check if a model is a reasoning model (o1, o3, gpt-5 series).
   *
   * @param string $model_id
   *   The model ID to check.
   *
   * @return bool
   *   TRUE if the model is a reasoning model, FALSE otherwise.
   */
  protected function isReasoningModel(string $model_id): bool {
    $id = strtolower($model_id);
    
    // OpenAI reasoning models available through OpenRouter.
    if (str_starts_with($id, 'openai/gpt-5')) {
      return TRUE;
    }
    if (str_starts_with($id, 'openai/o1')) {
      return TRUE;
    }
    if (str_starts_with($id, 'openai/o3')) {
      return TRUE;
    }
    
    // Grok reasoning models.
    if (str_contains($id, 'grok') && str_contains($id, 'reasoning')) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputExample(string $operation_type, string $model_id): mixed {
    // Stub: no example provided.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationExample(string $operation_type, string $model_id): mixed {
    // Stub: no example provided.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxInputTokens(string $model_id): int {
    // Fetch model metadata from OpenRouter to get accurate context window.
    $models = $this->client->listModels();
    if (isset($models[$model_id]['context_length'])) {
      return (int) $models[$model_id]['context_length'];
    }
    // Fallback for unknown models - conservative default.
    return 8192;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxOutputTokens(string $model_id): int {
    // Fetch model metadata from OpenRouter.
    $models = $this->client->listModels();
    
    // Check if OpenRouter provides max_completion_tokens.
    if (isset($models[$model_id]['top_provider']['max_completion_tokens'])) {
      return (int) $models[$model_id]['top_provider']['max_completion_tokens'];
    }
    
    // Fallback: Most models reserve 10-25% of context for output.
    // Use 25% of input context as a conservative estimate.
    $input_tokens = $this->getMaxInputTokens($model_id);
    return (int) ($input_tokens * 0.25);
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput(string $model_id = ''): int {
    // OpenRouter models vary; return a conservative default.
    return 8192;
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    // Return the vector dimension size for known embedding models.
    // These dimensions are extracted from OpenRouter's model descriptions.
    return match($model_id) {
      // OpenAI embeddings.
      'openai/text-embedding-ada-002', 'openai/text-embedding-3-small' => 1536,
      'openai/text-embedding-3-large' => 3072,
      
      // Google embeddings.
      'google/gemini-embedding-001' => 768,
      
      // Mistral embeddings.
      'mistralai/mistral-embed-2312', 'mistralai/codestral-embed-2505' => 1024,
      
      // BAAI embeddings.
      'baai/bge-large-en-v1.5', 'baai/bge-m3' => 1024,
      'baai/bge-base-en-v1.5' => 768,
      
      // Intfloat embeddings.
      'intfloat/e5-large-v2', 'intfloat/multilingual-e5-large' => 1024,
      'intfloat/e5-base-v2' => 768,
      
      // Thenlper embeddings.
      'thenlper/gte-large' => 1024,
      'thenlper/gte-base' => 768,
      
      // Sentence Transformers embeddings.
      'sentence-transformers/paraphrase-minilm-l6-v2',
      'sentence-transformers/all-minilm-l12-v2',
      'sentence-transformers/all-minilm-l6-v2' => 384,
      'sentence-transformers/multi-qa-mpnet-base-dot-v1',
      'sentence-transformers/all-mpnet-base-v2' => 768,
      
      // Qwen embeddings.
      'qwen/qwen3-embedding-8b' => 1024,
      
      // Return 0 for unknown models (caller should handle this).
      default => 0,
    };
  }

  /**
   * {@inheritdoc}
   * OpenRouter provides access to a wide range of models from different
   * providers. To improve usability, this implementation filters available
   * models based on user configuration in the OpenRouter settings form.
   *
   * This approach allows site administrators to curate a focused list of
   * models they want to use, preventing dropdown menus throughout the site
   * from being overwhelmed with hundreds of options.
   *
   * The filtering is based on the 'enabled_models' configuration, which
   * stores the IDs of models that the administrator has explicitly enabled
   * in the OpenRouter configuration form.
   *
   * @param string|null $operation_type
   *   The operation type to filter models by (e.g., 'chat', 'embeddings').
   * @param array<string, mixed> $capabilities
   *   Optional array of capabilities to filter models by.
   *
   * @return array<string, string>
   *   An array of model IDs and labels, filtered by user configuration.
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $models = $this->client->listModels();
    $result = [];
    // Get enabled models from config, if any.
    $config = $this->configFactory->get('ai_provider_openrouter.settings');
    $enabled = $config->get('enabled_models') ?? [];
    $enabled = array_filter($enabled);

    $this->logger->debug(
      'OpenRouter getConfiguredModels: Found @count total models, @enabled enabled, operation_type=@op',
      [
        '@count' => count($models),
        '@enabled' => count($enabled),
        '@op' => $operation_type ?? 'NULL',
      ]
    );

    foreach ($models as $id => $model) {
      $label = $model['name'] ?? $model['id'];
      
      // Filter by operation type if specified.
      if ($operation_type === 'embeddings') {
        // Only include models marked as embedding models.
        if (empty($model['_is_embedding_model'])) {
          continue;
        }
      }
      elseif ($operation_type === 'chat' || $operation_type === 'text_to_image') {
        // Exclude embedding-only models from chat/image generation.
        if (!empty($model['_is_embedding_model'])) {
          continue;
        }
      }
      
      // If enabled models are set, only include those.
      if (empty($enabled) || in_array($id, $enabled, TRUE)) {
        $result[$id] = $label;
      }
    }
    return $result;
  }

}
