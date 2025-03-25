<?php

namespace Drupal\pme\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\ProjectInfo;
use Drupal\package_manager\StageBase;
use Drupal\pme\BatchProcessor;
use Drupal\pme\InstallerStage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a  simple form for installing a project that uses the batch system.
 *
 * @see \Drupal\automatic_updates\Form\UpdaterForm::submitForm()
 * @see \Drupal\automatic_updates\BatchProcessor::postApply()
 */
class BatchInstallerForm extends FormBase {

  public function __construct(protected StageBase $stage, private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(InstallerStage::class),
      $container->get(ComposerInspector::class),
      $container->get(PathLocator::class)
    );
  }

  public function getFormId() {
    return 'pme_install_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project'),
      '#required' => TRUE,
      '#description' => $this->t('Enter a project machine name or URL.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Install'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $project_name = $form_state->getValue('project');
    // Confirm the input is a valid project name or URL.
    if (UrlHelper::isValid($project_name, TRUE)) {
      if (strpos($project_name, 'https://www.drupal.org/project/') !== 0) {
        $form_state->setErrorByName('project_name', $this->t('The project URL must start with https://drupal.org/project/.'));
      }
      // Grab the last part of the URL for the project name.
      $project_name = substr($project_name, strlen('https://www.drupal.org/project/'));

    }
    // Check the project name is valid machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $project_name)) {
      $form_state->setErrorByName('project_name', $this->t('The project name must be a valid machine name.'));
    }
    if (!$this->stage->isAvailable()) {
      $form_state->setErrorByName('project_name', $this->t('The project is not available.'));
    }
    // Check if the project is already installed.
    $packageList = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $installed_package = $packageList->getPackageByDrupalProjectName($project_name);
    if ($installed_package) {
      $form_state->setErrorByName('project_name', $this->t('The project %project is already installed.', ['%project' => $project_name]));
    }
    $form_state->setValue('project_name', $project_name);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $project_name = $form_state->getValue('project_name');
    $projectInfo = new ProjectInfo($project_name);
    $releases = $projectInfo->getInstallableReleases();
    if (empty($releases)) {
      $this->messenger()->addError($this->t('No installable releases found for the project %project.', ['%project' => $project_name]));
      return;
    }
    // Install the most recent release.
    $install_release = reset($releases);
    $version = $install_release->getVersion();
    // Handle legacy version numbers, like 8.x-1.0.
    $version = LegacyVersionUtility::convertToSemanticVersion($version);
    // We must use the Composer package name, not the Drupal project name.
    $package_name = "drupal/$project_name:$version";
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Downloading updates'))
      ->setInitMessage($this->t('Preparing to install project'))
      ->addOperation([BatchProcessor::class, 'begin'])
      ->addOperation(
        [BatchProcessor::class, 'require'],
        [$package_name]
      )
      ->addOperation([BatchProcessor::class, 'apply'])
      ->addOperation([BatchProcessor::class, 'cleanUp'])
      ->toArray();

    batch_set($batch);
  }

}
