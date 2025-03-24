<?php

namespace Drupal\pme;

use Drupal\package_manager\StageBase;

class InstallerStage extends StageBase
{

  /**
   * {@inheritdoc}
   */
  protected string $type = 'pme:installer';

}
