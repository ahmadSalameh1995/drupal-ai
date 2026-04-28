<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Kernel;

use Drupal\ai\OperationType\TextToImage\TextToImageInput;

/**
 * Kernel tests for OpenRouter text-to-image operations.
 *
 * @group ai_provider_openrouter
 */
class OpenRouterProviderTextToImageKernelTest extends OpenRouterKernelTestBase {

  /**
   * Test that the provider supports the text_to_image operation type.
   */
  public function testProviderSupportsTextToImageOperationType(): void {
    $operation_types = $this->provider->getSupportedOperationTypes();
    $this->assertContains('text_to_image', $operation_types, 'OpenRouter provider supports the text_to_image operation type.');
  }

  /**
   * Test simple text-to-image generation with string input.
   */
  public function testSimpleTextToImageWithString(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // @todo Re-enable when openai-php/client supports GPT-5 image response format.
    // The library throws TypeError on null index in CreateResponseChoiceImage.
    $this->markTestSkipped('Blocked by upstream openai-php/client bug with GPT-5 image responses.');

    // Use a model that supports image generation via OpenRouter.
    $response = $this->provider->textToImage(
      'A simple red circle on white background',
      'openai/gpt-5-image-mini',
      []
    );

    $this->assertNotNull($response, 'Text-to-image response should not be null.');
    $images = $response->getNormalized();
    $this->assertIsArray($images, 'Response should contain an array of images.');
    $this->assertNotEmpty($images, 'Should generate at least one image.');

    // Verify first image.
    $image = $images[0];
    $this->assertInstanceOf('Drupal\ai\OperationType\GenericType\ImageFile', $image);
    $this->assertNotEmpty($image->getBinaryContent(), 'Image should have binary content.');
    $this->assertStringStartsWith('image/', $image->getMimeType(), 'Image should have image/* mime type.');
  }

  /**
   * Test text-to-image with TextToImageInput object.
   */
  public function testTextToImageWithInputObject(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // @todo Re-enable when openai-php/client supports GPT-5 image response format.
    $this->markTestSkipped('Blocked by upstream openai-php/client bug with GPT-5 image responses.');

    $input = new TextToImageInput('A blue square');

    $response = $this->provider->textToImage(
      $input,
      'openai/gpt-5-image-mini',
      []
    );

    $images = $response->getNormalized();
    $this->assertNotEmpty($images, 'Should generate images from TextToImageInput.');
  }

  /**
   * Test that generated images are valid image files.
   */
  public function testGeneratedImagesAreValid(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // @todo Re-enable when openai-php/client supports GPT-5 image response format.
    $this->markTestSkipped('Blocked by upstream openai-php/client bug with GPT-5 image responses.');

    $response = $this->provider->textToImage(
      'A green triangle',
      'openai/gpt-5-image-mini',
      []
    );

    $images = $response->getNormalized();
    $image = $images[0];

    // Verify image can be decoded.
    $binary = $image->getBinaryContent();
    $this->assertNotEmpty($binary, 'Image binary content should not be empty.');

    // Try to get image info.
    $temp_file = tempnam(sys_get_temp_dir(), 'test_image_');
    file_put_contents($temp_file, $binary);
    $image_info = @getimagesize($temp_file);
    unlink($temp_file);

    $this->assertNotFalse($image_info, 'Generated image should be a valid image file.');
    $this->assertGreaterThan(0, $image_info[0], 'Image should have width > 0.');
    $this->assertGreaterThan(0, $image_info[1], 'Image should have height > 0.');
  }

  /**
   * Test error handling for invalid model.
   */
  public function testInvalidModelError(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $this->expectException(\Drupal\ai\Exception\AiResponseErrorException::class);

    $this->provider->textToImage(
      'test',
      'invalid/nonexistent-image-model',
      []
    );
  }

  /**
   * Test that models supporting image generation are identified.
   */
  public function testImageGenerationModelsAreIdentified(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $models = $this->provider->getConfiguredModels('text_to_image');

    $this->assertNotEmpty($models, 'Should return text-to-image capable models.');

    // Verify DALL-E models are included.
    // Verify at least one model is identified as supporting image generation.
    // The specific models available depend on which models are enabled in config.
    $model_ids = array_keys($models);
    $this->assertNotEmpty($model_ids, 'At least one text-to-image model should be available.');
  }

}
