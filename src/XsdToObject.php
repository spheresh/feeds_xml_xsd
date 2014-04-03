<?php
/**
 * Created by PhpStorm.
 * User: martijn
 * Date: 4/2/14
 * Time: 3:50 PM
 */

namespace feeds_xsd_xml;
/*
TODO: file documentation
 - class documentation
 - variables: init camelcase
 - empty constructor + new parse(string) method
 - run code style PHP Storm
 - teach use PHP Storm settings for DSrupal 7
 - method documentation
 - document path used (add reasons)
*/

class XsdToObject {
    private $xsdurl;
    private $xsd;
    private $types = [];
    private $elements = [];

    public function __construct($url){
        $this->xsdurl = $url;
        $this->xsd = simplexml_load_file($url);
        $this->xsd->registerXPathNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        foreach($this->xsd->xpath('///xs:simpleType') as $element){
            $this->parseType($element);
        }
        foreach($this->xsd->xpath('/xs:schema/xs:element') as $element){
            $this->parseElement($element);
        }
        print_r($this->elements);
    }

    private function parseElement($element, $parentpath = '/'){

        $name = (string) $element->attributes()->name;
        $children = $element->children('xs', true);
        if($children->count() > 0){
                foreach($element->xpath('xs:complexType//xs:element') as $subelement){
                    $this->parseElement($subelement, $parentpath . $name . '/');
                }
                foreach($element->xpath('xs:complexType/xs:attribute') as $attribute){
                    $this->elements[$parentpath . $name . '/@' . $attribute->attributes()->name] = [
                        'type' => 'attribute'
                    ];
                }
        }else{
            $min = 1;
            if(isset($element->attributes()->minOccurs)){
                $min = (string) $element->attributes()->minOccurs;
            }
            $max = 1;
            if(isset($element->attributes()->maxOccurs)){
                $max = (string) $element->attributes()->maxOccurs;
            }
            $type = (string) $element->attributes()->type;
            if(!empty($type) && isset($this->types[$type])){
                $annotation = $this->types[$type];
            }else{
                $annotation = [];
            }
            $this->elements[$parentpath . $name] = [
                'type' => 'element',
                'min' => $min,
                'max' => $max,
                'annotation' => $annotation
            ];
        }
    }

    private function parseType($element)
    {
        $name = (string) $element->attributes()->name;
        foreach($element->xpath('//xs:documentation') as $doc){
            $lang = (string) $doc->attributes('xml', true)->lang;
            $text = (string) $doc;
            $this->types[$name][$lang] = $text;
        }
    }
}

$test = new XsdToObject('http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd');