<?php

declare(strict_types=1);

namespace Drupal\tool\Normalizer;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Drupal\tool\TypedData\ListContextDefinition;
use Drupal\tool\TypedData\MapContextDefinition;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Normalizes ContextDefinition objects.
 *
 * This normalizer extends ComplexDataNormalizer to provide specialized
 * normalization for ContextDefinition objects, which are commonly used
 * in plugin systems and typed data definitions.
 *
 * @property-read \Symfony\Component\Serializer\Normalizer\NormalizerInterface $serializer
 */
class ContextDefinitionNormalizer extends ComplexDataNormalizer {
  use TypedDataTrait;

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
  public function doNormalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTypedData(DataDefinitionInterface $definition): mixed {
    return $this->getTypedDataManager()->create($definition);
  }

  /**
   * Get combined description from label and description.
   */
  protected function getDescription(ContextDefinitionInterface $definition) : ?string {
    if ($description = (string) $definition->getLabel()) {
      if ((string) $definition->getDescription()) {
        $description .= ': ' . (string) $definition->getDescription();
      }
    }
    else {
      $description = (string) $definition->getDescription();
    }
    return $description ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizationSchema(mixed $object, array $context = []): array {
    assert($object instanceof ContextDefinitionInterface);
    assert($this->serializer instanceof NormalizerInterface);

    // If multiple or a list, handle item definition.
    if ($object->getDataDefinition() instanceof ListDataDefinitionInterface) {
      $schema = [
        'type' => 'array',
      ];
      if ($object->isMultiple()) {
        // If multiple, clone and set multiple to false for item schema.
        $item_definition = clone $object;
        $item_definition->setMultiple(FALSE);
        $schema['items'] = $this->serializer->normalize($item_definition, 'json_schema', $context);
        if ($description = $this->getDescription($object)) {
          $schema['items']['description'] = (string) $description;
          $schema['description'] = 'Array of ' . (string) $object->getLabel();
        }
      }
      elseif ($object instanceof ListContextDefinition) {
        $schema['description'] = $this->getDescription($object);
        if ($object->getItemDefinition()) {
          $schema['items'] = $this->serializer->normalize($object->getItemDefinition(), 'json_schema', $context);
        }
      }
    }
    elseif ($object instanceof MapContextDefinition) {
      // Handle MapContextDefinition specifically.
      $schema = ['type' => 'object'];
      foreach ($object->getPropertyDefinitions() as $property_name => $property) {
        $property_schema = $this->serializer->normalize($property, 'json_schema', $context);
        if (empty($property_schema['description']) && $description = $this->getDescription($property)) {
          $property_schema['description'] = (string) $description;
        }
        $schema['properties'][$property_name] = $property_schema;
        if ($property->isRequired()) {
          $schema['required'] ??= [];
          $schema['required'][] = $property_name;
        }
      }
    }
    elseif ($schema = $this->serializer->normalize($this->getTypedData($object->getDataDefinition()), 'json_schema', $context)) {
      if ($default_value = $object->getDefaultValue()) {
        $schema['default'] = $default_value;
      }
      if ($constraints = $object->getConstraints()) {
        // Handle specific constraints as needed.
        if (isset($constraints['FixedValue'])) {
          // Extract value from constraint object or array structure.
          if (is_object($constraints['FixedValue']) && property_exists($constraints['FixedValue'], 'value')) {
            $schema['const'] = $constraints['FixedValue']->value;
          }
          elseif (is_array($constraints['FixedValue']) && isset($constraints['FixedValue']['value'])) {
            $schema['const'] = $constraints['FixedValue']['value'];
          }
          else {
            $schema['const'] = $constraints['FixedValue'];
          }
        }
        if (isset($constraints['Choice'])) {
          if (isset($constraints['Choice']['choices'])) {
            $schema['enum'] = $constraints['Choice']['choices'];
          }
          elseif (isset($constraints['Choice']['callback'])) {
            $schema['enum'] = call_user_func($constraints['Choice']['callback']);
          }
          elseif (is_array($constraints['Choice']) && array_is_list($constraints['Choice'])) {
            $schema['enum'] = $constraints['Choice'];
          }
        }
        if (isset($constraints['AllowedValues'])) {
          if ($constraints['AllowedValues'] instanceof Choice) {
            /** @var \Symfony\Component\Validator\Constraints\Choice $choice */
            $choice = $constraints['AllowedValues'];
            $schema['enum'] = $choice->choices;
          }
          elseif (isset($constraints['AllowedValues']['choices'])) {
            $schema['enum'] = $constraints['AllowedValues']['choices'];
          }
          elseif (isset($constraints['AllowedValues']['callback'])) {
            $schema['enum'] = call_user_func($constraints['AllowedValues']['callback']);
          }
          elseif (is_array($constraints['AllowedValues']) && !empty($constraints['AllowedValues'])) {
            $schema['enum'] = $constraints['AllowedValues'];
          }
        }
        if (isset($constraints['PluginExists'])) {
          // Get a list of plugin IDs from the specified plugin manager.
          // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
          $plugin_manager = \Drupal::service($constraints['PluginExists']['manager']); // phpcs:ignore
          $plugin_definitions = $plugin_manager->getDefinitions();
          $fallbacks = [];
          if (isset($constraints['PluginExists']['interface'])) {
            foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
              if (!is_a(DefaultFactory::getPluginClass($plugin_id, $plugin_definition), $constraints['PluginExists']['interface'], TRUE)) {
                unset($plugin_definitions[$plugin_id]);
              }
              if ($plugin_manager instanceof FallbackPluginManagerInterface) {
                $key = $plugin_manager->getFallbackPluginId($plugin_id);
                if ($key) {
                  $fallbacks[$key] = $key;
                }
              }
            }
          }
          // Remove fallback IDs from the list.
          if ($fallbacks) {
            $plugin_definitions = array_diff_key($plugin_definitions, $fallbacks);
          }
          $plugin_ids = array_keys($plugin_definitions);
          $schema['enum'] = $plugin_ids;
        }

        if (isset($constraints['Length'])) {
          if (isset($constraints['Length']['min'])) {
            $schema['minLength'] = $constraints['Length']['min'];
          }
          if (isset($constraints['Length']['max'])) {
            $schema['maxLength'] = $constraints['Length']['max'];
          }
        }
        if (isset($constraints['Range'])) {
          if (isset($constraints['Range']['min'])) {
            $schema['minimum'] = $constraints['Range']['min'];
          }
          if (isset($constraints['Range']['max'])) {
            $schema['maximum'] = $constraints['Range']['max'];
          }
        }
      }
    }
    else {
      $schema = [
        'type' => 'string',
      ];
    }

    if (empty($schema['description']) && $description = $this->getDescription($object)) {
      $schema['description'] = (string) $description;
    }
    if (!$object->isRequired()) {
      if (isset($schema['type']) && is_string($schema['type'])) {
        $schema['type'] = [$schema['type'], 'null'];
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
      ContextDefinitionInterface::class => ($format === 'json_schema'),
    ];
  }

}
