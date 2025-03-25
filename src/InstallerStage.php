<?php

namespace Drupal\pme;

use Drupal\package_manager\StageBase;

/**
 * This is a very simple stage for installing a project.
 */
class InstallerStage extends StageBase {

  /**
   * ℹ️ Every stage must have a unique type.
   */
  protected string $type = 'pme:installer';

}
