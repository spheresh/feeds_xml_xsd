#!/usr/bin/env php
<?php

include(__DIR__ . '/../../src/XsdToObject.php');

if (!isset($argv[1])) {
  echo 'Usage: testXsdToObject [URI to XSD file]';
  exit(1);
}

$test = new XsdToObject();
$schema = $test->parse(file_get_contents($argv[1]));
echo json_encode($schema, JSON_PRETTY_PRINT);