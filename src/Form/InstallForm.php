<?php

namespace Drupal\pme;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class InstallForm extends FormBase {


  public function __construct(private InstallerStage $stage)
  {
  }

  public function getFormId() {
    return 'pme_install_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Create a text input for project name.
    $form['project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project'),
      '#required' => TRUE,
      '#description' => $this->t('Enter a project machine name or URL.'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    $project_name = $form_state->getValue('project_name');
    // Confirm the input is a valid project name or URL.
    // first check if it is valid project url.

    if (UrlHelper::isValid($project_name)) {
      // Ensure starts with https://drupal.org/project/
      if (strpos($project_name, 'https://www.drupal.org/project/') !== 0) {
        $form_state->setErrorByName('project_name', $this->t('The project URL must start with https://drupal.org/project/.'));
      }
      // Grab the last part of the URL for the project name.
      $project_name = substr($project_name, strlen('https://www.drupal.org/project/'));

    }
    if (!preg_match('/^[a-z0-9-]+$/', $project_name)) {
      $form_state->setErrorByName('project_name', $this->t('The project name must be a valid machine name.'));
    }
    if (!$this->stage->isAvailable()) {
      $form_state->setErrorByName('project_name', $this->t('The project is not available.'));
    }
    $form_state->setValue('project_name', $project_name);

  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $project_name = $form_state->getValue('project_name');
    $package_name = "drupal/$project_name";
    $this->stage->create();
    $this->stage->require($package_name);
    $this->stage->apply($package_name);
    $this->messenger()->addMessage($this->t('The project %project has been installed.', ['%project' => $project_name]));
  }


}
