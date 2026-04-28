<?php

namespace Drupal\tool\Normalizer;

use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes Map data definitions to JSON Schema.
 */
class MapDefinitionNormalizer extends ComplexDataNormalizer {

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
    assert($this->serializer instanceof NormalizerInterface);

    $schema = [
      'type' => 'object',
    ];
    foreach ($object->getDataDefinition()->getPropertyDefinitions() as $name => $property_definition) {
      $data = $property_definition->getTypedDataManager()->create($property_definition);
      $property_schema = $this->serializer->normalize($data, 'json_schema', $context);
      if ($property_schema) {
        $schema['properties'][$name] = $property_schema;
        if ($property_definition->isRequired()) {
          $schema['required'] ??= [];
          $schema['required'][] = $name;
        }
      }
    }
    return $schema;

  }

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL) {
    if ($format === 'json_schema') {
      return TRUE;
    }
    return parent::checkFormat($format);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      Map::class => ($format === 'json_schema'),
    ];
  }

}
