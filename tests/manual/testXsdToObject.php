<?php
namespace feeds_xsd_xml;
include(__DIR__ . '/../../src/XsdToObject.php');

$test = new XsdToObject();
$test->parse(file_get_contents('http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd'));