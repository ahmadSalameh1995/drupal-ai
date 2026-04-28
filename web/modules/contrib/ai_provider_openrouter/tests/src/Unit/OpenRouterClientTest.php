<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Unit;

use Drupal\ai_provider_openrouter\Service\OpenRouterClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\key\KeyRepositoryInterface;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;

/**
 * Unit tests for the OpenRouterClient service.
 */
class OpenRouterClientTest extends TestCase {

  /**
   * Ensures the client can be instantiated with config and a key repository.
   */
  public function testClientInitializesWithConfigAndKey(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['api_key', 'test_key'],
      ['base_url', 'https://custom.example/api/v1'],
    ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('ai_provider_openrouter.settings')->willReturn($config);

    $key_entity = $this->createMock(Key::class);
    $key_entity->method('getKeyValue')->willReturn('FAKE_API_KEY');

    $key_repository = $this->createMock(KeyRepositoryInterface::class);
    $key_repository->method('getKey')->with('test_key')->willReturn($key_entity);

    $logger = $this->createMock(LoggerInterface::class);

    $http_client = $this->createMock(GuzzleClientInterface::class);

    $client = new OpenRouterClient($config_factory, $key_repository, $logger, $http_client);
  }

}
