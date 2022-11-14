<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles OCCAPI import responses.
 */
class OccapiImportController extends ControllerBase {

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   */
  public function __construct(
    OccapiImportManager $import_manager
  ) {
    $this->importManager     = $import_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('occapi_entities_bridge.manager')
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
  public function execute(string $temp_store_key): RedirectResponse {
    dpm($this);

    return $this->redirect('occapi_entities_bridge.select');
  }

}
