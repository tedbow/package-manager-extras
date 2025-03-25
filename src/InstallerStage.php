<?php

namespace Drupal\pme;

use Drupal\package_manager\StageBase;

/**
 * This is a very simple stage for installing a project.
 */
class InstallerStage extends StageBase {

  /**
   * {@inheritdoc}
   */
  protected string $type = 'pme:installer';

}
