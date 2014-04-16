<?php

/**
 * @file
 * Provides the base class for FeedsXPathParserHTML and FeedsXPathParserXML.
 *
 * NOTE:
 * This is a file copy / rename of FeedsXPathParserBase
 */

/**
 * Base class for the HTML and XML parsers.
 */
abstract class FeedsXSDParserBase extends FeedsParser {

  protected $rawXML = array();
  protected $doc = NULL;
  protected $xpath = NULL;

  /**
   * Classes that use FeedsXPathParserBase must implement this.
   *
   * @param array $source_config
   *   The configuration for the source.
   * @param FeedsFetcherResult $fetcher_result
   *   A FeedsFetcherResult object.
   *
   * @return DOMDocument
   *   The DOMDocument to perform XPath queries on.
   */
  abstract protected function setup($source_config, FeedsFetcherResult $fetcher_result);

  /**
   * Helper callback to return the raw value.
   *
   * @param DOMNode $node
   *   The DOMNode to convert to a string.
   *
   * @return string
   *   The string representation of the DOMNode.
   */
  abstract protected function getRaw(DOMNode $node);

  /**
   * Implements FeedsParser::parse().
   */
  public function parse(FeedsSource $source, FeedsFetcherResult $fetcher_result) {
    $state = $source->state(FEEDS_PARSE);
    $source_config = $this->getConfig();

    $this->doc = $this->setup($source_config, $fetcher_result);
    $parser_result = new FeedsParserResult();
    $mappings = $this->getOwnMappings();
    $fetcher_config = $source->getConfigFor($source->importer->fetcher);
    $parser_result->link = $fetcher_config['source'];
    $this->xpath = new FeedsXPathParserDOMXPath($this->doc);

    // TODO: fix source config
    $config = array();
    $config['debug'] = array("A");//array_keys(array_filter($source_config['exp']['debug']));
    $config['errors'] = 1;//$source_config['exp']['errors'];

    $this->xpath->setConfig($config);

    // Get number of nodes inside context
    $context_query = '(' . $source_config['context'] . ')';
    if (empty($state->total)) {
      $state->total = $this->xpath->namespacedQuery('count(' . $context_query . ')', $this->doc, 'count');
    }

    // Calculate result range for this batch run
    $start = $state->pointer ? $state->pointer : 0;
    $limit = $start + $source->importer->getLimit();
    $end = ($limit > $state->total) ? $state->total : $limit;
    $state->pointer = $end;
    $context_query .= "[position() > $start and position() <= $end]";
    $progress = $state->pointer ? $state->pointer : 0;
    $all_nodes = $this->xpath->namespacedQuery($context_query, NULL, 'context');

    // Loo through all root nodes inside the context
    foreach ($all_nodes as $node) {
      $parsed_item = array();

      // Loop through all mappings and get the values
      foreach ($mappings as $query => $target) {
        list(, $xpath) = explode(':', $query, 2);
        $xpath = str_replace($source_config['context'] . '/', '', $xpath);

        $result = $this->parseSourceElement($xpath, $node, 'xsd');
        if (isset($result)) {
          $parsed_item[$query] = $result;
        }
      }
      if (!empty($parsed_item)) {
        $parser_result->items[] = $parsed_item;
      }
    }
    $state->progress($state->total, $progress);
    unset($this->doc);
    unset($this->xpath);
    return $parser_result;
  }

  /**
   * Parses one item from the context array.
   *
   * @param string $query
   *   An XPath query.
   * @param DOMNode $context
   *   The current context DOMNode .
   * @param string $source
   *   The name of the source for this query.
   *
   * @return array
   *   An array containing the results of the query.
   */
  protected function parseSourceElement($query, $context, $source) {

    if (empty($query)) {
      return;
    }

    $node_list = $this->xpath->namespacedQuery($query, $context, $source);

    // Iterate through the results of the XPath query.  If this source is
    // configured to return raw xml, make it so.
    if ($node_list instanceof DOMNodeList) {
      $results = array();
      foreach ($node_list as $node) {
        $results[] = $node->nodeValue;
      }
      // Return single result if so.
      if (count($results) === 1) {
        return $results[0];
      }
      // Empty result returns NULL, that way we can check.
      elseif (empty($results)) {
        return;
      }
      else {
        return $results;
      }
    }
    // A value was returned directly from namespacedQuery().
    else {
      return $node_list;
    }
  }

  /**
   * Gets the mappings that are defined by this parser.
   *
   * The mappings begin with "xpathparser:".
   *
   * @return array
   *   An array of mappings keyed source => target.
   */
  protected function getOwnMappings() {
    $importer_config = feeds_importer($this->id)->getConfig();
    return $this->filterMappings($importer_config['processor']['config']['mappings']);
  }

  /**
   * Filters mappings, returning the ones that belong to us.
   *
   * @param array $mappings
   *   A mapping array from a processor.
   *
   * @return array
   *   An array of mappings keyed source => target.
   */
  protected function filterMappings($mappings) {
    $our_mappings = array();
    foreach ($mappings as $mapping) {
      if (strpos($mapping['source'], 'xsd:') === 0) {
        $our_mappings[$mapping['source']] = $mapping['target'];
      }
    }
    return $our_mappings;
  }

  /**
   * Starts custom error handling.
   *
   * @return bool
   *   The previous value of use_errors.
   */
  protected function errorStart() {
    return libxml_use_internal_errors(TRUE);
  }

  /**
   * Stops custom error handling.
   *
   * @param bool $use
   *   The previous value of use_errors.
   * @param bool $print
   *   (Optional) Whether to print errors to the screen. Defaults to TRUE.
   */
  protected function errorStop($use, $print = TRUE) {
    if ($print) {
      foreach (libxml_get_errors() as $error) {
        switch ($error->level) {
          case LIBXML_ERR_WARNING:
          case LIBXML_ERR_ERROR:
            $type = 'warning';
            break;

          case LIBXML_ERR_FATAL:
            $type = 'error';
            break;
        }
        $args = array(
          '%error' => trim($error->message),
          '%num' => $error->line,
          '%code' => $error->code,
        );
        $message = t('%error on line %num. Error code: %code', $args);
        drupal_set_message($message, $type, FALSE);
      }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($use);
  }

}
