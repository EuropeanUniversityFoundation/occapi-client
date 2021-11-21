<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_entities\Form\ProgrammeForm;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;

/**
 * Form controller for the programme entity API form.
 */
class ProgrammeApiForm extends ProgrammeForm {

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

    dpm($remote_id);
    dpm($remote_url);

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
