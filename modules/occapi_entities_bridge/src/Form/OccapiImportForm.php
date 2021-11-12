<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataFetcher;
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
   * OCCAPI provider ounit_filter.
   *
   * @var boolean
   */
  protected $ounitFilter = FALSE;

  /**
   * OCCAPI Institution data.
   *
   * @var array
   */
  protected $heiData;

  /**
   * OCCAPI OUnit data.
   *
   * @var array
   */
  protected $ounitData;

  /**
   * OCCAPI Programme data.
   *
   * @var array
   */
  protected $programmeData;

  /**
   * OCCAPI Course data.
   *
   * @var array
   */
  protected $courseData;

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
    $this->logger->notice('Building form...');

    if ($this->provider) {
      $this->ounitFilter = $this->provider->get('ounit_filter');
    } else {
      $this->ounitFilter = FALSE;
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

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('OCCAPI providers'),
      '#options' => $provider_titles,
      '#empty_option' => $this->t('- Select a provider -'),
      '#default_value' => NULL,
      '#ajax' => [
        'callback' => '::heiMarkup',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'hei_markup',
      ],
    ];

    // Display Institution data from the first call to the provider.
    $form['hei_markup'] = [
      '#type' => 'markup',
      '#markup' => '<div id="heiMarkup"></div>'
    ];

    // IF ounit_filter THEN build a select element with the ounit list.
    if ($this->ounitFilter) {
      $form['ounit'] = [
        '#type' => 'select',
        '#title' => $this->t('Organizational Units'),
        '#options' => [],
        '#empty_option' => $this->t('- Select an Organizational Unit -'),
        '#default_value' => NULL,
      ];
    }

    // Check for an existing links key for programmes.

    // Build a select element with the programme list.

    // Display programme resource data.

    // Check for an existing links key for courses.

    // Display course collection data.

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // dpm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
  * AJAX callback to load and display an Institution.
  */
  public function heiMarkup(array &$form, FormStateInterface $form_state) {
    $markup = '';

    $provider_id = $form_state->getValue('provider');

    if ($provider_id) {
      $this->provider = $this->providerManager
        ->getProvider($provider_id);

      // Prepare Institution data.
      $hei_id         = $this->provider->get('hei_id');
      $hei_tempstore  = $provider_id . '.' . Manager::HEI_KEY . '.' . $hei_id;

      $base_url       = $this->provider->get('base_url');
      $hei_endpoint   = $base_url . '/' . Manager::HEI_KEY . '/' . $hei_id;

      $hei_response = $this->jsonDataFetcher
        ->load($hei_tempstore, $hei_endpoint);

      $hei_data = \json_decode($hei_response, TRUE);
      $hei_table = $this->dataFormatter
        ->resourceTable($hei_data);

      $hei_markup = '<p><code>GET ' . $hei_endpoint . '</code></p>';
      $hei_markup .= $hei_table;

      $markup .= $hei_markup;
    }

    $ajax_response = new AjaxResponse();
    $ajax_response
      ->addCommand(new HtmlCommand('#heiMarkup', $markup));
    return $ajax_response;
  }

}
