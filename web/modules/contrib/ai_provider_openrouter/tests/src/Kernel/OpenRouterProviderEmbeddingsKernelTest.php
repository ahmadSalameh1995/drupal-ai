<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Kernel;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;

/**
 * Kernel tests for OpenRouter embeddings operations.
 *
 * @group ai_provider_openrouter
 */
class OpenRouterProviderEmbeddingsKernelTest extends OpenRouterKernelTestBase {

  /**
   * Test that the provider supports the embeddings operation type.
   */
  public function testProviderSupportsEmbeddingsOperationType(): void {
    $operation_types = $this->provider->getSupportedOperationTypes();
    $this->assertContains('embeddings', $operation_types, 'OpenRouter provider supports the embeddings operation type.');
  }

  /**
   * Test single string embedding.
   */
  public function testSingleStringEmbedding(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $response = $this->provider->embeddings(
      'This is a test sentence.',
      'openai/text-embedding-3-small',
      []
    );

    $this->assertNotNull($response, 'Embeddings response should not be null.');
    $embedding = $response->getNormalized();
    $this->assertIsArray($embedding, 'Embedding should be an array.');
    $this->assertNotEmpty($embedding, 'Embedding should not be empty.');
    $this->assertCount(1536, $embedding, 'text-embedding-3-small should return 1536 dimensions.');
    
    // Verify all values are floats.
    foreach ($embedding as $value) {
      $this->assertIsFloat($value, 'Each embedding value should be a float.');
    }
  }

  /**
   * Test batch embeddings with array of strings.
   */
  public function testBatchEmbeddings(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $texts = [
      'First sentence.',
      'Second sentence.',
      'Third sentence.',
    ];

    $response = $this->provider->embeddings(
      $texts,
      'openai/text-embedding-3-small',
      []
    );

    $embeddings = $response->getNormalized();
    $this->assertIsArray($embeddings, 'Batch embeddings should return an array.');
    $this->assertCount(3, $embeddings, 'Should return 3 embeddings for 3 inputs.');

    foreach ($embeddings as $embedding) {
      $this->assertIsArray($embedding, 'Each embedding should be an array.');
      $this->assertCount(1536, $embedding, 'Each embedding should have 1536 dimensions.');
    }
  }

  /**
   * Test embeddings with EmbeddingsInput object.
   */
  public function testEmbeddingsWithInputObject(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $input = new EmbeddingsInput('Test with input object.');

    $response = $this->provider->embeddings(
      $input,
      'openai/text-embedding-3-small',
      []
    );

    $embedding = $response->getNormalized();
    $this->assertIsArray($embedding, 'Embedding should be an array.');
    $this->assertCount(1536, $embedding, 'Should return 1536 dimensions.');
  }

  /**
   * Test embeddingsVectorSize() for known models.
   */
  public function testEmbeddingsVectorSize(): void {
    // Test OpenAI models.
    $this->assertEquals(1536, $this->provider->embeddingsVectorSize('openai/text-embedding-ada-002'));
    $this->assertEquals(1536, $this->provider->embeddingsVectorSize('openai/text-embedding-3-small'));
    $this->assertEquals(3072, $this->provider->embeddingsVectorSize('openai/text-embedding-3-large'));

    // Test Google models.
    $this->assertEquals(768, $this->provider->embeddingsVectorSize('google/gemini-embedding-001'));

    // Test Mistral models.
    $this->assertEquals(1024, $this->provider->embeddingsVectorSize('mistralai/mistral-embed-2312'));

    // Test BAAI models.
    $this->assertEquals(1024, $this->provider->embeddingsVectorSize('baai/bge-large-en-v1.5'));
    $this->assertEquals(768, $this->provider->embeddingsVectorSize('baai/bge-base-en-v1.5'));

    // Test sentence transformers.
    $this->assertEquals(384, $this->provider->embeddingsVectorSize('sentence-transformers/all-minilm-l6-v2'));
    $this->assertEquals(768, $this->provider->embeddingsVectorSize('sentence-transformers/all-mpnet-base-v2'));

    // Test unknown model returns 0.
    $this->assertEquals(0, $this->provider->embeddingsVectorSize('unknown/model'));
  }

  /**
   * Test maxEmbeddingsInput() returns reasonable value.
   */
  public function testMaxEmbeddingsInput(): void {
    $max = $this->provider->maxEmbeddingsInput('openai/text-embedding-3-small');
    $this->assertEquals(8192, $max, 'maxEmbeddingsInput should return 8192.');
  }

  /**
   * Test model filtering for embeddings operation type.
   */
  public function testEmbeddingsModelFiltering(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $models = $this->provider->getConfiguredModels('embeddings');

    $this->assertNotEmpty($models, 'Should return embedding models.');

    // Verify embedding models are not in the chat model list.
    $chat_models = $this->provider->getConfiguredModels('chat');
    foreach (array_keys($models) as $model_id) {
      $this->assertArrayNotHasKey($model_id, $chat_models,
        "Embedding model {$model_id} should not appear in chat model list.");
    }
  }

  /**
   * Test that chat models are excluded from embeddings list.
   */
  public function testChatModelsExcludedFromEmbeddings(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $embedding_models = $this->provider->getConfiguredModels('embeddings');
    $chat_models = $this->provider->getConfiguredModels('chat');

    // Verify no overlap between embedding and chat models.
    $overlap = array_intersect(array_keys($embedding_models), array_keys($chat_models));
    $this->assertEmpty($overlap, 'Embedding models should not appear in chat model list.');
  }

  /**
   * Test embeddings with empty string.
   */
  public function testEmbeddingsWithEmptyString(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // OpenRouter/OpenAI should handle empty strings gracefully.
    $response = $this->provider->embeddings(
      '',
      'openai/text-embedding-3-small',
      []
    );

    $embedding = $response->getNormalized();
    $this->assertIsArray($embedding, 'Should return an array even for empty string.');
  }

  /**
   * Test embeddings with very long text.
   */
  public function testEmbeddingsWithLongText(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // Create a long text (but within limits).
    $long_text = str_repeat('This is a test sentence. ', 100);

    $response = $this->provider->embeddings(
      $long_text,
      'openai/text-embedding-3-small',
      []
    );

    $embedding = $response->getNormalized();
    $this->assertIsArray($embedding, 'Should handle long text.');
    $this->assertCount(1536, $embedding, 'Should return correct dimensions.');
  }

}
