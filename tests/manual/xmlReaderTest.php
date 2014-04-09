<?php
$filename = __DIR__ . '/../fixtures/gutenprint-ijs.5.2-pagesize.xml';
$context = '/option/enum_vals/enum_val/constraints/constraint';
$map = array(
  "/option/enum_vals/enum_val/constraints/constraint/driver" => "driver",
  "/option/enum_vals/enum_val/constraints/constraint/printer" => "printer"
);




$contextlen = strlen($context);
$reader = new XMLReader();
$reader->open($filename);
$currentxpath = array();

$limit = 1000;
$i = 0;

$incontext = false;
$nodecount = 0;
$node = array();
while($reader->read()){
  $i++;
  if($i == $limit){
    //die();
  }
  if($reader->nodeType == XMLReader::ELEMENT && !$reader->isEmptyElement){
    $currentxpath[] = $reader->name;
    $xpath = '/' . implode('/' , $currentxpath);
    if($incontext){
      if(strlen($xpath) < $contextlen){
        $incontext = false;
        $nodecount++;
      }

    }
    if($xpath == $context){
      if($incontext){
        print_r($node);
      }
      $incontext = true;
      $node = array();
    }
  }
  if($reader->nodeType == XMLReader::TEXT){
    if($incontext){
      if(isset($map[$xpath])){
        $node[$map[$xpath]] = $reader->value;
      }
    }
  }
  if($reader->nodeType == XMLReader::END_ELEMENT){
    array_pop($currentxpath);
  }
}