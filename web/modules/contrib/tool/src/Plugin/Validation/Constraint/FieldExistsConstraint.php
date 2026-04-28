<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint to validate that a field exists on an entity type and bundle.
 *
 * @Constraint(
 *   id = "FieldExists",
 *   label = @Translation("Field exists", context = "Validation"),
 *   type = "string"
 * )
 */
class FieldExistsConstraint extends Constraint {

  /**
   * The default violation message.
   */
  public string $message = 'Field "@field" does not exist on @entity_type entity with bundle "@bundle".';

  /**
   * The entity type ID context variable name.
   */
  public string $entityTypeId = 'entityTypeId';

  /**
   * The bundle context variable name.
   */
  public string $bundle = 'bundle';

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return [$this->entityTypeId, $this->bundle];
  }

}
