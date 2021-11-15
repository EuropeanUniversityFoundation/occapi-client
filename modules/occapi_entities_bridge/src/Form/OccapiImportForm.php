<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\Url;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\JsonDataProcessor as Json;
use Drupal\occapi_client\OccapiProviderManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OCCAPI entities import form.
 */
class OccapiImportForm extends FormBase {

  /**
   * OCCAPI provider.
   *
   * @var \Drupal\occapi_client\Entity\OccapiProvider
   */
  protected $provider;

  /**
   * OCCAPI Institution resource.
   *
   * @var array
   */
  protected $heiResource;

  /**
   * OCCAPI Organizational Unit collection.
   *
   * @var array
   */
  protected $ounitCollection;

  /**
   * OCCAPI Programme collection.
   *
   * @var array
   */
  protected $programmeCollection;

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
   * JSON data fetcher service.
   *
   * @var \Drupal\occapi_client\JsonDataFetcher
   */
  protected $jsonDataFetcher;

  /**
   * JSON data processing service.
   *
   * @var \Drupal\occapi_client\JsonDataProcessor
   */
  protected $jsonDataProcessor;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
    $instance->dataFormatter        = $container->get('occapi_client.format');
    $instance->jsonDataFetcher      = $container->get('occapi_client.fetch');
    $instance->jsonDataProcessor    = $container->get('occapi_client.json');
    $instance->loggerFactory        = $container->get('logger.factory');
    $instance->logger = $instance->loggerFactory->get('occapi_entities_bridge');
    $instance->providerManager      = $container->get('occapi_client.manager');
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();

    // Give a user with permission the opportunity to add an entity manually
    if ($user->hasPermission('bypass import occapi entities')) {
      $add_programme_link = Link::fromTextAndUrl(t('add a new Programme'),
        Url::fromRoute('entity.programme.add_form'))->toString();
      $add_course_link = Link::fromTextAndUrl(t('add a new Course'),
        Url::fromRoute('entity.course.add_form'))->toString();

      $warning = $this->t('You can bypass this form and @add_programme or @add_course manually.',[
        '@add_programme' => $add_programme_link,
        '@add_course' => $add_course_link
      ]);

      $form['messages'] = [
        '#type' => 'markup',
        '#markup' => $warning,
        '#weight' => '-20'
      ];
    }

    // Load all available OCCAPI providers.
    $providers = $this->providerManager
      ->getProviders();

    // Build a select element with the provider list.
    $provider_titles = [];

    foreach ($providers as $id => $provider) {
      $title = $provider->label();
      $title .= ' ('. $provider->get('hei_id') .')';
      $provider_titles[$id] = $title;
    }

