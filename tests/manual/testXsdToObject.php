#!/usr/bin/env php
<?php

include(__DIR__ . '/../../src/XsdToObject.php');

if (!isset($argv[1])) {
  echo 'Usage: testXsdToObject [URI to XSD file]' . PHP_EOL;
  exit(1);
}

$test = new XsdToObject();

$uri = $argv[1];
$content = @file_get_contents($uri);
if ($content) {
  $schema = $test->parse($content);
  echo json_encode($schema, JSON_PRETTY_PRINT);
}
else {
  echo "No content found for $uri" . PHP_EOL;
  var_dump($http_response_header);
}
