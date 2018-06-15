<?php
namespace DennisDigital\Behat\DrupalFeatures\Context;

use Behat\Testwork\Hook\HookDispatcher;
use Drupal\DrupalDriverManager;
use Drupal\DrupalExtension\Context\DrupalAwareInterface;
use Drupal\DrupalUserManagerInterface;

class DrupalFeaturesContext implements DrupalAwareInterface {

  /**
   * @var DrupalDriverManager
   */
  private $drupal;

  /**
   * @var HookDispatcher
   */
  private $dispatcher;

  /**
   * @var array
   */
  private $parameters;

  /**
   * @inheritDoc
   */
  public function setDispatcher(HookDispatcher $dispatcher) {
    $this->dispatcher = $dispatcher;
  }

  /**
   * @inheritDoc
   */
  public function getDrupal() {
    return $this->drupal;
  }

  /**
   * @inheritDoc
   */
  public function setDrupal(DrupalDriverManager $drupal) {
    $this->drupal = $drupal;
  }

  /**
   * @inheritDoc
   */
  public function setDrupalParameters(array $parameters) {
    $this->parameters = $parameters;
  }
  
  /**
   * @inheritdoc
   */
  public function setUserManager(DrupalUserManagerInterface $userManager) {
  }
  
  /**
   * @inheritdoc
   */
  public function getUserManager() {
  }
  
  /**
   * @Given Features are in a default state
   */
  public function featuresAreInADefaultState() {
    $overridden = array();
    foreach ($this->getFeaturesList() as $feature) {
      switch ($feature['state']) {
        case FEATURES_OVERRIDDEN:
        case FEATURES_NEEDS_REVIEW:
          $overridden[] = $feature;
          break;
      }
    }
    if (!empty($overridden)) {
      $messages = array(
        'The following Features are either overridden or need review:',
        $this->getFeatureListOutput($overridden),
      );
      throw new \Exception(implode(PHP_EOL, $messages) . PHP_EOL);
    }
  }

  /**
   * Get error output.
   *
   * @param $features
   * @return string
   */
  protected function getFeatureListOutput($features) {
    $lines = array();
    foreach ($features as $feature) {
      $state = '';
      switch ($feature['state']) {
        case FEATURES_OVERRIDDEN:
          $state = '(Overridden)';
          break;
        case FEATURES_NEEDS_REVIEW:
          $state = '(Needs review)';
          break;
      }
      $lines[] = ' - ' . $feature['feature'] . ' ' . $state;
    }

    return implode(PHP_EOL, $lines);
  }

  /**
   * Get list of enabled Features and their state.
   *
   * @return array
   */
  protected function getFeaturesList() {
    $this->drupal->getDriver('drupal');

    module_load_include('inc', 'features', 'features.export');

    // Sort the Features list before compiling the output.
    $features = features_get_features(NULL, TRUE);
    ksort($features);

    $rows = array();
    foreach ($features as $k => $m) {
      if ($m->status == 1) {
        $rows[$k] = array(
          'name' => $m->info['name'],
          'feature' => $m->name,
          'status' => $m->status ? t('Enabled') : t('Disabled'),
          'version' => $m->info['version'],
          'state' => features_get_storage($m->name),
        );
      }
    }
    return $rows;
  }
}
