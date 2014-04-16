<?php

/**
 * @file
 * This file contains a XML Parser based on a given XSD.
 *
 * The given XSD is processed to get all possible path selectors. Next the user must give a context from which
 * to extract 'records' for further processing.
 */

/**
 * Class FeedsXSDParser
 *
 * Provides for the different feeds forms and processes the given XML.
 */
class FeedsXSDParser extends FeedsXSDParserXML {

  /**
   * Define defaults.
   */
  public function sourceDefaults() {
    return array(
      'xsd_uri' => $this->config['xsd_uri'],
      'xsd_fid' => $this->config['xsd_fid'],
      'xpaths' => $this->config['xpaths'],
    );
  }

  /**
   * Source form.
   *
   * Show mapping configuration as a guidance for import form users.
   */
  public function sourceForm($source_config) {
    $form = array();
    return $form;
  }

  /**
   * Define default configuration.
   */
  public function configDefaults() {
    // TODO must these match with sourceDefaults?
    return array(
      // TODO: hardcoded schema
      'xsd_uri' => 'http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd',
      'xsd_fid' => 0,
      'xpaths' => array(),
      'context' => '',
    );
  }

  /**
   * Build configuration form.
   */
  public function configForm(&$form_state) {
    $config = $this->getConfig();
    // TODO remove this line : reset test
    //$config = $this->configDefaults();
    $xsd_fid = $config['xsd_fid'];
    if ($xsd_fid) {
      $file = file_load($xsd_fid);
    }
    $form = array();
    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['xsd_upload'] = array(
      '#type' => 'file',
      '#title' => t('Upload XSD schema'),
      '#required' => !$xsd_fid,
      '#description' => $xsd_fid ? t("Using file %uri", array('%uri' => $file->uri)) : t("No file selected yet"),
    );
    $form['xsd_fid'] = array(
      '#type' => 'value',
      '#value' => $xsd_fid,
    );

    $contexts = array_unique(array_map('dirname', array_keys($config['xpaths'])));

    $form['context'] = array(
      '#type' => 'select',
      '#disabled' => !count($config['xpaths']),
      '#title' => t('Context path'),
      '#options' => count($config['xpaths']) ? $contexts: array(),
      '#description' => t("The path from which to extract repeating elements."),
      '#value' => $config['context'],
    );

    return $form;
  }

  public function configFormValidate(&$values) {
    $file = file_save_upload('xsd_upload', array(
      'file_validate_extensions' => array('xsd')
    ));
    if ($values['xsd_fid'] && !$file) {
      $file = file_load($values['xsd_fid']);
    }
    if ($file) {
      $parser = new XsdToObject();
      $xsd = file_get_contents($file->uri);
      $result = $parser->parse($xsd);
      if (count($result) == 0) {
        form_set_error('xsd_upload', t("This does not seem to be a valid XSD schema"));
      }
      else {
        $values['xsd_fid'] = $file->fid;
        $values['xsd_upload'] = $file;
        $values['xpaths'] = $result;
      }
    }
    else {
      form_set_error('xsd_upload', t('No XSD Schema uploaded.'));
    }

    // TODO: What must we keep from parent form validate?
    parent::configFormValidate($values);
  }

  public function configFormSubmit(&$values) {
    $file = $values['xsd_upload'];
    // TODO is file now moved to public:// or still tmp://?
    $file->status = FILE_STATUS_PERMANENT;
    file_save($file);
    $values['xsd_fid'] = $file->fid;
    $this->config['xpaths'] = $values['xpaths'];
    parent::configFormSubmit($values);
  }

  public function getMappingSources() {
    $xpath_sources = array();
    foreach ($this->config['xpaths'] as $path => $properties) {
      $xpath_sources['xsd:' . $path]['name'] = 'xsd:' . $path;
      if (isset($properties['annotation'])) {
        if (isset($properties['annotation']['en'])) {
          $xpath_sources['xsd:' . $path]['description'] = $properties['annotation']['en'];
        }
        else {
          $firstlang = array_shift($properties['annotation']);
          $xpath_sources['xsd:' . $path]['description'] = $firstlang;
        }
      }
      else {
        $xpath_sources['xsd:' . $path]['description'] = '';
      }
    }
    return parent::getMappingSources() + $xpath_sources;
  }
}
