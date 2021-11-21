<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManager;
use Drupal\occapi_entities\Form\CourseForm;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the course entity API form.
 */
class CourseApiForm extends CourseForm {

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->importManager = $container->get('occapi_entities_bridge.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_api_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = [];

    $remote_id  = $this->entity->get(Manager::REMOTE_ID)->value;
    $remote_url = $this->entity->get(Manager::REMOTE_URL)->value;

    if (! empty($remote_id)) {
      $header_markup = $this->importManager
        ->formatRemoteId($remote_id, $remote_url);

      $form['header'] = [
        '#type' => 'markup',
        '#markup' => $header_markup
      ];
    }

    // Get the entity ID of the referenced Institution.
    $ref_field = $this->entity->get(Manager::REF_HEI)->getValue();
    $target_id = $ref_field[0]['target_id'];

    // Get the Institution ID.
    $hei_id = $this->entityTypeManager
      ->getStorage(Manager::REF_HEI)
      ->load($target_id)
      ->get('hei_id')
      ->value;

    // Get the OCCAPI provider that covers the Institution ID.
    $providers = $this->importManager
      ->getHeiProviders($hei_id);

    $provider_id = '';

    // Account for more than one provider for a given Institution ID.
    if (! empty($providers)) {
      $found = FALSE;
      foreach ($providers as $key => $obj) {
        if (! $found) {
          $provider_id = $key;
          $found = TRUE;
        }
      }
    }

    // Build the TempStore key for this Course.
    $tempstore = '';

    if (! empty($remote_id)) {
      $tempstore = \implode('.', [
        $provider_id,
        OccapiProviderManager::COURSE_KEY,
        $remote_id,
        Manager::EXT_SUFFIX
      ]);
    }

    // Load additional Course data from an external API.
    $course_ext = NULL;

    if (! empty($tempstore) && ! empty($remote_url)) {
      $course_ext = $this->importManager
        ->loadExternalCourse($tempstore, $remote_url);
    }

    // Prepare the data from the extra fields.
    $display_data = [];

    if (! empty($course_ext)) {
      $course_ext_fields = OccapiFieldManager::getCourseExtraFields();

      $course_ext_data = $course_ext[JsonDataProcessor::DATA_KEY];
      $course_ext_attributes = $course_ext_data[JsonDataProcessor::ATTR_KEY];

      foreach ($course_ext_fields as $key => $value) {
        $display_data[$key] = $course_ext_attributes[$key];
      }
    }

    // Render extra field data.
    if (! empty($display_data)) {
      foreach ($display_data as $key => $array) {
        $form[$key] = [
          '#type' => 'container'
        ];

        foreach ($array as $i => $value) {
          $lang = $value[JsonDataProcessor::LANG_KEY];
          $title = ($lang) ? $key . ' <code>' . $lang . '</code>' : $key;

          $form[$key][$i] = [
            '#type' => 'details',
            '#title' => $title,
          ];

          $form[$key][$i][$key . '_' . $i . '_data'] = [
            '#type' => 'markup',
            '#markup' => $value[JsonDataProcessor::MLSTR_KEY],
          ];
        }
      }
    }

    // $metadata   = $this->entity->get(Manager::JSON_META)->value;
    // dpm($metadata);


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = [];

    return $element;
  }

}
