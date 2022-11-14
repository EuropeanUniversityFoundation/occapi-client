<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\occapi_client\JsonDataFetcherInterface;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiDataLoaderInterface;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities\Form\CourseForm;
use Drupal\occapi_entities_bridge\OccapiEntityManagerInterface;
use Drupal\occapi_entities_bridge\OccapiRemoteDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the course entity API form.
 */
class CourseApiForm extends CourseForm {

  const DATA_KEY = JsonDataSchemaInterface::JSONAPI_DATA;
  const ATTR_KEY = JsonDataSchemaInterface::JSONAPI_ATTR;

  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  const FIELD_REMOTE_ID = OccapiRemoteDataInterface::FIELD_REMOTE_ID;
  const FIELD_REMOTE_URL = OccapiRemoteDataInterface::FIELD_REMOTE_URL;
  const PARAM_EXTERNAL = OccapiRemoteDataInterface::PARAM_EXTERNAL;

  const ENTITY_HEI = OccapiEntityManagerInterface::ENTITY_HEI;
  const REF_HEI = OccapiEntityManagerInterface::ENTITY_REF[self::ENTITY_HEI];
  const UNIQUE_HEI = OccapiEntityManagerInterface::UNIQUE_ID[self::ENTITY_HEI];
  const ENTITY_COURSE = OccapiEntityManagerInterface::ENTITY_COURSE;

  /**
   * The remote URL for this Course.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * The tempstore key for this Course.
   *
   * @var string
   */
  protected $temp_store_key;

  /**
   * The OCCAPI data loader.
   *
   * @var \Drupal\occapi_client\OccapiDataLoaderInterface
   */
  protected $dataLoader;

  /**
  * The JSON data fetcher.
  *
  * @var \Drupal\occapi_client\JsonDataFetcherInterface
  */
  protected $jsonDataFetcher;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The OCCAPI provider manager.
   *
   * @var \Drupal\occapi_client\OccapiProviderManagerInterface
   */
  protected $providerManager;

  /**
   * The OCCAPI remote data handler.
   *
   * @var \Drupal\occapi_client\OccapiRemoteDataInterface
   */
  protected $remoteData;

  /**
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->dataLoader      = $container->get('occapi_client.load');
    $instance->jsonDataFetcher = $container->get('occapi_client.fetch');
    $instance->messenger       = $container->get('messenger');
    $instance->providerManager = $container->get('occapi_client.manager');
    $instance->remoteData      = $container->get('occapi_entities_bridge.remote');
    $instance->occapiTempStore = $container->get('occapi_client.tempstore');
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

    $remote_id      = $this->entity->get(self::FIELD_REMOTE_ID)->value;
    $this->endpoint = $this->entity->get(self::FIELD_REMOTE_URL)->value;

    if (empty($remote_id)) {
      $form['header'] = [
        '#type' => 'markup',
        '#markup' => '<em>' . $this->t('No API data available.') . '</em>'
      ];

      return $form;
    }

    $header_markup = $this->remoteData
      ->formatRemoteId($remote_id, $this->endpoint);

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => $header_markup
    ];

    // Get the entity ID of the referenced Institution.
    $ref_field = $this->entity->get(self::REF_HEI)->getValue();
    $target_id = $ref_field[0]['target_id'];

    // Get the Institution ID.
    $hei_id = $this->entityTypeManager
      ->getStorage(self::REF_HEI)
      ->load($target_id)
      ->get(self::UNIQUE_HEI)
      ->value;

    // Get the OCCAPI provider that covers the Institution ID.
    $providers = $this->providerManager->getProvidersByHeiId($hei_id);

    $provider_id = '';

    // Account for more than one provider for a given Institution ID.
    if (!empty($providers)) {
      $found = FALSE;
      foreach ($providers as $key => $obj) {
        if (! $found) {
          $provider_id = $key;
          $found = TRUE;
        }
      }
    }

    // Build the TempStore key for this Course.
    if (!empty($remote_id)) {
      $temp_store_params = [
        OccapiTempStoreInterface::PARAM_PROVIDER => $provider_id,
        OccapiTempStoreInterface::PARAM_FILTER_TYPE => NULL,
        OccapiTempStoreInterface::PARAM_FILTER_ID => NULL,
        OccapiTempStoreInterface::PARAM_RESOURCE_TYPE => self::TYPE_COURSE,
        OccapiTempStoreInterface::PARAM_RESOURCE_ID => $remote_id,
        OccapiTempStoreInterface::PARAM_EXTERNAL => self::PARAM_EXTERNAL,
      ];

      $this->temp_store_key = $this->occapiTempStore
        ->keyFromParams($temp_store_params);
    }

    // Load additional Course data from an external API.
    $course_ext = NULL;

    if (!empty($this->temp_store_key) && !empty($this->endpoint)) {
      $course_ext = $this->dataLoader
        ->loadExternalCourse($this->temp_store_key, $this->endpoint);
    }

    // Prepare the data from the extra fields.
    $display_data = [];

    if (!empty($course_ext)) {
      $course_ext_fields = OccapiFieldManager::getCourseExtraFields();

      $course_ext_data = $course_ext[self::DATA_KEY];
      $course_ext_attributes = $course_ext_data[self::ATTR_KEY];

      foreach ($course_ext_fields as $key => $value) {
        $display_data[$key] = $course_ext_attributes[$key] ?? [];
      }
    }

    // Render extra field data.
    if (!empty($display_data)) {
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

          if (isset($value[JsonDataProcessor::MLSTR_KEY])) {
            $text = $value[JsonDataProcessor::MLSTR_KEY];
          }
          elseif (isset($value[JsonDataProcessor::STR_KEY])) {
            $text = $value[JsonDataProcessor::STR_KEY];
          }
          else {
            $text = '';
          }

          $form[$key][$i][$key . '_' . $i . '_data'] = [
            '#type' => 'markup',
            '#markup' => $text,
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = [];

    $element['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh data'),
      '#submit' => ['::submitForm'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->jsonDataFetcher
      ->load($this->temp_store_key, $this->endpoint, TRUE);

    $this->messenger
      ->addMessage($this->t('Refreshed data for this Course.'));

    parent::submitForm($form, $form_state);
  }

}
