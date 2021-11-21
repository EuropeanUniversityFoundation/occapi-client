<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_entities\Form\ProgrammeForm;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the programme entity API form.
 */
class ProgrammeApiForm extends ProgrammeForm {

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->importManager = $container->get('occapi_entities_bridge.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'programme_api_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = [];

    $remote_id  = $this->entity->get(Manager::REMOTE_ID)->value;
    $remote_url = $this->entity->get(Manager::REMOTE_URL)->value;

    if (! empty($remote_id)) {
      $header_markup = $this->importManager
        ->formatRemoteId($remote_id, $remote_url);

      $form['header'] = [
        '#type' => 'markup',
        '#markup' => $header_markup
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = [];

    return $element;
  }

}
