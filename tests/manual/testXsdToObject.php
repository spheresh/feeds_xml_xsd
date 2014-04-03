#!/usr/bin/env php
<?php
namespace feeds_xsd_xml;
include(__DIR__ . '/../../src/XsdToObject.php');

if(!isset($argv[1])){
    echo 'Usage: testXsdToObject [xsd file or url]';
    exit(1);
}

$test = new XsdToObject();
$schema = $test->parse(file_get_contents($argv[1]));
echo json_encode($schema, JSON_PRETTY_PRINT);