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
use Drupal\package_manager\ProjectInfo;
use Drupal\project_browser\ComposerInstaller\Installer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProjectBrowserValidator implements EventSubscriberInterface
{

  use StringTranslationTrait;

  public function __construct(private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator){
  }

  public static function getSubscribedEvents(): array{
    return [
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
      if (str_starts_with($package->name, 'drupal/')) {
        $version = LegacyVersionUtility::convertToSemanticVersion($package->version);
        $stability = VersionParser::parseStability($version);
        if ($stability !== 'stable') {
          $project_name = $package->getProjectName() ?? $package->name;
          $event->addError([
            $this->t(
              'Unable to install @project, version @version, because only installing stable modules is supported.',
              [
                '@project' => $project_name,
                '@version' => $package->version,
              ]
            )],
          );
        }
      }
    }
  }
}
