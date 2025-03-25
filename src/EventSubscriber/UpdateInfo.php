<?php

namespace Drupal\pme\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\InstalledPackage;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdateInfo implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator) {
  }

  public static function getSubscribedEvents(): array {
    return [
      StatusCheckEvent::class => 'onStatusCheck',
    ];
  }

  public function onStatusCheck(StatusCheckEvent $event): void {
    if (!$event->stage->stageDirectoryExists()) {
      return;
    }
    $active = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());

    $staged = $this->composerInspector->getInstalledPackagesList($event->stage->getStageDirectory());
    $changed_stage_packages = $staged->getPackagesWithDifferentVersionsIn($active)->getArrayCopy();
    $messages = [];
    foreach ($changed_stage_packages as $changed_stage_package) {
      assert($changed_stage_package instanceof InstalledPackage);
      $active_package = $active[$changed_stage_package->name];
      $messages[] = $this->t(
        '@name changed from @active_version to @staged_version',
        [
          '@name' => $changed_stage_package->name,
          '@active_version' => $active_package->version,
          '@staged_version' => $changed_stage_package->version,
        ]
      );
    }
    if ($messages) {
      $event->addWarning($messages, $this->t('The following packages have changed versions in the staging directory:'));
    }
    $messages = [];
    $new_stage_packages = $staged->getPackagesNotIn($active)->getArrayCopy();
    foreach ($new_stage_packages as $new_stage_package) {
      assert($new_stage_package instanceof InstalledPackage);
      $messages[] = $this->t(
        '@name added with version @staged_version',
        [
          '@name' => $new_stage_package->name,
          '@staged_version' => $new_stage_package->version,
        ]
      );
    }
    if ($messages) {
      $event->addWarning($messages, $this->t('The following packages were added in the staging directory:'));
    }
  }

}
