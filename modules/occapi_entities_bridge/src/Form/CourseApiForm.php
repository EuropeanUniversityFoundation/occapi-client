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
   * OCCAPI provider manager service.
   *
   * @var \Drupal\occapi_client\OccapiProviderManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->importManager   = $container->get('occapi_entities_bridge.manager');
    $instance->providerManager = $container->get('occapi_client.manager');
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

    // dpm($this);

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

    $remote_id  = $this->entity->get(Manager::REMOTE_ID)->value;

    if (! empty($remote_id)) {
      $tempstore = \implode('.', [
        $provider_id,
        OccapiProviderManager::COURSE_KEY,
        $remote_id,
        Manager::EXT_SUFFIX
      ]);
    }

    // Load additional Course data from an external API.
    $remote_url = $this->entity->get(Manager::REMOTE_URL)->value;

    $course_external = NULL;

    if (! empty($tempstore) && ! empty($remote_url)) {
      $course_external = $this->importManager
        ->loadExternalCourse($tempstore, $remote_url);
    }

    $display_data = [];

    // Prepare the data from the extra fields.
    if (! empty($course_external)) {
      $course_external_fields = OccapiFieldManager::getCourseExtraFields();

      $course_external_data = $course_external[JsonDataProcessor::DATA_KEY];
      $course_external_attributes = $course_external_data[JsonDataProcessor::ATTR_KEY];

      foreach ($course_external_fields as $key => $value) {
        $display_data[$key] = $course_external_attributes[$key];
      }
    }

    // Render extra field data.
    if (! empty($display_data)) {
      foreach ($display_data as $key => $value) {
        $form[$key] = [
          '#type' => 'details',
          '#title' => $key,
        ];

        $form[$key][$key . '_data'] = [
          '#type' => 'markup',
          '#markup' => $value[0][JsonDataProcessor::MLSTR_KEY],
        ];

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
