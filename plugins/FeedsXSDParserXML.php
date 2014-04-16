<?php

/**
 * @file
 * Provides the FeedsXPathParserXML class.
 *
 * NOTE:
 * This is a file copy / rename of FeedsXPathParserBase
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
    // TODO fix source config
    $source_config['exp']['errors'] = TRUE;
    // end TODO
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
