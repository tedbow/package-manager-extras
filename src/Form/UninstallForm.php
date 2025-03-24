<?php

namespace Drupal\pme\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\StageBase;
use Drupal\pme\UninstallStage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UninstallForm extends StageFormBase {


  public function __construct(private readonly ComposerInspector $composerInspector, private readonly PathLocator $pathLocator, protected readonly StageBase $stage, private readonly ModuleExtensionList $extensionList, private readonly ModuleHandler $moduleHandler) {

  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get(ComposerInspector::class),
      $container->get(PathLocator::class),
      $container->get(UninstallStage::class),
      $container->get(ModuleExtensionList::class),
      $container->get(ModuleHandlerInterface::class)
    );
  }


  public function getFormId()
  {
    return 'pme.uninstall';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
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
          continue;
        }
        if (!$this->moduleHandler->moduleExists($module_name)) {
          $uninstallable_packages[$package->name] = "{$module->info['project']} ($package->name)";
        }
        else {
          $required_packages[$package->name] = $package->name;
        }
      }
    }
    $uninstallable_packages = array_diff_key($uninstallable_packages, $required_packages);
    // Make a group checkboxes for $uninstallable_projects
    $form['uninstall'] = [
      '#type' => 'checkboxes',
      '#title' => 'Uninstall',
      '#options' => $uninstallable_packages,
    ];
    // Add a submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Uninstall'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    try {
      $this->stage->create();
      $this->stage->uninstall($form_state->getValue('uninstall'));
      $this->stage->apply();
      $this->messenger()->addMessage($this->t('Packages uninstalled'));
    }
    catch (StageEventException $exception) {
      $this->messenger()->addError($exception->getMessage());
    }
    $this->stage->destroy();
  }
}
