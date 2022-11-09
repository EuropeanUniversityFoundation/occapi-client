<?php

namespace Drupal\occapi_client\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\OccapiProviderManager as Manager;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OCCAPI provider form.
 *
 * @property \Drupal\occapi_client\OccapiProviderInterface $entity
 */
class OccapiProviderForm extends EntityForm {

  const JSONAPI_TYPE_HEI = OccapiTempStoreInterface::JSONAPI_TYPE_HEI;

  const PARAM_PROVIDER = OccapiTempStoreInterface::PARAM_PROVIDER;
  const PARAM_FILTER_TYPE = OccapiTempStoreInterface::PARAM_FILTER_TYPE;
  const PARAM_FILTER_ID = OccapiTempStoreInterface::PARAM_FILTER_ID;
  const PARAM_RESOURCE_TYPE = OccapiTempStoreInterface::PARAM_RESOURCE_TYPE;
  const PARAM_RESOURCE_ID = OccapiTempStoreInterface::PARAM_RESOURCE_ID;


  /**
   * JSON data fetcher service.
   *
   * @var \Drupal\occapi_client\JsonDataFetcher
   */
  protected $jsonDataFetcher;

  /**
   * Shared TempStore manager.
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
    $instance->jsonDataFetcher = $container->get('occapi_client.fetch');
    $instance->occapiTempStore = $container->get('occapi_client.tempstore');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the OCCAPI provider.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\occapi_client\Entity\OccapiProvider::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['api_params'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API parameters'),
      '#tree' => FALSE,
    ];

    $description = $this->t('@resource URL containing @links links.', [
      '@resource' => $this->t('Institution %hei resource', ['%hei' => 'hei']),
      '@links' => $this->t('%ounit, %programme and/or %course', [
        '%ounit' => 'ounit',
        '%programme' => 'programme',
        '%course' => 'course',
      ])
    ]);

    $form['api_params']['hei_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Institution ID'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('hei_id'),
      '#description' => $this->t('Format: %format', [
        '%format' => 'domain.tld'
      ]),
      '#required' => TRUE,
    ];

    $form['api_params']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('base_url'),
      '#description' => $description . '<br />' . $this->t('Format: %format', [
        '%format' => 'https://example.com/occapi/v1/hei/domain.tld'
      ]),
      '#required' => TRUE,
    ];

    $form['api_params']['ounit_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter by Organizational Unit'),
      '#default_value' => $this->entity->get('ounit_filter') ?? TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the OCCAPI provider.'),
    ];

    $form['refresh'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Refresh temporary storage on Save'),
      '#default_value' => FALSE,
      '#return_value' => TRUE,
    ];

    if (empty($this->entity->id())) {
      $form['refresh']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $endpoint = $form_state->getValue('base_url');

    $code = $this->jsonDataFetcher
      ->getResponseCode($endpoint);

    if ($code !== 200) {
      $message = $this->t('Failed to fetch Institution data from %endpoint.', [
        '%endpoint' => $endpoint
      ]);
      $form_state->setErrorByName('base_url', $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $endpoint = $form_state->getValue('base_url');
    $refresh = $form_state->getValue('refresh');

    if ($refresh && !empty($endpoint)) {
      $provider_id = $form_state->getValue('id');
      $hei_id = $form_state->getValue('hei_id');

      $temp_store_params = [
        self::PARAM_PROVIDER => $provider_id,
        self::PARAM_FILTER_TYPE => NULL,
        self::PARAM_FILTER_ID => NULL,
        self::PARAM_RESOURCE_TYPE => self::JSONAPI_TYPE_HEI,
        self::PARAM_RESOURCE_ID => $hei_id,
      ];

      $temp_store_key = $this->occapiTempStore
        ->keyFromParams($temp_store_params);

      $json_data = $this->jsonDataFetcher
        ->load($temp_store_key, $endpoint, TRUE);
    }

    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new OCCAPI provider %label.', $message_args)
      : $this->t('Updated OCCAPI provider %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
