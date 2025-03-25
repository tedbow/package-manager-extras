# Package Manager Examples

👋👋👋👋👋👋👋👋👋👋👋👋

Welcome, thanks for coming to my session. Ok, don't feel guilty if you didn't.

Well, maybe a little.


## Purpose
This module has some simple examples of how to use the API of the Package Manager module.

## Examples

1. Installing a package via Composer
[`Drupal\pme\Form\InstallForm`](src/Form/InstallForm.php)

1. Uninstalling a package via Composer
[`Drupal\pme\Form\UninstallForm`](src/Form/UninstallForm.php)
1. Inspecting the stage directory for more information
[`Drupal\pme\EventSubscriber\UpdateInfo`](src/EventSubscriber/UpdateInfo.php)
1. Preventing Package Manager operations based on custom logic
[`Drupal\pme\EventSubscriber\ProjectBrowserValidator`](src/EventSubscriber/ProjectBrowserValidator.php)
