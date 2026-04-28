<?php

declare(strict_types=1);

namespace Drupal\ai_provider_openrouter\Form;

// Use Drupal\ai\Service\AiProviderFormHelper;.
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_provider_openrouter\Service\OpenRouterClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenRouter API access.
 *
 * @internal
 *   This class is an internal part of the module and may be removed or changed
 *   at any time without warning.
 */
class OpenRouterConfigForm extends ConfigFormBase {

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The OpenRouter API client.
   *
   * @var \Drupal\ai_provider_openrouter\Service\OpenRouterClient
   */
  protected OpenRouterClient $client;

  /**
   * {@inheritdoc}
   *
   * @return static
   *   The created instance.
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->keyRepository = $container->get('key.repository');
    $instance->logger = $container->get('logger.factory')->get('ai_provider_openrouter');
    $instance->client = $container->get('ai_provider_openrouter.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleName(): string {
    return 'ai_provider_openrouter';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openrouter_provider_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   Config names editable by this form.
   */
  protected function getEditableConfigNames(): array {
    return [
      'ai_provider_openrouter.settings',
      'ai.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The built form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ai_provider_openrouter.settings');

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('OpenRouter API key'),
      '#default_value' => $config->get('api_key'),
      '#key_filters' => ['type' => 'authentication'],
      '#description' => $this->t('Select the key containing your OpenRouter API key. <a href=":url" target="_blank">Get an API key</a>.', [
        ':url' => 'https://openrouter.ai/keys',
      ]),
      '#required' => TRUE,
      '#weight' => 2,
    ];

