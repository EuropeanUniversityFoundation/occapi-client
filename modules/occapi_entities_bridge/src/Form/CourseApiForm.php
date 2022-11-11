<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_entities\Form\CourseForm;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the course entity API form.
 */
class CourseApiForm extends CourseForm {

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
  * The JSON data fetcher.
  *
  * @var \Drupal\occapi_client\JsonDataFetcher
  */
  protected $jsonDataFetcher;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->jsonDataFetcher = $container->get('occapi_client.fetch');
    $instance->messenger       = $container->get('messenger');
    $instance->importManager   = $container->get('occapi_entities_bridge.manager');
    $instance->providerManager = $container->get('occapi_client.manager');
    $instance->remoteData      = $container->get('occapi_entities_bridge.remote');
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

    $remote_id      = $this->entity->get(Manager::REMOTE_ID)->value;
    $this->endpoint = $this->entity->get(Manager::REMOTE_URL)->value;

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
    $ref_field = $this->entity->get(Manager::REF_HEI)->getValue();
    $target_id = $ref_field[0]['target_id'];

    // Get the Institution ID.
    $hei_id = $this->entityTypeManager
      ->getStorage(Manager::REF_HEI)
      ->load($target_id)
      ->get('hei_id')
      ->value;

    // Get the OCCAPI provider that covers the Institution ID.
    $providers = $this->providerManager->getProvidersByHeiId($hei_id);

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
    if (! empty($remote_id)) {
      $this->temp_store_key = \implode('.', [
        $provider_id,
        OccapiProviderManager::COURSE_KEY,
        $remote_id,
        Manager::EXT_SUFFIX
      ]);
    }

    // Load additional Course data from an external API.
    $course_ext = NULL;

    if (! empty($this->temp_store_key) && ! empty($this->endpoint)) {
      $course_ext = $this->remoteData
        ->loadExternalCourse($this->temp_store_key, $this->endpoint);
    }

    // Prepare the data from the extra fields.
    $display_data = [];

    if (! empty($course_ext)) {
      $course_ext_fields = OccapiFieldManager::getCourseExtraFields();

      $course_ext_data = $course_ext[JsonDataProcessor::DATA_KEY];
      $course_ext_attributes = $course_ext_data[JsonDataProcessor::ATTR_KEY];

      foreach ($course_ext_fields as $key => $value) {
        $display_data[$key] = $course_ext_attributes[$key] ?? [];
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
