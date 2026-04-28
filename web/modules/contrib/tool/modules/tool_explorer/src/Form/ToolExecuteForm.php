<?php

declare(strict_types=1);

namespace Drupal\tool_explorer\Form;

use Drupal\Core\DependencyInjection\ClassResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\tool\Tool\ToolManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for executing tools from the tool explorer.
 */
class ToolExecuteForm extends FormBase {

  /**
   * Constructs a ToolExecuteForm object.
   *
   * @param \Drupal\tool\Tool\ToolManager $toolManager
   *   The tool plugin manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolver $classResolver
   *   The class resolver service.
   */
  public function __construct(
    protected ToolManager $toolManager,
    protected ClassResolver $classResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.tool'),
      $container->get('class_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tool_explorer_execute_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $plugin_id = NULL) {
    if (!$this->toolManager->hasDefinition($plugin_id)) {
      throw new NotFoundHttpException();
    }
    /** @var \Drupal\tool\Tool\ToolDefinition $definition */
    $definition = $this->toolManager->getDefinition($plugin_id);
    // Create the tool plugin instance.
    $tool = $this->toolManager->createInstance($plugin_id);
    // Store the tool instance in form state.
    $form_state->set('tool_plugin', $tool);
    $form_state->set('plugin_id', $plugin_id);

    $form['info'] = [
      '#type' => 'item',
      '#markup' => '<h2>' . $definition->getLabel() . '</h2><p>' . $definition->getDescription() . '</p>',
      '#weight' => -50,
    ];

    // Build the execute form using the tool's plugin form.
    $class_name = $tool->getFormClass('execute');
    $plugin_form = $this->classResolver->getInstanceFromDefinition($class_name);
    $plugin_form->setPlugin($tool);

    $subform_state = SubformState::createForSubform($form, $form, $form_state);
    $form = $plugin_form->buildConfigurationForm($form, $subform_state);

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('tool_explorer.view', ['plugin_id' => $plugin_id]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tool = $form_state->get('tool_plugin');

    $class_name = $tool->getFormClass('execute');
    $plugin_form = $this->classResolver->getInstanceFromDefinition($class_name);
    $plugin_form->setPlugin($tool);

    $subform_state = SubformState::createForSubform($form, $form, $form_state);
    $plugin_form->submitConfigurationForm($form, $subform_state);

    try {
      $result = $tool->getResult();
      // @todo Enhance output display.
      // We cannot currently print outputs because of security concerns.
      // E.g. User has access to view entity but not all fields on the entity.
      if (!$result->isSuccess()) {
        $this->messenger()->addError($this->t('Tool execution failed: @message', [
          '@message' => $result->getMessage(),
        ]));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error executing tool: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

}