    $form['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenRouter API Base URL'),
      '#default_value' => $config->get('base_url') ?: 'https://openrouter.ai/api/v1',
      '#description' => $this->t('The base URL for the OpenRouter API. Only change this if you are using a custom endpoint.'),
      '#required' => TRUE,
      '#weight' => 3,
    ];

    // Add streaming support option.
    $form['streaming_container'] = [
      '#type' => 'container',
      '#weight' => 4,
    ];

    $form['streaming_container']['streaming'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable streaming'),
      '#description' => $this->t('Enable streaming responses for supported models. This acts as a default and can be overridden by callers.'),
      '#default_value' => (bool) ($config->get('streaming') ?? FALSE),
    ];

    // Add default provider option.
    $form['default_provider'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set as default provider'),
      '#description' => $this->t('Make OpenRouter the default AI provider for new operations.'),
      '#default_value' => $config->get('default_provider') ?: FALSE,
      '#weight' => 5,
    ];

    // Model selection section.
    $form['model_selection_title'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Model Selection') . '</h3>',
      '#weight' => 10,
    ];

    $form['model_selection_description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="description">' .
      '<p>' . $this->t('OpenRouter provides access to 300+ models from various providers. By default, all available models will be listed in the Model selection dropdown. Use the toggles below to enable only the models you need. This helps keep your model selection dropdowns clean and focused on just the models you want to use.') . '</p>' .
      '</div>',
      '#weight' => 11,
    ];

    // Fetch models from OpenRouter API.
    $models = $this->getOpenRouterModels();

    // If we have models, organize them by provider and type.
    if (!empty($models)) {
      // Get currently enabled models.
      $enabled_models = $config->get('enabled_models') ?: [];

      // Separate embedding models from chat/image models.
      $embedding_models = [];
      $chat_models = [];
      
      foreach ($models as $model_id => $model) {
        if (!empty($model['is_embedding'])) {
          $embedding_models[$model_id] = $model;
        }
        else {
          $chat_models[$model_id] = $model;
        }
      }

      // Organize chat models by provider.
      $providers = [];
      foreach ($chat_models as $model_id => $model) {
        $provider = $model['provider'] ?? 'Unknown';
        $providers[$provider][$model_id] = $model;
      }
      
      // Organize embedding models by provider.
      $embedding_providers = [];
      foreach ($embedding_models as $model_id => $model) {
        $provider = $model['provider'] ?? 'Unknown';
        $embedding_providers[$provider][$model_id] = $model;
      }

      // Create fieldsets for each provider.
      $weight = 20;
      $form['model_selection'] = [
        '#tree' => TRUE,
      ];

      // Add embedding models section first if we have any.
      if (!empty($embedding_providers)) {
        $form['embedding_models_title'] = [
          '#type' => 'markup',
          '#markup' => '<h3>' . $this->t('Embedding Models') . '</h3>',
          '#weight' => $weight,
        ];
        $weight += 5;
        
        $form['embedding_models_description'] = [
          '#type' => 'markup',
          '#markup' => '<div class="description">' .
          '<p>' . $this->t('These models are used for generating embeddings (vector representations of text) for semantic search, RAG, and other AI operations. Enable the embedding models you want to use.') . '</p>' .
          '</div>',
          '#weight' => $weight,
        ];
        $weight += 5;
        
        foreach ($embedding_providers as $provider_name => $provider_models) {
          // Check if any models in this provider are enabled.
          $has_enabled_models = FALSE;
          foreach ($provider_models as $model_id => $model) {
            if (in_array($model_id, $enabled_models)) {
              $has_enabled_models = TRUE;
              break;
            }
          }

          // Add model count to the fieldset title.
          $model_count = count($provider_models);

          $provider_id = 'embedding_' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($provider_name));

          $form[$provider_id . '_fieldset'] = [
            '#type' => 'details',
            '#title' => $this->t('@provider Embeddings (@count models)', [
              '@provider' => $provider_name,
              '@count' => $model_count,
            ]),
            '#open' => $has_enabled_models,
            '#weight' => $weight,
          ];
          $weight += 10;

          foreach ($provider_models as $model_id => $model) {
            $model_name = $model['name'] ?? $model_id;
            $model_description = $model['description'] ?? '';

            // Add context info if available.
            $context_info = [];
            if (!empty($model['context_length'])) {
              $context_info[] = $this->t('Context: @context tokens', ['@context' => number_format($model['context_length'])]);
            }

            // Add pricing info if available.
            if (isset($model['pricing']) && is_array($model['pricing'])) {
              if (isset($model['pricing']['prompt']) && is_numeric($model['pricing']['prompt'])) {
                $price_per_million = $model['pricing']['prompt'] * 1000000;
                $context_info[] = $this->t('$@price/1M tokens', ['@price' => number_format($price_per_million, 4)]);
              }
            }

            $description = $model_description;
            if (!empty($context_info)) {
              $description .= '<br><strong>' . implode(' | ', $context_info) . '</strong>';
            }

            $form[$provider_id . '_fieldset'][$model_id] = [
              '#type' => 'checkbox',
              '#title' => $model_name,
              '#description' => $description,
              '#default_value' => in_array($model_id, $enabled_models),
              '#return_value' => $model_id,
              '#parents' => ['model_selection', $model_id],
            ];
          }
        }
        
        // Add separator.
        $form['chat_models_separator'] = [
          '#type' => 'markup',
          '#markup' => '<hr style="margin: 2em 0;">',
          '#weight' => $weight,
        ];
        $weight += 5;
      }
      
      // Add chat/image models section title.
      $form['chat_models_title'] = [
        '#type' => 'markup',
        '#markup' => '<h3>' . $this->t('Chat & Image Generation Models') . '</h3>',
        '#weight' => $weight,
      ];
      $weight += 5;
      
      $form['chat_models_description'] = [
        '#type' => 'markup',
        '#markup' => '<div class="description">' .
        '<p>' . $this->t('These models are used for chat completions, text generation, and image generation (models with 🖼️ are capable of text-to-image). Enable the models you want to use.') . '</p>' .
        '</div>',
        '#weight' => $weight,
      ];
      $weight += 5;

      foreach ($providers as $provider_name => $provider_models) {
        // Check if any models in this provider are enabled.
        $has_enabled_models = FALSE;
        foreach ($provider_models as $model_id => $model) {
          if (in_array($model_id, $enabled_models)) {
            $has_enabled_models = TRUE;
            break;
          }
        }

        // Add model count to the fieldset title.
        $model_count = count($provider_models);

        $provider_id = preg_replace('/[^a-z0-9_]+/', '_', strtolower($provider_name));

        $form[$provider_id . '_fieldset'] = [
          '#type' => 'details',
          '#title' => $this->t('@provider (@count models)', [
            '@provider' => $provider_name,
            '@count' => $model_count,
          ]),
        // Only open by default if there are enabled models.
          '#open' => $has_enabled_models,
          '#weight' => $weight,
        ];
        $weight += 10;

        foreach ($provider_models as $model_id => $model) {
          $model_name = $model['name'] ?? $model_id;
          $model_description = $model['description'] ?? '';

          // Add image generation icon if model supports it.
          if (!empty($model['supports_image_generation'])) {
            $model_name = '🖼️ ' . $model_name;
          }

          // Add context info if available.
          $context_info = [];
          if (!empty($model['context_length'])) {
            $context_info[] = $this->t('Context: @context tokens', ['@context' => number_format($model['context_length'])]);
          }

          // Add pricing info if available.
          if (isset($model['pricing']) && is_array($model['pricing'])) {
            if (isset($model['pricing']['prompt']) && is_numeric($model['pricing']['prompt'])) {
              // Convert to price per million tokens for better readability.
              $price_per_million = $model['pricing']['prompt'] * 1000000;
              $context_info[] = $this->t('Input: $@price/1M tokens', ['@price' => number_format($price_per_million, 2)]);
            }
            if (isset($model['pricing']['completion']) && is_numeric($model['pricing']['completion'])) {
              // Convert to price per million tokens for better readability.
              $price_per_million = $model['pricing']['completion'] * 1000000;
              $context_info[] = $this->t('Output: $@price/1M tokens', ['@price' => number_format($price_per_million, 2)]);
            }
          }

          $description = $model_description;
          if (!empty($context_info)) {
            $description .= '<br><strong>' . implode(' | ', $context_info) . '</strong>';
          }

          // Add the checkbox directly to the fieldset.
          $form[$provider_id . '_fieldset'][$model_id] = [
            '#type' => 'checkbox',
            '#title' => $model_name,
            '#description' => $description,
            '#default_value' => in_array(
              $model_id,
              $enabled_models,
            ),
            // Store the value in model_selection.
            // Used for processing in submitForm.
            '#return_value' => $model_id,
            '#parents' => [
              'model_selection',
              $model_id,
            ],
          ];
        }
      }
    }
    else {
      $form['no_models'] = [
        '#markup' => $this->t('<p>No models available. Please check your API key and connection.</p>'),
        '#weight' => 20,
      ];
    }

    // Ensure the actions (submit button) appears at the bottom.
    $form['actions']['#weight'] = 1000;

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> &$form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('ai_provider_openrouter.settings');
    $values = $form_state->getValues();

    // Save provider settings.
    $config->set('api_key', $values['api_key']);
    $config->set('base_url', $values['base_url']);
    $config->set('streaming', $values['streaming']);
    $config->set('default_provider', $values['default_provider']);

    // Extract enabled models from the model selection fieldsets.
    $enabled_models = [];
    $model_selections = $values['model_selection'] ?? [];

    if (!empty($model_selections)) {
      foreach ($model_selections as $model_id => $enabled) {
        if ($enabled) {
          $enabled_models[] = $model_id;
        }
      }
    }

    // Log the enabled models for debugging.
    $this->logger->debug('Saving @count enabled models', [
      '@count' => count($enabled_models),
    ]);

    $config->set('enabled_models', $enabled_models);
    $config->save();

    // If set as default provider, update the AI module configuration.
    if ($values['default_provider']) {
      // Set default provider through config instead.
      $ai_config = $this->config('ai.settings');
      $ai_config->set('default_provider', 'openrouter');
      $ai_config->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Fetch models from OpenRouter API.
   *
   * @return array<string, array<string, mixed>>
   *   An array of models keyed by model ID.
   */
  protected function getOpenRouterModels(): array {
    try {
      $models_data = $this->client->listModels();

      // Format the response into a usable structure.
      $formatted_models = [];

      // OpenRouter API returns models directly in the data array.
      if (!empty($models_data)) {
        foreach ($models_data as $model_id => $model) {
          // Extract pricing information if available.
          $pricing = [];
          if (!empty($model['pricing'])) {
            if (isset($model['pricing']['prompt'])) {
              $pricing['prompt'] = (float) $model['pricing']['prompt'];
            }
            if (isset($model['pricing']['completion'])) {
              $pricing['completion'] = (float) $model['pricing']['completion'];
            }
          }

          // Check if model supports image generation (has 'image' in output_modalities).
          $supports_image_gen = FALSE;
          if (!empty($model['architecture']['output_modalities']) && is_array($model['architecture']['output_modalities'])) {
            $supports_image_gen = in_array('image', $model['architecture']['output_modalities']);
          }

          $formatted_models[$model_id] = [
            'name' => $model['name'] ?? $model_id,
            'provider' => $this->extractProviderFromModelId($model_id),
            'description' => $model['description'] ?? '',
            'context_length' => $model['context_length'] ?? NULL,
            'pricing' => $pricing,
            'is_embedding' => !empty($model['_is_embedding_model']),
            'supports_image_generation' => $supports_image_gen,
          ];
        }

        return $formatted_models;
      }

      // Fallback to static list if API response is empty or malformed.
      return [
        'openai/gpt-3.5-turbo' => [
          'name' => 'GPT-3.5 Turbo',
          'provider' => 'OpenAI',
          'description' => 'Fast and efficient language model.',
          'context_length' => 16385,
          'pricing' => [
            'prompt' => 0.50,
            'completion' => 1.50,
          ],
        ],
        'openai/gpt-4' => [
          'name' => 'GPT-4',
          'provider' => 'OpenAI',
          'description' => 'Advanced language model with improved reasoning.',
          'context_length' => 8192,
          'pricing' => [
            'prompt' => 10.00,
            'completion' => 30.00,
          ],
        ],
        'anthropic/claude-2' => [
          'name' => 'Claude 2',
          'provider' => 'Anthropic',
          'description' => 'Anthropic\'s advanced language model.',
          'context_length' => 100000,
          'pricing' => [
            'prompt' => 8.00,
            'completion' => 24.00,
          ],
        ],
        'meta-llama/llama-2-70b-chat' => [
          'name' => 'Llama 2 70B',
          'provider' => 'Meta',
          'description' => 'Meta\'s largest open-source chat model.',
          'context_length' => 4096,
          'pricing' => [
            'prompt' => 0.70,
            'completion' => 0.90,
          ],
        ],
      ];
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not fetch models for selection: @error', ['@error' => $e->getMessage()]);

      // Return a minimal set of models if API call fails.
      return [
        'openai/gpt-3.5-turbo' => [
          'name' => 'GPT-3.5 Turbo',
          'provider' => 'OpenAI',
          'description' => 'Fast and efficient language model.',
          'context_length' => 16385,
          'pricing' => [
            'prompt' => 0.50,
            'completion' => 1.50,
          ],
        ],
        'openai/gpt-4' => [
          'name' => 'GPT-4',
          'provider' => 'OpenAI',
          'description' => 'Advanced language model with improved reasoning.',
          'context_length' => 8192,
          'pricing' => [
            'prompt' => 10.00,
            'completion' => 30.00,
          ],
        ],
      ];
    }
  }

  /**
   * Extract provider name from model ID.
   *
   * @param string $model_id
   *   The model ID in format 'provider/model-name'.
   *
   * @return string
   *   The provider name or 'Unknown' if not found.
   */
  protected function extractProviderFromModelId(string $model_id): string {
    if (strpos($model_id, '/') !== FALSE) {
      [$provider] = explode('/', $model_id, 2);
      return ucfirst($provider);
    }
    return 'Unknown';
  }

}
