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
use Drupal\occapi_entities_bridge\OccapiImportManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OCCAPI entities import form.
 */
class OccapiImportForm extends FormBase {

  const PARAM_PROVIDER = OccapiTempStoreInterface::PARAM_PROVIDER;
  const PARAM_FILTER_TYPE = OccapiTempStoreInterface::PARAM_FILTER_TYPE;
  const PARAM_FILTER_ID = OccapiTempStoreInterface::PARAM_FILTER_ID;
  const PARAM_RESOURCE_TYPE = OccapiTempStoreInterface::PARAM_RESOURCE_TYPE;
  const PARAM_RESOURCE_ID = OccapiTempStoreInterface::PARAM_RESOURCE_ID;
  const PARAM_EXTERNAL = OccapiTempStoreInterface::PARAM_EXTERNAL;

  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  const DATA_KEY = JsonDataSchemaInterface::JSONAPI_DATA;
  const LINKS_KEY = JsonDataSchemaInterface::JSONAPI_LINKS;
  const SELF_KEY = JsonDataSchemaInterface::JSONAPI_SELF;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  protected $currentUser;

  /**
   * Individual resource type.
   *
   * @var string
   */
  protected $resourceType;

  /**
   * Individual resource ID.
   *
   * @var string
   */
  protected $resourceId;

  /**
   * Resource type in collection.
   *
   * @var string
   */
  protected $collectionType;

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
    $validated = $this->importManager->validateImportPrerequisites($temp_store_key);

    if (!$validated) { return $form; }

    $this->importManager->checkBypassPermission($this->currentUser);

    // Validate the TempStore key.
    $validated = $this->occapiTempStore->validateTempstoreKey($temp_store_key);

    if (!$validated) { return $form; }

    // Start building the form by setting the reference values.
    $form['resource_tempstore'] = [
      '#type' => 'value',
      '#value' => NULL,
      '#attributes' => [
        '#name' => 'resource_tempstore',
      ],
    ];

    $form['collection_tempstore'] = [
      '#type' => 'value',
      '#value' => NULL,
      '#attributes' => [
        '#name' => 'collection_tempstore',
      ],
    ];

    // Parse the TempStore parameters.
    $temp_store_params = $this->occapiTempStore->paramsFromKey($temp_store_key);

    $provider_id = $temp_store_params[self::PARAM_PROVIDER];
    $filter_type = $temp_store_params[self::PARAM_FILTER_TYPE];
    $filter_id = $temp_store_params[self::PARAM_FILTER_ID];
    $resource_type = $temp_store_params[self::PARAM_RESOURCE_TYPE];
    $resource_id = $temp_store_params[self::PARAM_RESOURCE_ID];

    if (!empty($resource_id)) {
      // Presence of a resource ID indicates a resource TempStore.
      $this->resourceType = $resource_type;
      $this->resourceId = $resource_id;
      $form['resource_tempstore']['#value'] = $temp_store_key;

      if ($resource_type !== self::TYPE_COURSE) {
        // Get collection links from the resource.
        $links = $this->getLinks($provider_id, $resource_type, $resource_id);

        if (\count($links) === 1) {
          $link_type = \array_keys($links)[0];

          $child_temp_store_params = [
            self::PARAM_PROVIDER => $provider_id,
            self::PARAM_FILTER_TYPE => $resource_type,
            self::PARAM_FILTER_ID => $resource_id,
            self::PARAM_RESOURCE_TYPE => $link_type,
            self::PARAM_RESOURCE_ID => NULL,
            self::PARAM_EXTERNAL => NULL,
          ];

          $child_temp_store_key = $this->occapiTempStore
            ->keyFromParams($child_temp_store_params);

          // Validate the child TempStore key.
          $validated = $this->occapiTempStore
            ->validateTempstoreKey($child_temp_store_key);

          if ($validated) {
            // The derived TempStore is the collection.
            $this->collectionType = $link_type;
            $form['collection_tempstore']['#value'] = $child_temp_store_key;
          }
        }
        else {
          // TODO: Handle edge cases.
        }
      }
    }
    else {
      // Otherwise it is a collection TempStore.
      $this->collectionType = $resource_type;
      $form['collection_tempstore']['#value'] = $temp_store_key;
    }

