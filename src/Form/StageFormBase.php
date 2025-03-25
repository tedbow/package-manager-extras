<?php

namespace Drupal\pme\Form;

use Drupal\Core\Form\FormBase;
use Drupal\package_manager\StageBase;

/**
 * Provides a base form for forms that perform Package Manager operations.
 *
 * @todo Production code forms should run status checks
 *
 * @see \Drupal\package_manager\StatusCheckTrait::runStatusCheck()
 */
abstract class StageFormBase extends FormBase {

  protected readonly StageBase $stage;

  protected function getCancelForm(): ?array {
    if (!$this->stage->isAvailable()) {
      $form['message'] = [
        '#markup' => $this->t('There is another operation in progress.'),
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
