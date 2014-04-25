#!/usr/bin/env php
<?php

include(__DIR__ . '/../../src/XsdToObject.php');

if (!isset($argv[1])) {
  echo 'Usage: testXsdToObject [URI to XSD file]' . PHP_EOL;
  exit(1);
}


$uri = $argv[1];
// TODO: add context to get correct result type: application/xml
$content = @file_get_contents($uri);
if ($content) {
  $test = new XsdToObject();
  $schema = $test->parse($content);
  echo PHP_EOL . "Found namespaces: " . PHP_EOL;
  print_r($test->getDocNamespaces());
  echo PHP_EOL . "Found XPath for default namespace: " . PHP_EOL;
  echo '  ' . join(PHP_EOL . '  ', array_keys($schema)) . PHP_EOL . PHP_EOL;
}
else {
  echo "No content found for $uri" . PHP_EOL;
  var_dump($http_response_header);
}
