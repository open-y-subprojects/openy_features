<?php

namespace Drupal\openy_loc_branch;

use Drupal\Core\Entity\EntityInterface;
use Drupal\openy_node_alert\Service\AlertBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides an alert builder for branches.
 */
class BranchAlertBuilder implements AlertBuilderInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs the BranchAlertBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $node) {
    return $node->bundle() === 'branch';
  }

  /**
   * {@inheritdoc}
   */
  public function build(EntityInterface $node) {
    $alerts_entities = $this->nodeStorage->loadByProperties([
      'type' => 'alert',
      'field_alert_location' => $node->id(),
      'status' => 1,
    ]);
    return array_keys($alerts_entities);
  }

}
