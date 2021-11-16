<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ewp_institutions_get\InstitutionManager;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\Entity\OccapiProvider;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiProviderManager;

/**
 * Service for managing OCCAPI entity imports.
 */
class OccapiImportManager {

  use StringTranslationTrait;

  // Machine names of OCCAPI entity types.
  const PROGRAMME_ENTITY = 'programme';
  const COURSE_ENTITY    = 'course';

  // Machine names of OCCAPI extra fields.
  const REMOTE_ID  = 'remote_id';
  const REMOTE_URL = 'remote_url';

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
  * Data formatting service.
  *
  * @var \Drupal\occapi_client\DataFormatter
  */
  protected $dataFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
  * EWP Institutions manager service.
  *
  * @var \Drupal\ewp_institutions_get\InstitutionManager
  */
  protected $heiManager;

  /**
  * JSON data fetching service.
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
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\occapi_client\DataFormatter $data_formatter
   *   Data formatting service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ewp_institutions_get\InstitutionManager $hei_manager
   *   EWP Institutions manager service.
   * @param \Drupal\occapi_client\JsonDataFetcher $json_data_fetcher
   *   JSON data fetching service.
   * @param \Drupal\occapi_client\JsonDataProcessor $json_data_processor
   *   JSON data processing service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\occapi_client\OccapiProviderManager $provider_manager
   *   The provider manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
      ConfigFactoryInterface $config_factory,
      DataFormatter $data_formatter,
      EntityTypeManagerInterface $entity_type_manager,
      InstitutionManager $hei_manager,
      JsonDataFetcher $json_data_fetcher,
      JsonDataProcessor $json_data_processor,
      LoggerChannelFactoryInterface $logger_factory,
      MessengerInterface $messenger,
      OccapiProviderManager $provider_manager,
      TranslationInterface $string_translation
  ) {
    $this->configFactory      = $config_factory;
    $this->dataFormatter      = $data_formatter;
    $this->entityTypeManager  = $entity_type_manager;
    $this->heiManager         = $hei_manager;
    $this->jsonDataFetcher    = $json_data_fetcher;
    $this->jsonDataProcessor  = $json_data_processor;
    $this->logger             = $logger_factory->get('occapi_entities_bridge');
    $this->messenger          = $messenger;
    $this->providerManager    = $provider_manager;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Validate the existence of an Institution entity.
   *
   * @param string $hei_id
   *   Institution ID to validate.
   *
   * @return array $result
   *   An array containing the validation status and related message.
   */
  public function validateInstitution(string $hei_id): array {
    // Check if an entity with the same hei_id already exists
    $exists = $this->heiManager
      ->getInstitution($hei_id);

    if (!empty($exists)) {
      foreach ($exists as $id => $hei) {
        $link = $hei->toLink();
        $renderable = $link->toRenderable();
      }

      $message = $this->t('Institution with ID <code>@hei_id</code> already exists: @link', [
        '@hei_id' => $hei_id,
        '@link' => render($renderable),
      ]);

      $status = TRUE;
    }
    else {
      $message = $this->t('Institution with ID <code>@hei_id</code> does not exist!', [
        '@hei_id' => $hei_id,
      ]);

      $status = FALSE;
    }

    $result['status'] = $status;
    $result['message'] = $message;

    return $result;
  }
}
