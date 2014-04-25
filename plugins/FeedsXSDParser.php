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
    return array();
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
    return array(
      'xsd_fid' => 0,
      'xpaths' => array(),
      'context' => '',
      'namespaces' => array(),
      'available_namespaces' => array(),
    );
  }

  /**
   * Build configuration form.
   */
  public function configForm(&$form_state) {
    $config = $this->getConfig();
    $xsd_fid = $config['xsd_fid'];
    if ($xsd_fid) {
      $file = file_load($xsd_fid);
    }
    $form = array();
    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['xsd_upload'] = array(
      '#type' => 'file',
      '#title' => t('Upload XSD schema'),
      //'#required' => !$xsd_fid,
      '#description' => $xsd_fid ? t("Using file %uri", array('%uri' => $file->uri)) : t("No file selected yet"),
    );
    $form['xsd_fid'] = array(
      '#type' => 'value',
      '#value' => $xsd_fid,
    );

    foreach ($config['available_namespaces'] as $key => &$namespace) {
      if (!empty($key)) {
        $namespace = $key . ':' . $namespace;
      }
    }

    $form['namespaces'] = array(
      '#type' => 'checkboxes',
      '#options' => $config['available_namespaces'],
      '#title' => 'Select namespaces to import from the XSD',
      '#default_value' => $config['namespaces'],
    );

    $contexts = array_unique(array_map('dirname', array_keys($config['xpaths'])));
    if (count($contexts)) {
      $contexts = array_combine($contexts, $contexts);
    }
    $form['context'] = array(
      '#type' => 'select',
      '#disabled' => !count($config['xpaths']),
      '#title' => t('Context path'),
      '#options' => count($config['xpaths']) ? $contexts : array(),
      '#description' => t("The path from which to extract repeating elements."),
      '#default_value' => $config['context'],
    );

    return $form;
  }

  public function configFormValidate(&$values) {
    $config = $this->getConfig();
    dsm($config);
    $file = file_save_upload('xsd_upload', array(
      'file_validate_extensions' => array('xsd')
    ));
    if ($values['xsd_fid'] && !$file) {
      $file = file_load($values['xsd_fid']);
    }
    if ($file) {
      dsm($values);
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
        $values['available_namespaces'] = $parser->getDocNamespaces();
      }
    }
    else {
      form_set_error('xsd_upload', t('No XSD Schema uploaded.'));
    }
  }

  public function configFormSubmit(&$values) {
    /** @var  $file */
    $file = $values['xsd_upload'];
    $file->status = FILE_STATUS_PERMANENT;
    $file = file_save($file);
    file_move($file, 'public://' . $file->filename);
    $values['xsd_fid'] = $file->fid;
    $this->config['namespaces'] = $values['namespaces'];
    $this->config['context'] = $values['context'];
    $this->config['xpaths'] = $values['xpaths'];
    parent::configFormSubmit($values);
  }

  public function getMappingSources() {
    $xpath_sources = array();
    $context = $this->config['context'];
    foreach ($this->config['xpaths'] as $path => $properties) {
      if (strpos($path, $context) !== 0) {
        continue;
      }
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
