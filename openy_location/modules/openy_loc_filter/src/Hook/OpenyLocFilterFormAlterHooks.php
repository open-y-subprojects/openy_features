<?php

namespace Drupal\openy_loc_filter\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\openy_loc_filter\Form\LocationFilterSettingsForm;

/**
 * Hook implementations for openy_loc_filter.
 */
class OpenyLocFilterFormAlterHooks {

  /**
   * Implements hook_form_alter().
   *
   * D12 (change record 3496788): moved from a bare procedural function to
   * this OOP method so the order: Order::Last parameter actually takes
   * effect. Core's HookCollectorPass only reads a #[Hook] attribute's order
   * parameter when it is on a real class method (OOP scan branch); the
   * procedural-file scan branch never does (see
   * HookCollectorPass::collectModuleHookImplementations(),
   * core/lib/Drupal/Core/Hook/HookCollectorPass.php:397-405 vs. 424-466).
   * The old openy_loc_filter_module_implements_alter() procedural reorder
   * is left in place as dead code for pre-11.2 BC.
   */
  #[Hook('form_alter', order: Order::Last)]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    $groups = [];
    $locations = [];
    switch ($form_id) {
      // To show Branches and Camps enabled in Location Filter settings form.
      case 'openy_popups_branches_form':
      case 'views_exposed_form':
        if ('views_exposed_form' == $form_id &&
          'views-exposed-form-classes-listing-search-form' == $form['#id']) {
          $locations = &$form['location']['#options'];
          $groups = ['branches', 'camps'];
        }
        elseif ($form_id != 'views_exposed_form') {
          $locations = &$form['branch'];
          $groups = ['#options', '#branches', '#camps'];
        }

        $allowed_locations = \Drupal::config(LocationFilterSettingsForm::CONFIG_NAME)
          ->get('locations');

        if (empty($groups) && empty($locations) && empty($allowed_locations)) {
          break;
        }

        foreach ($groups as $group) {
          foreach ($locations[$group] as $key => $location) {
            if ($key != 'All' && !in_array($key, $allowed_locations)) {
              unset($locations[$group][$key]);
            }
          }
          if (empty($locations[$group])) {
            unset($locations[$group]);
          }
        }

        break;
    }
  }

}
