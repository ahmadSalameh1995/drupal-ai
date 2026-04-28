<?php

namespace Drupal\tool_ai_connector\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\tool_ai_connector\Plugin\AiFunctionCall\Derivative\ToolPluginDeriver;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\tool\Tool\ToolInterface;
use Drupal\tool\Tool\ToolManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Plugin implementation of the action plugin function.
 */
#[FunctionCall(
  id: 'tool',
  function_name: 'tool',
  name: new TranslatableMarkup('Tool Plugin Wrapper'),
  description: '',
  deriver: ToolPluginDeriver::class
)]
final class ToolPluginBase extends FunctionCallBase implements ExecutableFunctionCallInterface {
  /**
   * The context values.
   *
   * @var array
   */
  protected array $values = [];

  /**
   * The status of the action execution.
   *
   * @var string|null
   */
  protected $executionStatus;

  /**
   * The error message, if any.
   *
   * @var string|null
   */
  protected $errorMessage;

  /**
   * The tool plugin instance.
   *
   * @var \Drupal\tool\Tool\ToolInterface|null
   */
  protected ?ToolInterface $toolPluginInstance;

  /**
   * The tool manager.
   *
   * @var \Drupal\tool\Tool\ToolManager
   */
  protected ToolManager $toolManager;

  /**
   * The private temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $privateTempStore;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->toolManager = $container->get('plugin.manager.tool');
    $instance->privateTempStore = $container->get('tempstore.private');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    try {
      // We defer upcasting until execution time, so that any context
      // modifications are taken into account.
      foreach ($this->getPluginInstance()->getInputDefinitions() as $name => $definition) {
        if (isset($this->values[$name])) {
          $this->setInputValue($name, $this->values[$name]);
        }
      }
      if ($this->getPluginInstance()->access()) {
        $this->getPluginInstance()->execute();
      }
      else {
        $this->errorMessage = 'Tool plugin access denied.';
      }
    }
    catch (\Exception $e) {
      $this->errorMessage = 'Tool plugin execution failed: ' . $e->getMessage();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    // @todo move to unified artifact solution.
    $tempstore = $this->privateTempStore->get('ai_tool_artifacts');

    if (isset($this->errorMessage)) {
      $output = $this->errorMessage;
      $properties = [];

      foreach ($this->contextDefinitionNormalizer->normalize($this->getPluginInstance()->getInputDefinitions()) as $property) {
        $properties[$property->getName()] = $property->renderPropertyArray();
      }

      $object = [
        'type' => 'object',
        'properties' => $properties,
      ];
      $output .= "\nThe tool input schema is: " . json_encode($object);
    }
    elseif ($this->getPluginInstance()?->getResult()?->isSuccess()) {
      $output = (string) $this->t('Tool Plugin executed successfully: @result',
        [
          '@result' => (string) $this->getPluginInstance()->getResult()->getMessage(),
        ]);
      foreach ($this->getPluginInstance()->getOutputDefinitions() as $key => $definition) {
        if ($definition instanceof EntityContextDefinition || $definition->getDataType() === 'entity') {
          // If the output is an entity, we can provide a link to it.
          $entity = $this->getPluginInstance()->getOutputValue($key);
          if (!$entity) {
            $output .= "\nOutput for " . $key . ": NULL (no entity)";
            continue;
          }
          $output .= "\n";

          // Get the entity's langcode for the artifact.
          // Default to 'undefined' if no language.
          $langcode = 'und';
          if (method_exists($entity, 'language')) {
            $langcode = $entity->language()->getId();
          }

          if ($entity->isNew()) {
            if (!isset($entity->ai_hash)) {
              $hash = substr(md5(serialize($entity)), 0, 6);
              $entity->ai_hash = $hash;
            }
            $artifact_key = "{{entity:" . $entity->getEntityTypeId() . ":new:" . $entity->ai_hash . ":" . $langcode . "}}";
            $output .= "An artifact has been created for the returned " . $key . " output.  To use this new entity for subsequent tool input arguments, pass the artifact string '" . $artifact_key . "' for entity inputs.\n";
          }
          else {
            $artifact_key = "{{entity:" . $entity->getEntityTypeId() . ":" . $entity->id() . ":" . $langcode . "}}";
            $output .= "An artifact has been created for the returned " . $key . " output.  To use this entity for subsequent tool input arguments, pass the artifact string '" . $artifact_key . "' for entity inputs.\n";
            $output .= "The artifact entity(" . $entity->getEntityTypeId() . ") id(" . $entity->id() . ") langcode(" . $langcode . ") and revision id(" . $entity->getRevisionId() . ")\n";
          }

          $artifact_key = str_replace('{{', 'artifact__', $artifact_key);
          $artifact_key = str_replace('}}', '', $artifact_key);
          $tempstore->delete($artifact_key);
          $tempstore->set($artifact_key, $entity);
        }
        else {
          $value = $this->getPluginInstance()->getOutputValue($key);
          $output .= "\n";
          $output .= "Output for " . $key . ": " . (is_array($value) ? json_encode($value) : $value);
        }
      }
    }
    else {
      $output = (string) $this->t('Tool Plugin executed unsuccessfully: @result',
        [
          '@result' => (string) $this->getPluginInstance()->getResult()->getMessage(),
        ]);
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    return $this->getPluginInstance()->getInputDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition($name) {
    return $this->getPluginInstance()->getInputDefinition($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextValues() {
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function setContextValue($name, $value) {
    $this->values[$name] = $value;
    return $this;
  }

  /**
   * Set the input value with proper upcasting.
   */
  protected function setInputValue($name, $value) {
    if ($this->getContextDefinition($name)->isMultiple()) {
      $value = $this->dataTypeConverterManager()->convert('list', $value);
      foreach ($value as $delta => $item) {
        $value[$delta] = $this->dataTypeConverterManager()->convert($this->getContextDefinition($name)->getDataType(), $item);
        $value[$delta] = static::handleArtifactConversion($value[$delta]);
      }
    }
    else {
      $value = $this->dataTypeConverterManager()->convert($this->getContextDefinition($name)->getDataType(), $value);
      $value = static::handleArtifactConversion($value);
    }
    $this->getPluginInstance()->setInputValue($name, $value);
  }

  /**
   * Gets the tool plugin instance.
   */
  public function getPluginInstance(): ?ToolInterface {
    if (!isset($this->toolPluginInstance)) {
      $this->toolPluginInstance = $this->toolManager->createInstance($this->getDerivativeId());
    }
    return $this->toolPluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function validateContexts() {
    return new ConstraintViolationList();
  }

  /**
   * A short term solution to handle artifact conversion for tool inputs.
   *
   * @todo move to unified solution.
   *
   * @param mixed $value
   *   The value to check for artifact conversion.
   *
   * @return mixed
   *   The converted value if artifact was found, otherwise the original value.
   */
  public static function handleArtifactConversion(mixed $value) {
    if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
      if (preg_match('/{{(.*?)}}/', $value, $matches)) {
        $artifact_id = trim($matches[1]);

        $tempstore = \Drupal::service('tempstore.private')
          ->get('ai_tool_artifacts');
        // Load the artifact from the temp store.
        $value = $tempstore->get('artifact__' . $artifact_id);
      }
    }
    return $value;
  }

}
