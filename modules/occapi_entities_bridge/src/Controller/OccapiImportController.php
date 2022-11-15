<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Handles OCCAPI import responses.
 */
class OccapiImportController extends ControllerBase {

  use StringTranslationTrait;

  const ROUTE_IMPORT_FORM = 'occapi_entities_bridge.import';

  /**
   * A router implementation which does not check access.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $accessUnawareRouter;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * The constructor.
   *
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $access_unaware_router
   *   A router implementation which does not check access.
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    UrlMatcherInterface $access_unaware_router,
    OccapiImportManager $import_manager,
    OccapiTempStoreInterface $occapi_tempstore,
    TranslationInterface $string_translation
  ) {
    $this->accessUnawareRouter = $access_unaware_router;
    $this->importManager       = $import_manager;
    $this->occapiTempStore     = $occapi_tempstore;
    $this->stringTranslation   = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.no_access_checks'),
      $container->get('occapi_entities_bridge.manager'),
      $container->get('occapi_client.tempstore'),
      $container->get('string_translation')
    );
  }

  /**
   * executes data import operations from a TempStore key.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return RedirectResponse
   */
  public function execute(Request $request, string $temp_store_key): RedirectResponse {
    $referer = $request->headers->get('referer');
    $result = $this->accessUnawareRouter->match($referer);
    $params = ['temp_store_key' => $temp_store_key];

    if ($referer !== self::ROUTE_IMPORT_FORM) {
      $validated = $this->importManager
        ->validateImportPrerequisites($temp_store_key);
    }

    if ($validated) {
      // Get the parameters from the TempStore key.
      $temp_store_params = $this->occapiTempStore
        ->paramsFromKey($temp_store_key);

      $data = $this->importManager->loadData($temp_store_key);

      $changes = $this->importManager->importData($data);
    }

    return $this->redirect(self::ROUTE_IMPORT_FORM, $params);
  }

}
