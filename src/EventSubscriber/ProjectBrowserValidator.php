<?php

namespace Drupal\pme\EventSubscriber;

use Composer\Semver\VersionParser;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\InstalledPackage;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a subscriber to validate project browser installs.
 *
 * In this example we only allow stable versions of drupal projects to be installed.
 */
class ProjectBrowserValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator) {
  }

  public static function getSubscribedEvents(): array {
    return [
      // We subscribe to StatusCheckEvent and PreApplyEvent.
      //
      // StatusCheckEvent allows Package Manager aware forms to check for problems
      // before applying updates(though Project Browser does not use this).
      //
      // PreApplyEvent allows use to prevent the operation from being applied to
      // the live site.
      StatusCheckEvent::class => 'validateProjectStatus',
      PreApplyEvent::class => 'validateProjectStatus',
    ];
  }

  public function validateProjectStatus(PreOperationStageEvent $event): void {
    $stage = $event->stage;
    if (!$stage->stageDirectoryExists() || $stage->getType() !== 'project_browser.installer') {
      return;
    }
    $active = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $newPackages = $this->composerInspector->getInstalledPackagesList($stage->getStageDirectory())->getPackagesNotIn($active);
    foreach ($newPackages->getArrayCopy() as $package) {
      assert($package instanceof InstalledPackage);
      // We only care about drupal projects.
      if ($project_name = $package->getProjectName()) {
        // Handle legacy versions, such as 8.x-1.5.
        $version = LegacyVersionUtility::convertToSemanticVersion($package->version);
        $stability = VersionParser::parseStability($version);
        if ($stability !== 'stable') {
          // By adding an error we can ensure the package will not be installed
          // @see Drupal\package_manager\StageBase::apply()
          $event->addError([
            $this->t(
              'Unable to install @project, version @version, because only installing stable modules is supported.',
              [
                '@project' => $project_name,
                '@version' => $package->version,
              ]
            ),
          ]);
        }
      }
    }
  }

}
