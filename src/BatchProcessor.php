<?php

declare(strict_types=1);

namespace Drupal\pme;

/**
 * A batch processor for updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class BatchProcessor {


  public const STAGE_ID_SESSION_KEY = '_pme_stage_id';

  private static function getStage(): InstallerStage {
    return \Drupal::service(InstallerStage::class);
  }

  public static function begin(): void {
    $stage_id = static::getStage()->create();
    \Drupal::service('session')->set(static::STAGE_ID_SESSION_KEY, $stage_id);
  }

  public static function require(string $package_name): void {
    $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
    self::getStage()->claim($stage_id)->require([$package_name]);
  }

  public static function apply(): void {
    $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
    static::getStage()->claim($stage_id)->apply();
    // The batch system does not allow any single request to run for longer
    // than a second, so this will force the next operation to be done in a
    // new request. This helps keep the running code in as consistent a state
    // as possible.
    // @see \Drupal\package_manager\Stage::apply()
    // @see \Drupal\package_manager\Stage::postApply()
    sleep(1);
  }

  public static function cleanUp(): void {
    $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
    $stage = self::getStage()->claim($stage_id);
    $stage->postApply();
    $stage->destroy();
    \Drupal::messenger()->addMessage('The project has been installed');
  }

}
