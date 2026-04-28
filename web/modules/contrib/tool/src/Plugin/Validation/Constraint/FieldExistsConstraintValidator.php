<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for the FieldExists constraint.
 */
class FieldExistsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a FieldExistsValidator object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore new.static
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof FieldExistsConstraint);

    if ($value === NULL || $value === '') {
      return;
    }

    // Get the typed data object to access context.
    $typed_data = $this->context->getObject();
    if (!$typed_data instanceof TypedDataInterface) {
      return;
    }

    // Get entity type ID and bundle from context.
    $entity_type_id = $constraint->entityTypeId;
    $bundle = $constraint->bundle;

    if (!$entity_type_id || !$bundle) {
      // Cannot validate without entity type and bundle context.
      return;
    }

    // Validate that entity type exists.
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return;
    }

    try {
      // Get field definitions for the entity type and bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      // Check if the field exists.
      if (!isset($field_definitions[$value])) {
        $this->context->buildViolation($constraint->message)
          ->setParameter('@field', $value)
          ->setParameter('@entity_type', $entity_type_id)
          ->setParameter('@bundle', $bundle)
          ->addViolation();
      }
    }
    catch (\Exception $e) {
      // If we can't get field definitions, skip validation.
      return;
    }
  }

}