    // Build the form header with the AJAX components.
    $form['header'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select a Programme to import'),
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
        'wrapper' => 'programmeSelect',
      ],
      '#attributes' => [
        'name' => 'provider_select',
      ],
      '#weight' => '-9',
    ];

    $form['header']['programme_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Programmes'),
      '#prefix' => '<div id="programmeSelect">',
      '#suffix' => '</div>',
      '#options' => [],
      '#default_value' => '',
      '#empty_value' => '',
      '#ajax' => [
        'callback' => '::previewProgramme',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'data',
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
      '#prefix' => '<div id="data">',
      '#suffix' => '</div>',
      '#weight' => '-7',
    ];

    $form['data']['status'] = [
      '#type' => 'hidden',
      '#value' => '',
      '#attributes' => [
        'name' => 'data_status',
      ],
    ];

    $form['data']['preview'] = [
      '#type' => 'markup',
      '#markup' => '<p><em>' . $this->emptyData . '</em></p>',
    ];

    $target = '#programmeSelect';
    $link_text = $this->t('Back to top');

    $form['data']['back_to_top'] = [
      '#type' => 'markup',
      '#markup' => '<p><a href="' . $target . '">' . $link_text . '</a></p>',
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
      '#states' => [
        'disabled' => [
          ':input[name="programme_select"]' => ['value' => ''],
        ],
        'visible' => [
          ':input[name="data_status"]' => ['value' => ''],
        ],
      ],
    ];

    // $form['actions']['load'] = [
    //   '#type' => 'submit',
    //   '#submit' => ['::loadImportForm'],
    //   '#value' => $this->t('Load Import form'),
    //   '#states' => [
    //     'disabled' => [
    //       ':input[name="hei_select"]' => ['value' => ''],
    //     ],
    //     'visible' => [
    //       ':input[name="data_status"]' => ['value' => ''],
    //     ],
    //   ],
    // ];

    // dpm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // $index_item = $form_state->getValue('index_select');
    // $hei_id = $form_state->getValue('hei_select');
    //
    // $form_state->setRedirect('entity.hei.auto_import',[
    //   'index_key' => $index_item,
    //   'hei_key' => $hei_id
    // ]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadImportForm(array &$form, FormStateInterface $form_state) {
    // $index_item = $form_state->getValue('index_select');
    // $hei_id = $form_state->getValue('hei_select');
    //
    // $form_state->setRedirect('entity.hei.import_form',[
    //   'index_key' => $index_item,
    //   'hei_key' => $hei_id
    // ]);
  }

  /**
  * Fetch the Institution data and build the Programme select list.
  */
  public function getProgrammeList(array $form, FormStateInterface $form_state) {
    $provider_id = $form_state->getValue('provider_select');

    if ($provider_id) {
      $options = ['' => '- None -'];

      $this->provider = $this->providerManager
        ->getProvider($provider_id);

      // Fetch Institution data.
      $this->heiResource = $this->providerManager
        ->loadInstitution($provider_id);

      // Fetch Programme data from Institution resource links.
      if (
        ! $this->provider->get('ounit_filter') &&
        array_key_exists(
          Manager::PROGRAMME_KEY,
          $this->heiResource[Json::LINKS_KEY]
        )
      ) {
        $this->programmeCollection = $this->providerManager
          ->loadProgrammes($provider_id);

        $options += $this->jsonDataProcessor
          ->getTitles($this->programmeCollection);
      }

      // Fetch Programme data from all available OUnit resource links.
      if (
        $this->provider->get('ounit_filter') &&
        array_key_exists(
          Manager::OUNIT_KEY,
          $this->heiResource[Json::LINKS_KEY]
        )
      ) {
        $this->programmeCollection = [Json::DATA_KEY => []];

        $this->ounitCollection = $this->providerManager
          ->loadOunits($provider_id);

        foreach ($this->ounitCollection[Json::DATA_KEY] as $i => $resource) {
          $ounit_id     = $this->jsonDataProcessor->getId($resource);
          $ounit_title  = $this->jsonDataProcessor->getTitle($resource);
          $ounit_label  = $ounit_title . ' (' . $ounit_id . ')';

          $ounit_resource   = $this->providerManager
            ->loadOunit($provider_id, $ounit_id);

          if (
            array_key_exists(
              Manager::PROGRAMME_KEY,
              $ounit_resource[Json::LINKS_KEY]
            )
          ) {
            $ounit_programmes = $this->providerManager
              ->loadOunitProgrammes($provider_id, $ounit_id);

            if (! empty($ounit_programmes[Json::DATA_KEY])) {
              $partial_data = $ounit_programmes[Json::DATA_KEY];
              $this->programmeCollection[Json::DATA_KEY] += $partial_data;

              $programme_titles = $this->jsonDataProcessor
                ->getTitles($ounit_programmes);

              $options[$ounit_label] = $programme_titles;
            }
          }
        }

      }

      $form['header']['programme_select']['#options'] = $options;
      return $form['header']['programme_select'];
    }

  }

  /**
  * Fetch the data and preview Programme
  */
  public function previewProgramme(array $form, FormStateInterface $form_state) {
    $markup ='';

    $provider_id = $form_state->getValue('provider_select');

    $programme_id = $form_state->getValue('programme_select');

    $form['data']['status']['#value'] = $programme_id;
    $form['data']['preview']['#markup'] = $this->emptyData;

    if (! empty($provider_id) && !empty($programme_id)) {
      $programme_resource = $this->providerManager
        ->loadProgramme($provider_id, $programme_id);

      $programme_markup = $this->dataFormatter
        ->programmeResourceTable($programme_resource);

      $markup .= '<h3>' . $this->t('Programme data') . '</h3>';
      $markup .= $programme_markup;

      if (
        \array_key_exists(
          Manager::COURSE_KEY,
          $programme_resource[Json::LINKS_KEY]
        )
      ) {
        $course_collection = $this->providerManager
          ->loadProgrammeCourses($provider_id, $programme_id);

        $course_markup = $this->dataFormatter
          ->collectionTable($course_collection);

        $markup .= '<h3>' . $this->t('Course data') . '</h3>';
        $markup .= $course_markup;
      }
    }

    $form['data']['preview']['#markup'] = $markup;

    return $form['data'];
  }

}
