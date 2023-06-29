<?php

namespace Drupal\openy_prgf_class_sessions;

use Drupal\node\NodeInterface;

/**
 * Class ClassSessionsService.
 *
 * @package Drupal\openy_prgf_class_sessions
 */
interface ClassSessionsServiceInterface {

  /**
   * Retrieves Class Sessions for class nodes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The class node.
   *
   * @param array $conditions
   *   Array of key (field) value pairs.
   *
   * @return array
   *   Array of Drupal\openy_session_instance\Entity\SessionInstance.
   */
  public function getClassNodeSessionInstances(NodeInterface $node, $conditions = []);

  /**
   * Retrieves Session Instances rows.
   *
   * @param array $session_instances
   *   Array of \Drupal\openy_session_instance\Entity\SessionInstance.
   *
   * @param string[] $tags
   *   Cache tags array.
   *
   * @return array
   *   Session Instance rows for class_sessions theme.
   */
  public function getSessionInstancesRows($session_instances, &$tags);

  /**
   * Build render array of registation link.
   *
   * @param \Drupal\node\Entity\Node $session
   *
   * @return null|Link
   */
  public function getSessionRegistrationLink($session);

  /**
   * Get session online value.
   *
   * @param \Drupal\node\Entity\Node $session
   *
   * @return bool
   */
  public function getSessionOnlineRegistration($session);

  /**
   * Get session ticket value.
   *
   * @param \Drupal\node\Entity\Node $session
   *
   * @return bool
   */
  public function getSessionTicketRequired($session);

  /**
   * Get in membership value.
   *
   * @param \Drupal\node\Entity\Node $session
   *
   * @return bool
   */
  public function getSessionInMembership($session);

}
