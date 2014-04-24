<?php

/**
 * @file
 * Provides the FeedsXSDParserXML class.
 *
 * NOTE:
 * This was a file copy + rename of FeedsXPathParserBase
 */
class FeedsXSDParserXML extends FeedsXSDParserBase {

  /**
   * Implements FeedsXPathParserBase::setup().
   */
  protected function setup($source_config, FeedsFetcherResult $fetcher_result) {
    $raw = $fetcher_result->getRaw();
    $doc = new DOMDocument();
    $use = $this->errorStart();
    $success = $doc->loadXML($raw);
    unset($raw);

    // TODO : we want error logging back
    // hack
    if (!isset($source_config['exp'])) {
      $source_config['exp'] = array();
    }
    $source_config['exp']['errors'] = FALSE;
    $source_config['exp']['errors'];
    // end hack
    $this->errorStop($use, $source_config['exp']['errors']);
    if (!$success) {
      throw new Exception(t('There was an error parsing the XML document.'));
    }
    return $doc;
  }

  protected function getRaw(DOMNode $node) {
    return $this->doc->saveXML($node);
  }
}