    if (!empty($filter_id)) {
      // Presence of a filter indicates a filtered collection TempStore.
      $filter_temp_store_params = [
        self::PARAM_PROVIDER => $provider_id,
        self::PARAM_FILTER_TYPE => NULL,
        self::PARAM_FILTER_ID => NULL,
        self::PARAM_RESOURCE_TYPE => $filter_type,
        self::PARAM_RESOURCE_ID => $filter_id,
        self::PARAM_EXTERNAL => NULL,
      ];

      $filter_temp_store_key = $this->occapiTempStore
        ->keyFromParams($filter_temp_store_params);

      // Validate the filter TempStore key.
      $validated = $this->occapiTempStore
        ->validateResourceTempstore($filter_temp_store_key, $filter_type);

      if ($validated) {
        // The filter of the collection is the individual resource.
        $this->resourceType = $filter_type;
        $this->resourceId = $filter_id;
        $form['resource_tempstore']['#value'] = $filter_temp_store_key;
      }
    }

    $has_resource = !empty($form['resource_tempstore']['#value']);

    if ($has_resource) {
      // Load resource data.
      $resource = $this->dataLoader
        ->loadResource($provider_id, $this->resourceType, $this->resourceId);

      $resource_table = $this->dataFormatter->resourceTable($resource);

      $form['resource'] = [
        '#type' => 'details',
        '#title' => $this->t('Resource data'),
        '#open' => TRUE,
      ];

      $form['resource']['data'] = [
        '#type' => 'markup',
        '#markup' => $resource_table,
      ];
    }

    $has_collection = !empty($form['collection_tempstore']['#value']);

    if ($has_collection) {
      if (!empty($this->resourceId)) {
        // Load filtered collection data.
        $collection = $this->dataLoader
          ->loadFilteredCollection($provider_id, $this->resourceType, $this->resourceId, $this->collectionType);
      }
      else {
        // Load collection data.
        $collection = $this->dataLoader
          ->loadCollection($provider_id, $this->collectionType);
      }

      $collection_table = $this->dataFormatter->collectionTable($collection);

      $form['collection'] = [
        '#type' => 'details',
        '#title' => $this->t('Collection data'),
        '#open' => TRUE,
      ];

      $form['collection']['data'] = [
        '#type' => 'markup',
        '#markup' => $collection_table,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if ($has_resource && $this->resourceType !== self::TYPE_OUNIT) {
      $form['actions']['import_resource'] = [
        '#type' => 'submit',
        '#submit' => ['::importResource'],
        '#value' => $this->t('Import @type resource', [
          '@type' => $this->resourceType
        ]),
        '#attributes' => [
          'class' => [
            'button--primary',
          ]
        ],
      ];
    }

    if ($has_collection && $this->collectionType !== self::TYPE_OUNIT) {
      $form['actions']['import'] = [
        '#type' => 'submit',
        '#submit' => ['::importCollection'],
        '#value' => $this->t('Import @type collection', [
          '@type' => $this->collectionType
        ]),
        '#attributes' => [
          'class' => [
            'button--primary',
          ]
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function importResource(array &$form, FormStateInterface $form_state) {
    // Prevent the messenger service from rendering the messages again.
    $this->messenger->deleteAll();

    $temp_store_key = $form_state->getValue('resource_tempstore');

    $form_state->setRedirect('occapi_entities_bridge.import.execute', [
      'temp_store_key' => $temp_store_key
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function importCollection(array &$form, FormStateInterface $form_state) {
    // Prevent the messenger service from rendering the messages again.
    $this->messenger->deleteAll();

    $temp_store_key = $form_state->getValue('collection_tempstore');

    $form_state->setRedirect('occapi_entities_bridge.import.execute', [
      'temp_store_key' => $temp_store_key
    ]);
  }

  /**
   * Helper method to find collection links in a resource.
   */
  protected function getLinks(string $provider_id, string $resource_type, string $resource_id) {
    $links = [];

    $resource = $this->dataLoader
      ->loadResource($provider_id, $resource_type, $resource_id);

    if (empty($resource)) {
      $this->messenger->addError($this->t('Missing resource data!'));
      return [];
    }

    foreach ($resource[self::LINKS_KEY] as $link_type => $link_uri) {
      if ($link_type !== self::SELF_KEY) {
        $links[$link_type] = $link_uri;
      }
    }

    return $links;
  }

}
