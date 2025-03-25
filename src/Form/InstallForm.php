<?php

namespace Drupal\pme\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\ProjectInfo;
use Drupal\package_manager\StageBase;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\pme\InstallerStage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a very simple form for installing a project.
 *
 * This is a very simple example the form should really use the batch system for
 * stage operations because:
 *   * It can take a long time to install a project.
 *   * The post apply step should be run in a separate request.
 *
 * @see \Drupal\automatic_updates\Form\UpdaterForm::submitForm()
 * @see \Drupal\automatic_updates\BatchProcessor::postApply()
 */
class InstallForm extends StageFormBase {

  use StatusCheckTrait;

  public function __construct(protected readonly StageBase $stage, private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator) {}

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
    if ($cancelForm = $this->getCancelForm()) {
      return $cancelForm;
    }
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
    parent::validateForm($form, $form_state);
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
    $install_release = reset($releases);
    $version = $install_release->getVersion();
    $version = LegacyVersionUtility::convertToSemanticVersion($version);
    $package_name = "drupal/$project_name:$version";
    try {
      $this->stage->create();
      $this->stage->require([$package_name]);
      $this->stage->apply();
      // Post apply should be run in a separate request. Running in same request here for simplicity.
      // @see \Drupal\automatic_updates\BatchProcessor::postApply().
      $this->stage->postApply();
      $this->messenger()->addMessage($this->t('The project %project has been installed.', ['%project' => $project_name]));
      $this->logger('pme')->notice('The project %project has been installed.', ['%project' => $project_name]);
    }
    catch (StageEventException $exception) {
      $this->messenger()->addError($exception->getMessage());
    }
    $this->stage->destroy();
  }

}
