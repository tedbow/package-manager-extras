<?php

namespace Drupal\pme\Form;

use Drupal\Core\Form\FormBase;
use Drupal\package_manager\StageBase;

abstract class StageFormBase extends FormBase {

  protected readonly StageBase $stage;
  protected function getCancelForm(): ?array {
    if (!$this->stage->isAvailable()) {
      $form['message'] = [
        '#markup' => $this->t('The installer stage is not available.'),
      ];
      // Add cancel button.
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#submit' => ['::cancelForm'],
        '#limit_validation_errors' => [],
      ];
      return $form;
    }
    return NULL;
  }

  public function cancelForm(): void {
    $this->stage->destroy(TRUE);
    $this->messenger()->addMessage($this->t('The current operation has been canceled.'));
  }

}
