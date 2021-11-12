<?php

namespace Drupal\occapi_entities_bridge\Form;

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
   * OCCAPI programme to import.
   *
   * @var string
   */
  protected $programme;

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

    // dpm($this);

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
    ];

    // IF ounit_filter THEN build a select element with the ounit list.

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

    dpm($form);

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

}
