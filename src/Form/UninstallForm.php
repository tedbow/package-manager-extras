<?php

namespace Drupal\pme\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\StageBase;
use Drupal\pme\UninstallStage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a very simple form for uninstall projects.
 *
 * This is a very simple example the form should really use the batch system for
 * stage operations because:
 *   * It can take a long time to install run package manager operations.
 *   * The post apply step should be run in a separate request.
 *
 * @see \Drupal\automatic_updates\Form\UpdaterForm::submitForm()
 * @see \Drupal\automatic_updates\BatchProcessor::postApply()
 */
class UninstallForm extends StageFormBase {

  public function __construct(private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator, protected readonly StageBase $stage, private readonly ModuleExtensionList $extensionList, private readonly ModuleHandler $moduleHandler) {

  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ComposerInspector::class),
      $container->get(PathLocator::class),
      $container->get(UninstallStage::class),
      $container->get(ModuleExtensionList::class),
      $container->get(ModuleHandlerInterface::class)
    );
  }

  public function getFormId() {
    return 'pme.uninstall';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($cancelForm = $this->getCancelForm()) {
      return $cancelForm;
    }
    $modules = $this->extensionList->getList();
    $packageList = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $uninstallable_packages = [];
    foreach ($modules as $module_name => $module) {
      if (!empty($module->info['project'])) {
        $package = $packageList->getPackageByDrupalProjectName($module->info['project']);
        if (!$package) {
          // This module is not installed via composer.
          continue;
        }
        if (!$this->moduleHandler->moduleExists($module_name)) {
          $uninstallable_packages[$package->name] = "{$module->info['project']} ($package->name)";
        }
        else {
          // If the module is enabled, so we can't uninstall it.
          $required_packages[$package->name] = $package->name;
        }
      }
    }
    $uninstallable_packages = array_diff_key($uninstallable_packages, $required_packages);
    $form['uninstall'] = [
      '#type' => 'checkboxes',
      '#title' => 'Uninstall',
      '#options' => $uninstallable_packages,
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Uninstall'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // @todo Determine if any of these packages will fail to be uninstalled
    //   because they are required by other packages.
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    assert($this->stage instanceof UninstallStage);
    $packages = array_filter($form_state->getValue('uninstall'));
    try {
      $this->stage->create();
      $this->stage->uninstall($packages);
      $this->stage->apply();
      // Post apply should be run in a separate request. Running in same request here for simplicity.
      // @see \Drupal\automatic_updates\BatchProcessor::postApply().
      $this->stage->postApply();
      $this->messenger()->addMessage($this->t('Packages uninstalled'));
    }
    catch (\Throwable $exception) {
      $this->messenger()->addError($exception->getMessage());
    }
    $this->stage->destroy();
  }

}
