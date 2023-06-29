<?php

namespace Drupal\openy_node_blog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;

class BlogSimplePaginator {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the BlogSimplePaginator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $current_route_match) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->routeMatch = $current_route_match;
  }

  /**
   * @param $direction
   *
   * @return array|mixed[]|null
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function buildQuery($direction = 'prev') {
    $currentNode = $this->routeMatch->getParameter('node');

    if (!$currentNode) {
      return NULL;
    }

    $query = $this->nodeStorage->getQuery();

    $compare = '>';
    $sort = 'ASC';

    if ($direction == 'prev') {
      $compare = '<';
      $sort = 'DESC';
    }

    $result = $query->condition('created', $currentNode->getCreatedTime(), $compare)
      ->condition('type', $currentNode->bundle())
      ->condition('status', 1)
      ->condition('langcode', $currentNode->language()->getId())
      ->sort('created', $sort)
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($result) && is_array($result)) {
      $nid = array_shift($result);
      $node = $this->nodeStorage->load($nid);

      $link = Link::fromTextAndUrl($node->label(), $node->toUrl());
      $link = $link->toRenderable();
      return $link;
    }

    return NULL;
  }

  /**
   * @return array|mixed[]|null
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getPrevLink() {
    return $this->buildQuery('prev');
  }

  /**
   * @return array|mixed[]|null
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getNextLink() {
    return $this->buildQuery('next');
  }

  /**
   * @return array
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function build() {
    $prevLink = $this->getPrevLink();
    $nextLink = $this->getNextLink();
    $class = '';
    if ($prevLink && $nextLink) {
      $class = 'both';
    }

    return [
      '#theme' => 'simple_pagination',
      '#prev_link' => $prevLink,
      '#next_link' => $nextLink,
      '#wrapperClass' => $class,
    ];
  }

}
