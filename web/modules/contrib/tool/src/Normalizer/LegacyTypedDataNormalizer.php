<?php

namespace Drupal\tool\Normalizer;

use Drupal\Core\TypedData\Plugin\DataType\Email;
use Drupal\Core\TypedData\Plugin\DataType\FloatData;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Type\BooleanInterface;
use Drupal\Core\TypedData\Type\DecimalInterface;
use Drupal\Core\TypedData\Type\DurationInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\Type\UriInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;

/**
 * Normalizes Map data definitions to JSON Schema.
 */
class LegacyTypedDataNormalizer extends ComplexDataNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    if ($format === 'json_schema') {
      return $this->getNormalizationSchema($object, $context);
    }
    return $this->doNormalize($object, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function doNormalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizationSchema(mixed $object, array $context = []): array {
    $schema = match (TRUE) {
      $object instanceof BooleanInterface => [
        'type' => 'boolean',
      ],
      $object instanceof DecimalInterface => [
        'type' => 'string',
        'format' => 'number',
      ],
      $object instanceof DurationInterface => [
        'type' => 'string',
        'format' => 'duration',
      ],
      $object instanceof Email => [
        'type' => 'string',
        'format' => 'email',
      ],
      $object instanceof FloatData => [
        'type' => 'number',
      ],
      $object instanceof IntegerData => [
        'type' => 'integer',
      ],
      $object instanceof UriInterface => [
        'type' => 'string',
        'format' => 'uri',
      ],
      $object instanceof StringInterface => [
        'type' => 'string',
      ],
      TRUE => [],
    };

    // @todo Add enum support.
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    if ($format !== 'json_schema') {
      return [];
    }
    if (!class_exists("Drupal\Core\Serialization\Attribute\JsonSchema")) {
      return ['*' => TRUE];
    }
    return [TypedDataInterface::class => TRUE];
  }

}
