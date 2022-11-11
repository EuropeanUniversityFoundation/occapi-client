<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataProcessorInterface;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OCCAPI entities import form.
 */
class OccapiImportForm extends FormBase {

  const PARAM_PROVIDER = OccapiTempStoreInterface::PARAM_PROVIDER;
  const PARAM_RESOURCE_ID = OccapiTempStoreInterface::PARAM_RESOURCE_ID;

  const TYPE_HEI = OccapiTempStoreInterface::TYPE_HEI;
  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  const DATA_KEY = JsonDataSchemaInterface::JSONAPI_DATA;
  const LINKS_KEY = JsonDataSchemaInterface::JSONAPI_LINKS;
  const SELF_KEY = JsonDataSchemaInterface::JSONAPI_SELF;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var AccountProxyInterface $currentUser
   */
  protected $currentUser;

  /**
   * OCCAPI Institution resource.
   *
   * @var array
   */
  protected $heiResource;

  /**
   * OCCAPI Programme resource.
   *
   * @var array
   */
  protected $programmeResource;

  /**
   * OCCAPI Course collection.
   *
   * @var array
   */
  protected $courseCollection;

  /**
   * Empty data placeholder.
   *
   * @var string
   */
  protected $emptyData;

  /**
   * Data formatter service.
   *
   * @var \Drupal\occapi_client\DataFormatter
   */
  protected $dataFormatter;

  /**
   * the OCCAPI data loader.
   *
   * @var \Drupal\occapi_client\OccapiDataLoaderInterface
   */
  protected $dataLoader;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * JSON data processing service.
   *
   * @var \Drupal\occapi_client\JsonDataProcessorInterface
   */
  protected $jsonDataProcessor;

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
    $instance->dataFormatter     = $container->get('occapi_client.format');
    $instance->dataLoader        = $container->get('occapi_client.load');
    $instance->importManager     = $container->get('occapi_entities_bridge.manager');
    $instance->jsonDataProcessor = $container->get('occapi_client.json');
    $instance->messenger         = $container->get('messenger');
    $instance->providerManager   = $container->get('occapi_client.manager');
    $instance->occapiTempStore   = $container->get('occapi_client.tempstore');
    $instance->currentUser       = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'occapi_entities_bridge_occapi_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $temp_store_key = NULL) {
    // Give a user with permission the opportunity to add an entity manually.
    $can_add_programme = $this->currentUser->hasPermission('create programme');
    $can_add_course = $this->currentUser->hasPermission('create course');
    $can_bypass = $this->currentUser
      ->hasPermission('bypass import occapi entities');

    if ($can_add_programme && $can_add_course && $can_bypass) {
      $add_programme_text = $this->t('add a new Programme');
      $add_programme_link = Link::fromTextAndUrl($add_programme_text,
        Url::fromRoute('entity.programme.add_form'))->toString();

      $add_course_text = $this->t('add a new Course');
      $add_course_link = Link::fromTextAndUrl($add_course_text,
        Url::fromRoute('entity.course.add_form'))->toString();

      $notice = $this->t('You can @act and @add_prog or @add_course manually.',[
        '@act' => $this->t('bypass this form'),
        '@add_prog' => $add_programme_link,
        '@add_course' => $add_course_link
      ]);

      $this->messenger->addMessage($notice);
    }

    // Validate the tempstore parameter.
    $error = $this->occapiTempStore
      ->validateResourceTempstore($temp_store_key, self::TYPE_PROGRAMME);

    if ($error) {
      $this->messenger->addError($error);
      return $form;
    }

    // Parse the tempstore parameter to get the OCCAPI provider and its HEI ID.
    $temp_store_params = $this->occapiTempStore->paramsFromKey($temp_store_key);

    $provider_id = $temp_store_params[self::PARAM_PROVIDER];
    $hei_id = $this->providerManager->getProvider($provider_id)->heiId();
    $programme_id = $temp_store_params[self::PARAM_RESOURCE_ID];

    // Check if the Institution is present in the system.
    $result = $this->importManager->validateInstitution($hei_id);

    if (! $result['status']) {
      $this->messenger->addError($result['message']);
      return $form;
    }

    // Load Programme data.
    $this->programmeResource = $this->dataLoader
      ->loadProgramme($provider_id, $programme_id);

    if (empty($this->programmeResource)) {
      $this->messenger->addError($this->t('Missing programme data!'));
      return $form;
    }

    $programme_links = $this->programmeResource[self::LINKS_KEY];
    $programme_table = $this->dataFormatter
      ->programmeResourceTable($this->programmeResource);

    $form['programme_tempstore'] = [
      '#type' => 'value',
      '#value' => $temp_store_key
    ];

    $form['programme'] = [
      '#type' => 'details',
      '#title' => $this->t('Programme resource data'),
      '#open' => TRUE
    ];

    $form['programme']['data'] = [
      '#type' => 'markup',
      '#markup' => $programme_table
    ];

    // Load Course data.
    if (\array_key_exists(self::TYPE_COURSE, $programme_links)) {
      $this->courseCollection = $this->dataLoader
        ->loadProgrammeCourses($provider_id, $programme_id);

      if (empty($this->courseCollection)) {
        $this->messenger->addWarning($this->t('Missing course data!'));
      }
      else {
        $course_table = $this->dataFormatter
          ->courseCollectionTable($this->courseCollection);

        $form['course'] = [
          '#type' => 'details',
          '#title' => $this->t('Course collection data')
        ];

        $form['course']['data'] = [
          '#type' => 'markup',
          '#markup' => $course_table
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['import_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import all'),
      '#attributes' => [
        'class' => [
          'button--primary',
        ]
      ],
    ];

    $form['actions']['import'] = [
      '#type' => 'submit',
      '#submit' => ['::importProgramme'],
      '#value' => $this->t('Import Programme'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Prevent the messenger service from rendering the messages again.
    $this->messenger->deleteAll();

    $temp_store_key = $form_state->getValue('programme_tempstore');

    $form_state->setRedirect('occapi_entities_bridge.import_programme_courses',[
      'tempstore' => $temp_store_key
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function importProgramme(array &$form, FormStateInterface $form_state) {
    // Prevent the messenger service from rendering the messages again.
    $this->messenger->deleteAll();

    $temp_store_key = $form_state->getValue('programme_tempstore');

    $form_state->setRedirect('occapi_entities_bridge.import_programme',[
      'tempstore' => $temp_store_key
    ]);
  }

}
