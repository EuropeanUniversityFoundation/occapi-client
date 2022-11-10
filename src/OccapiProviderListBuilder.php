<?php

namespace Drupal\occapi_client;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of OCCAPI providers.
 */
class OccapiProviderListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['base_url'] = $this->t('Base URL');
    $header['hei_id'] = $this->t('Institution ID');
    $header['ounit_filter'] = $this->t('Filter by OUnit');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\occapi_client\OccapiProviderInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['base_url'] = $entity->baseUrl();
    $row['hei_id'] = $entity->get('hei_id');
    $row['ounit_filter'] = $entity->get('ounit_filter') ? $this->t('Yes') : $this->t('No');
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = $this->getOperations($entity);

    $operations['preview'] = [
        'title' => $this->t('Preview'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('preview-form')),
    ];

    uasort($operations, '\\Drupal\\Component\\Utility\\SortArray::sortByWeightElement');

    $build = [
      '#type' => 'operations',
      '#links' => $operations,
    ];
    return $build;
  }

}
