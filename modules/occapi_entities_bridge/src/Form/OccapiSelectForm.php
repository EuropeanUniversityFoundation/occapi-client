<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataProcessorInterface;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities_bridge\OccapiImportManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OCCAPI entities select form.
 */
class OccapiSelectForm extends FormBase {

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
   * @var \Drupal\occapi_entities_bridge\OccapiImportManagerInterface
   */
  protected $importManager;

  /**
   * the JSON data processor.
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
    return 'occapi_entities_bridge_occapi_select';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->importManager->checkBypassPermission($this->currentUser);

    // Load all enabled OCCAPI providers.
    $providers = $this->providerManager->getEnabledProviders();

    // Build a select element with the provider list.
    $provider_titles = [];

    foreach ($providers as $id => $provider) {
      $title = $provider->label() . ' (' . $provider->heiId() . ')';
      $provider_titles[$id] = $title;
    }

    // Build the form header with the AJAX components.
    $form['header'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select a Programme to import'),
      '#prefix' => '<div id="selectHeader">',
      '#suffix' => '</div>',
      '#weight' => '-10'
    ];

    $form['header']['provider_select'] = [
      '#type' => 'select',
      '#title' => $this->t('OCCAPI providers'),
      '#options' => $provider_titles,
      '#default_value' => '',
      '#empty_value' => '',
      '#ajax' => [
        'callback' => '::getProgrammeList',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'selectHeader',
      ],
      '#attributes' => [
        'name' => 'provider_select',
      ],
      '#weight' => '-9',
    ];

    $form['header']['programme_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Programmes'),
      '#options' => [],
      '#default_value' => '',
      '#empty_value' => '',
      '#ajax' => [
        'callback' => '::previewProgramme',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'preview',
      ],
      '#validated' => TRUE,
      '#states' => [
        'disabled' => [
          ':input[name="provider_select"]' => ['value' => ''],
        ],
      ],
      '#weight' => '-8',
    ];

    $this->emptyData = $this->t('Nothing to display.');

    $form['data'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Data'),
      '#weight' => '-7',
    ];

    $form['data']['empty'] = [
      '#type' => 'item',
      '#markup' => '<p><em>' . $this->emptyData . '</em></p>',
      '#states' => [
        'visible' => [
          ':input[name="provider_select"]' => ['value' => ''],
        ],
      ],
    ];

    $form['data']['preview'] = [
      '#type' => 'item',
      '#markup' => '<p><em>' . $this->emptyData . '</em></p>',
      '#prefix' => '<div id="preview">',
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [
          ':input[name="provider_select"]' => ['value' => ''],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => '-6',
    ];

    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Load Import form'),
      '#attributes' => [
        'class' => [
          'button--primary',
        ]
      ],
      '#states' => [
        'disabled' => [
          ':input[name="programme_select"]' => ['value' => ''],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $provider_id = $form_state->getValue('provider_select');
    $programme_id = $form_state->getValue('programme_select');

    $temp_store_params = [
      OccapiTempStoreInterface::PARAM_PROVIDER => $provider_id,
      OccapiTempStoreInterface::PARAM_FILTER_TYPE => NULL,
      OccapiTempStoreInterface::PARAM_FILTER_ID => NULL,
      OccapiTempStoreInterface::PARAM_RESOURCE_TYPE => self::TYPE_PROGRAMME,
      OccapiTempStoreInterface::PARAM_RESOURCE_ID => $programme_id,
    ];

    $temp_store_key = $this->occapiTempStore->keyFromParams($temp_store_params);

    $form_state->setRedirect('occapi_entities_bridge.import',[
      'temp_store_key' => $temp_store_key
    ]);
  }

  /**
  * AJAX callback to build the Programme select list.
  */
  public function getProgrammeList(array $form, FormStateInterface $form_state) {
    // Prevent the messenger service from rendering the messages again.
    $this->messenger->deleteAll();

    $provider_id = $form_state->getValue('provider_select');

    $options = ['' => '- None -'];

    if ($provider_id) {
      $provider = $this->providerManager->getProvider($provider_id);
      $filtered = $provider->get('ounit_filter');

      // Fetch Institution data.
      $this->heiResource = $this->dataLoader->loadInstitution($provider_id);
      $hei_links = $this->heiResource[self::LINKS_KEY];
      $has_ounits = array_key_exists(self::TYPE_OUNIT, $hei_links);
      $has_programmes = array_key_exists(self::TYPE_PROGRAMME, $hei_links);

      // Fetch Programme data from Institution resource links.
      if (!$filtered && $has_programmes) {
        $programme_collection = $this->dataLoader->loadProgrammes($provider_id);
        $options += $this->jsonDataProcessor
          ->getResourceTitles($programme_collection);
      }

      // Fetch Programme data from all available OUnit resource links.
      if ($filtered && $has_ounits) {
        $programme_collection = [self::DATA_KEY => []];

        $ounit_collection = $this->dataLoader->loadOunits($provider_id);
        $ounit_data = $ounit_collection[self::DATA_KEY];

        foreach ($ounit_data as $i => $resource) {
          $ounit_id = $this->jsonDataProcessor->getResourceId($resource);
          $ounit_title = $this->jsonDataProcessor->getResourceTitle($resource);
          $ounit_label = $ounit_title . ' (' . $ounit_id . ')';

          $ounit_resource = $this->dataLoader
            ->loadOunit($provider_id, $ounit_id);
          $ounit_links = $ounit_resource[self::LINKS_KEY];

          if (array_key_exists(self::TYPE_PROGRAMME, $ounit_links)) {
            $ounit_programmes = $this->dataLoader
              ->loadOunitProgrammes($provider_id, $ounit_id);

            if (!empty($ounit_programmes[self::DATA_KEY])) {
              $partial_data = $ounit_programmes[self::DATA_KEY];
              $programme_collection[self::DATA_KEY] += $partial_data;

              $programme_titles = $this->jsonDataProcessor
                ->getResourceTitles($ounit_programmes);

              $options[$ounit_label] = $programme_titles;
            }
          }
        }
      }
    }

    $form['header']['programme_select']['#options'] = $options;
    return $form['header'];
  }

  /**
  * Fetch the data and build Programme preview.
  */
  public function previewProgramme(array $form, FormStateInterface $form_state) {
    // Prevent the messenger service from rendering the messages again.
    $this->messenger->deleteAll();

    $markup = '<p><em>' . $this->emptyData . '</em></p>';

    $provider_id = $form_state->getValue('provider_select');

    $programme_id = $form_state->getValue('programme_select');

    if (!empty($provider_id) && !empty($programme_id)) {
      $programme_resource = $this->dataLoader
        ->loadProgramme($provider_id, $programme_id);
      $programme_links = $programme_resource[self::LINKS_KEY];
      $programme_markup = $this->dataFormatter
        ->programmeResourceTable($programme_resource);

      $markup = '<h3>' . $this->t('Programme data') . '</h3>';
      $markup .= $programme_markup;

      if (\array_key_exists(self::TYPE_COURSE, $programme_links)) {
        $course_collection = $this->dataLoader
          ->loadProgrammeCourses($provider_id, $programme_id);

        $course_markup = $this->dataFormatter
          ->courseCollectionTable($course_collection);

        $markup .= '<h3>' . $this->t('Course data') . '</h3>';
        $markup .= $course_markup;

        $target = '#selectHeader';
        $link_text = $this->t('Back to top');

        $markup .= '<p><a href="' . $target . '">' . $link_text . '</a></p>';
      }
    }

    $form['data']['preview']['#markup'] = $markup;

    return $form['data']['preview'];
  }

}
