<?php

namespace Drupal\pme;

use Drupal\package_manager\StageBase;

class UninstallStage extends StageBase {

  protected string $type = 'pme:uninstall';
  public function require(array $runtime, array $dev = [], ?int $timeout = 300): void
  {
    throw new \BadMethodCallException('UninstallStage::require() is not supported.');
  }

  public function uninstall(array $package_names): void
  {
    $active_dir = $this->pathFactory->create($this->pathLocator->getProjectRoot());
    $stage_dir = $this->pathFactory->create($this->getStageDirectory());
    $this->stager->stage(array_merge(['remove'], $package_names), $active_dir, $stage_dir, NULL, 300);
  }


}
