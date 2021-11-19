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
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiProviderManager;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OCCAPI entities import form.
 */
class OccapiImportForm extends FormBase {

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
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * JSON data processing service.
   *
   * @var \Drupal\occapi_client\JsonDataProcessor
   */
  protected $jsonDataProcessor;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
    $instance->dataFormatter      = $container->get('occapi_client.format');
    $instance->importManager      = $container->get('occapi_entities_bridge.manager');
    $instance->jsonDataProcessor  = $container->get('occapi_client.json');
    $instance->messenger          = $container->get('messenger');
    $instance->providerManager    = $container->get('occapi_client.manager');
    $instance->currentUser        = $container->get('current_user');
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
  public function buildForm(array $form, FormStateInterface $form_state, string $tempstore = NULL) {
    // Give a user with permission the opportunity to add an entity manually.
    if (
      $this->currentUser->hasPermission('create programme') &&
      $this->currentUser->hasPermission('create course') &&
      $this->currentUser->hasPermission('bypass import occapi entities')
    ) {
      $add_programme_link = Link::fromTextAndUrl(t('add a new Programme'),
        Url::fromRoute('entity.programme.add_form'))->toString();
      $add_course_link = Link::fromTextAndUrl(t('add a new Course'),
        Url::fromRoute('entity.course.add_form'))->toString();

      $notice = $this->t('You can bypass this form and @add_programme or @add_course manually.',[
        '@add_programme' => $add_programme_link,
        '@add_course' => $add_course_link
      ]);

      $this->messenger->addMessage($notice);
    }

    // Validate the tempstore parameter.
    $error = $this->providerManager
      ->validateResourceTempstore($tempstore, OccapiProviderManager::PROGRAMME_KEY);

    if ($error) {
      $this->messenger->addError($error);
      return $form;
    }

    // Parse the tempstore parameter to get the OCCAPI provider and its HEI ID.
    $components   = \explode('.', $tempstore);
    $provider_id  = $components[0];
    $programme_id = $components[2];

    $provider = $this->providerManager
      ->getProvider($provider_id);

    $hei_id = $provider->get('hei_id');

    // Check if the Institution is present in the system.
    $result = $this->importManager
      ->validateInstitution($hei_id);

    if (! $result['status']) {
      $this->messenger->addError($result['message']);
      return $form;
    }
    else {
      $this->messenger->addMessage($result['message']);
    }

    // Load Programme data.
    $this->programmeResource = $this->providerManager
      ->loadProgramme($provider_id, $programme_id);

    if (empty($this->programmeResource)) {
      $this->messenger->addError($this->t('Missing programme data!'));
      return $form;
    }

    $programme_table = $this->dataFormatter
      ->programmeResourceTable($this->programmeResource);

    $form['programme_tempstore'] = [
      '#type' => 'value',
      '#value' => $tempstore
    ];

    $form['programme'] = [
      '#type' => 'details',
      '#title' => $this->t('Programme resource data')
    ];

    $form['programme']['data'] = [
      '#type' => 'markup',
      '#markup' => $programme_table
    ];

    // Load Course data.
    if (
      \array_key_exists(
        OccapiProviderManager::COURSE_KEY,
        $this->programmeResource[JsonDataProcessor::LINKS_KEY]
      )
    ) {
      $this->courseCollection = $this->providerManager
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

    $programme_field_table = $this->importManager
      ->fieldTable($this->programmeResource, Manager::PROGRAMME_ENTITY);

    $sample_course = $this->courseCollection[JsonDataProcessor::DATA_KEY][0];

    $course_field_table = $this->importManager
      ->fieldTable($sample_course, Manager::COURSE_ENTITY);

    $form['preview'] = [
      '#type' => 'markup',
      // '#markup' => $programme_field_table,
      // '#markup' => $course_field_table,
      '#markup' => ''
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => '-6',
    ];

    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#attributes' => [
        'class' => [
          'button--primary',
        ]
      ],
    ];

    // dpm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tempstore = $form_state->getValue('programme_tempstore');

    $form_state->setRedirect('occapi_entities_bridge.import_programme',[
      'tempstore' => $tempstore
    ]);
  }

}
