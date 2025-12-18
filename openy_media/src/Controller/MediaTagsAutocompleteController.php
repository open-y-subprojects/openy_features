<?php

namespace Drupal\openy_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for media tags autocomplete that returns all terms on empty query.
 */
class MediaTagsAutocompleteController extends ControllerBase {

  /**
   * Returns autocomplete suggestions for media tags as a tree.
   */
  public function autocomplete(Request $request): JsonResponse {
    $results = [];
    $query = $request->query->get('q', '');

    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    if (!empty($query)) {
      // When searching, return flat filtered results.
      $term_query = $term_storage->getQuery()
        ->condition('vid', 'media_tags')
        ->condition('name', $query, 'CONTAINS')
        ->accessCheck(TRUE)
        ->sort('name', 'ASC');

      $tids = $term_query->execute();
      $terms = $term_storage->loadMultiple($tids);

      foreach ($terms as $term) {
        $results[] = [
          'value' => $term->label() . ' (' . $term->id() . ')',
          'label' => $term->label(),
          'depth' => 0,
        ];
      }
    }
    else {
      // When no query, return full tree structure.
      $tree = $term_storage->loadTree('media_tags', 0, NULL, TRUE);

      foreach ($tree as $term) {
        $results[] = [
          'value' => $term->label() . ' (' . $term->id() . ')',
          'label' => $term->label(),
          'depth' => $term->depth,
        ];
      }
    }

    return new JsonResponse($results);
  }

}
