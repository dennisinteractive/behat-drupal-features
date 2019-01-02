<?php
namespace DennisDigital\Behat\DrupalFeatures\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Testwork\Call\Exception\CallErrorException;

class DrupalFeaturesContext extends RawDrupalContext {

  /**
   * Catchable errors that occurred when checking features.
   *
   * @var array
   */
  private $errors = array();

  /**
   * Exclude components with known issues.
   *
   * @var array
   */
  protected $exclusions = array(
    'taxonomy' => array(
      'hierarchy',
    ),
    'info' => array(),
  );

  /**
   * @Given Features are in a default state
   */
  public function featuresAreInADefaultState() {
    $overridden = array();
    foreach ($this->getFeaturesList() as $feature) {
      switch ($feature['state']) {
        case FEATURES_OVERRIDDEN:
        case FEATURES_NEEDS_REVIEW:
          if ($feature['components'] = $this->getOverriddenComponents($feature['module'])) {
            $overridden[] = $feature;
          }
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
      if (!empty($feature['components'])) {
        foreach ($feature['components'] as $component) {
          $lines[] = '    - ' . $component;
        }
      }
    }

    // Append caught errors.
    if (count($this->errors)) {
      $lines[] = PHP_EOL . 'Errors:';
      foreach ($this->errors as $error) {
        $lines[] = '    - ' . $error;
      }
    }

    return implode(PHP_EOL, $lines);
  }

  /**
   * Get list of enabled Features and their state.
   *
   * @return array
   */
  protected function getFeaturesList() {
    $this->getDrupal()->getDriver('drupal');

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
          'module' => $m,
        );
      }
    }
    return $rows;
  }

  /**
   * Get array of overridden components.
   *
   * @param $module
   *
   * @return array
   */
  protected function getOverriddenComponents($module) {
    module_load_include('inc', 'features', 'features.export');
    module_load_include('inc', 'diff', 'diff.engine');

    $formatter = new \DiffFormatter();
    $formatter->leading_context_lines = 0;
    $formatter->trailing_context_lines = 0;
    $formatter->show_header = FALSE;

    $components = array();

    // Suppress errors during feature override detection, to be reported later.
    try {
      $detected_overrides = features_detect_overrides($module);
    }
    catch (CallErrorException $e) {
      $this->errors[] = $e->getMessage();
      return $components;
    }

    foreach ($detected_overrides as $component => $items) {
      if (isset($this->exclusions[$component]) && empty($this->exclusions[$component])) {
        continue;
      }

      $diff = new \Diff(explode("\n", $items['default']), explode("\n", $items['normal']));
      if (isset($this->exclusions[$component])) {
        foreach ($this->exclusions[$component] as $pattern) {
          if (preg_match('/' . preg_quote($pattern) . '/', $formatter->format($diff))) {
            continue 2;
          }
        }
      }

      $components[] = $component;
    }

    return $components;
  }
}
