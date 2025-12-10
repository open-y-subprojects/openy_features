<?php

namespace Drupal\openy_media\Plugin\views\filter;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter media by taxonomy term with depth support.
 *
 * This filter allows filtering media entities by their associated taxonomy
 * terms (via field_media_tags) with optional depth support for hierarchical
 * term matching.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("openy_media_taxonomy_tags")]
class MediaTaxonomyTagsFilter extends ManyToOne {

  /**
   * Stores the exposed input for this filter.
   *
   * @var array|null
   */
  public $validated_exposed_input = NULL;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a MediaTaxonomyTagsFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\taxonomy\VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   * @param \Drupal\taxonomy\TermStorageInterface $term_storage
   *   The term storage.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage, TermStorageInterface $term_storage, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->vocabularyStorage = $vocabulary_storage;
    $this->termStorage = $term_storage;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    if (!empty($this->definition['vocabulary'])) {
      $this->options['vid'] = $this->definition['vocabulary'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = ['default' => 'select'];
    $options['limit'] = ['default' => TRUE];
    $options['vid'] = ['default' => 'media_tags'];
    $options['hierarchy'] = ['default' => TRUE];
    $options['error_message'] = ['default' => TRUE];
    $options['depth'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    $options = [];
    foreach ($vocabularies as $voc) {
      $options[$voc->id()] = $voc->label();
    }

    if ($this->options['limit']) {
      if (empty($this->options['vid'])) {
        $this->options['vid'] = 'media_tags';
      }

      if (empty($this->definition['vocabulary'])) {
        $form['vid'] = [
          '#type' => 'radios',
          '#title' => $this->t('Vocabulary'),
          '#options' => $options,
          '#description' => $this->t('Select which vocabulary to show terms for in the regular options.'),
          '#default_value' => $this->options['vid'],
        ];
      }
    }

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Selection type'),
      '#options' => ['select' => $this->t('Dropdown'), 'textfield' => $this->t('Autocomplete')],
      '#default_value' => $this->options['type'],
    ];

    $form['hierarchy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show hierarchy in dropdown'),
      '#default_value' => !empty($this->options['hierarchy']),
      '#states' => [
        'visible' => [
          ':input[name="options[type]"]' => ['value' => 'select'],
        ],
      ],
    ];

    $form['depth'] = [
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => $this->options['depth'],
      '#description' => $this->t('The depth will match media tagged with terms in the hierarchy. For example, if you have the term "Fitness" and a child term "Yoga", with a depth of 1 (or higher) then filtering for the term "Fitness" will get media that are tagged with "Yoga" as well as "Fitness". If negative, the reverse is true; searching for "Yoga" will also pick up media tagged with "Fitness" if depth is -1 (or lower).'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $vocabulary = $this->vocabularyStorage->load($this->options['vid']);
    if (empty($vocabulary) && $this->options['limit']) {
      $form['markup'] = [
        '#markup' => '<div class="js-form-item form-item">' . $this->t('An invalid vocabulary is selected. Change it in the options.') . '</div>',
      ];
      return;
    }

    if ($this->options['type'] == 'textfield') {
      $terms = $this->value ? Term::loadMultiple(($this->value)) : [];
      $form['value'] = [
        '#title' => $this->options['limit'] ? $this->t('Select terms from vocabulary @voc', ['@voc' => $vocabulary->label()]) : $this->t('Select terms'),
        '#type' => 'textfield',
        '#default_value' => EntityAutocomplete::getEntityLabels($terms),
      ];

      if ($this->options['limit']) {
        $form['value']['#type'] = 'entity_autocomplete';
        $form['value']['#target_type'] = 'taxonomy_term';
        $form['value']['#selection_settings']['target_bundles'] = [$vocabulary->id()];
        $form['value']['#tags'] = TRUE;
        $form['value']['#process_default_value'] = FALSE;
      }
    }
    else {
      if (!empty($this->options['hierarchy']) && $this->options['limit']) {
        $tree = $this->termStorage->loadTree($vocabulary->id(), 0, NULL, TRUE);
        $options = [];

        if ($tree) {
          foreach ($tree as $term) {
            if (!$term->isPublished() && !$this->currentUser->hasPermission('administer taxonomy')) {
              continue;
            }
            $choice = new \stdClass();
            $choice->option = [$term->id() => str_repeat('-', $term->depth) . \Drupal::service('entity.repository')->getTranslationFromContext($term)->label()];
            $options[] = $choice;
          }
        }
      }
      else {
        $options = [];
        $query = \Drupal::entityQuery('taxonomy_term')
          ->accessCheck(TRUE)
          ->sort('weight')
          ->sort('name')
          ->addTag('taxonomy_term_access');
        if (!$this->currentUser->hasPermission('administer taxonomy')) {
          $query->condition('status', 1);
        }
        if ($this->options['limit']) {
          $query->condition('vid', $vocabulary->id());
        }
        $terms = Term::loadMultiple($query->execute());
        foreach ($terms as $term) {
          $options[$term->id()] = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
        }
      }

      $default_value = (array) $this->value;

      if ($exposed = $form_state->get('exposed')) {
        $identifier = $this->options['expose']['identifier'];

        if (!empty($this->options['expose']['reduce'])) {
          $options = $this->reduceValueOptions($options);

          if (!empty($this->options['expose']['multiple']) && empty($this->options['expose']['required'])) {
            $default_value = [];
          }
        }

        if (empty($this->options['expose']['multiple'])) {
          if (empty($this->options['expose']['required']) && (empty($default_value) || !empty($this->options['expose']['reduce']))) {
            $default_value = 'All';
          }
          elseif (empty($default_value)) {
            $keys = array_keys($options);
            $default_value = array_shift($keys);
          }
          elseif ($default_value == ['']) {
            $default_value = 'All';
          }
          else {
            $copy = $default_value;
            $default_value = array_shift($copy);
          }
        }
      }

      $form['value'] = [
        '#type' => 'select',
        '#title' => $this->options['limit'] ? $this->t('Select terms from vocabulary @voc', ['@voc' => $vocabulary->label()]) : $this->t('Select terms'),
        '#multiple' => TRUE,
        '#options' => $options,
        '#size' => min(9, count($options)),
        '#default_value' => $default_value,
      ];

      $user_input = $form_state->getUserInput();
      if ($exposed && isset($identifier) && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }

    if (!$form_state->get('exposed')) {
      $this->helper->buildOptionsForm($form, $form_state);
      $form['value']['#description'] = $this->t('Leave blank for all. Otherwise, the first selected term will be the default instead of "Any".');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function valueValidate($form, FormStateInterface $form_state) {
    if ($this->options['type'] != 'textfield') {
      return;
    }

    $tids = [];
    if ($values = $form_state->getValue(['options', 'value'])) {
      foreach ($values as $value) {
        $tids[] = $value['target_id'];
      }
    }
    $form_state->setValue(['options', 'value'], $tids);
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id']) && isset($input[$this->options['expose']['operator_id']])) {
      $this->operator = $input[$this->options['expose']['operator_id']];
    }

    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validated_exposed_input = (array) $this->view->exposed_raw_input[$this->options['expose']['identifier']];
    }

    if ($this->operator == 'empty' || $this->operator == 'not empty') {
      return TRUE;
    }

    if (!$this->options['expose']['required'] && empty($this->validated_exposed_input)) {
      return FALSE;
    }

    $rc = parent::acceptExposedInput($input);
    if ($rc) {
      if (isset($this->validated_exposed_input)) {
        $this->value = $this->validated_exposed_input;
      }
    }

    return $rc;
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];
    $input = $form_state->getValue($identifier);

    if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
      $this->validated_exposed_input = $this->options['group_info']['group_items'][$input]['value'];
      return;
    }

    if ($this->options['type'] != 'textfield') {
      if ($form_state->getValue($identifier) != 'All') {
        $this->validated_exposed_input = (array) $form_state->getValue($identifier);
      }
      return;
    }

    if (empty($this->options['expose']['identifier'])) {
      return;
    }

    if ($values = $form_state->getValue($identifier)) {
      foreach ($values as $value) {
        $this->validated_exposed_input[] = $value['target_id'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    // Prevent array_filter from messing up our arrays in parent submit.
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    if ($this->options['type'] != 'select') {
      unset($form['expose']['reduce']);
    }
    $form['error_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display error message'),
      '#default_value' => !empty($this->options['error_message']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $this->valueOptions = [];

    if ($this->value) {
      $this->value = array_filter($this->value);
      $terms = Term::loadMultiple($this->value);
      foreach ($terms as $term) {
        $this->valueOptions[$term->id()] = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
      }
    }
    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // If no filter values are present, then do nothing.
    if (count($this->value) == 0) {
      return;
    }

    // Get all term IDs including children if depth is configured.
    $tids = $this->value;
    if ($this->options['depth'] != 0) {
      $tids = $this->getTermsWithDepth($this->value, $this->options['depth']);
    }

    // Ensure the table for field_media_tags is joined.
    $this->ensureMyTable();

    // Add the condition.
    $operator = count($tids) > 1 ? 'IN' : '=';
    $value = count($tids) > 1 ? $tids : reset($tids);
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $value, $operator);
  }

  /**
   * Get all term IDs with depth support.
   *
   * @param array $tids
   *   The selected term IDs.
   * @param int $depth
   *   The depth to search (positive = children, negative = parents).
   *
   * @return array
   *   Array of term IDs including related terms based on depth.
   */
  protected function getTermsWithDepth(array $tids, int $depth): array {
    $all_tids = $tids;

    foreach ($tids as $tid) {
      $term = $this->termStorage->load($tid);
      if (!$term) {
        continue;
      }

      if ($depth > 0) {
        // Get child terms.
        $children = $this->termStorage->loadTree($term->bundle(), $tid, $depth, FALSE);
        foreach ($children as $child) {
          $all_tids[] = $child->tid;
        }
      }
      elseif ($depth < 0) {
        // Get parent terms.
        $parents = $this->termStorage->loadAllParents($tid);
        $parent_depth = 0;
        foreach ($parents as $parent) {
          if ($parent->id() != $tid) {
            $parent_depth++;
            if ($parent_depth <= abs($depth)) {
              $all_tids[] = $parent->id();
            }
          }
        }
      }
    }

    return array_unique($all_tids);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $vocabulary = $this->vocabularyStorage->load($this->options['vid']);
    if ($vocabulary) {
      $dependencies[$vocabulary->getConfigDependencyKey()][] = $vocabulary->getConfigDependencyName();
    }

    foreach ($this->termStorage->loadMultiple($this->options['value']) as $term) {
      $dependencies[$term->getConfigDependencyKey()][] = $term->getConfigDependencyName();
    }

    return $dependencies;
  }

}
