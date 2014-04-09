<?php
$filename = __DIR__ . '/../fixtures/gutenprint-ijs.5.2-pagesize.xml';
$context = '/option/enum_vals/enum_val/constraints/constraint';
$map = array(
  "/option/enum_vals/enum_val/constraints/constraint/driver" => "driver",
  "/option/enum_vals/enum_val/constraints/constraint/printer" => "printer"
);

//the gutenprint testfile with the above settings contain 92746 records



$contextlen = strlen($context);
$reader = new XMLReader();
$reader->open($filename);
$currentxpath = array();

$limit = 1000;
$i = 0;

$incontext = false;
$nodecount = 0;
$skip = 92544; //skip to last node
$node = array();
$a = '';
while($reader->read()){
  $i++;
  if($i == $limit){
    //die();
  }

  if($reader->nodeType == XMLReader::ELEMENT && !$reader->isEmptyElement){
    $currentxpath[] = $reader->name;
    $xpath = '/' . implode('/' , $currentxpath);
    $a .= $xpath . PHP_EOL;
    if($incontext){
      if(strlen($xpath) < $contextlen){
        $incontext = false;
        $nodecount++;
      }

    }
    if($xpath == $context){

      if($skip>0){
        for($j = 0; $reader->next($reader->name) && ($skip > 0); $skip--, $j++);
        echo $j . '/' . $skip;
      }
      if($incontext){
        print_r($node);
        $nodecount++;
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
echo $nodecount;
//file_put_contents('test.log', $a);