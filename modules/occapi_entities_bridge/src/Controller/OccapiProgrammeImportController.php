<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\occapi_client\OccapiProviderManager;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiProgrammeImportController extends ControllerBase {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * OCCAPI provider manager service.
   *
   * @var \Drupal\occapi_client\OccapiProviderManager
   */
  protected $providerManager;

  /**
   * Constructs an OccapiProgrammeImportController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   * @param \Drupal\occapi_client\OccapiProviderManager $provider_manager
   *   The OCCAPI provider manager service.

   */
  public function __construct(
    MessengerInterface $messenger,
    OccapiImportManager $import_manager,
    OccapiProviderManager $provider_manager
  ) {
    $this->messenger = $messenger;
    $this->importManager = $import_manager;
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('occapi_entities_bridge.manager'),
      $container->get('occapi_client.manager'),
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    dpm($this);

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  /**
   * Automatically imports a Programme.
   *
   * @param string $tempstore
   *   The TempStore key.
   *
   * @return array
   *   An array of [id => Drupal\occapi_entities\Entity\Programme]
   */
  public function import($tempstore) {
    $programme = $this->importManager
      ->getProgramme($tempstore);

    if (!empty($programme)) {
      foreach ($programme as $id => $value) {
        $params = [OccapiImportManager::PROGRAMME_ENTITY => $id];
      }
      $route = 'entity.' . OccapiImportManager::PROGRAMME_ENTITY . '.canonical';
      return $this->redirect($route, $params);
    }

    return $this->build();
  }

  /**
   * Automatically imports a Programme.
   *
   * @param string $tempstore
   *   The TempStore key.
   *
   * @return array
   *   An array of [id => Drupal\occapi_entities\Entity\Programme]
   */
  public function importCourses($tempstore) {
    return $this->build();

  }

}
