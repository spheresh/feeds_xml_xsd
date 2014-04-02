<?php
/**
 * Created by PhpStorm.
 * User: martijn
 * Date: 4/2/14
 * Time: 3:50 PM
 */

namespace feeds_xsd_xml;

class XsdToObject {
    private $xsdurl;
    private $xsd;
    private $elements = [];

    public function __construct($url){
        $this->xsdurl = $url;
        $this->xsd = simplexml_load_file($url);
        $this->xsd->registerXPathNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
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
        }else{
            $min = 0;
            if(isset($element->attributes()->minOccurs)){
                $min = (string) $element->attributes()->minOccurs;
            }
            $max = 1;
            if(isset($element->attributes()->maxOccurs)){
                $max = (string) $element->attributes()->maxOccurs;
            }

            $this->elements[] = [
                'xpath' => $parentpath . $name,
                'type' => 'element',
                'min' => $min,
                'max' => $max
            ];
            foreach($element->attributes() as $attribute){
                $this->elements[] = [
                    'xpath' => $parentpath . $name . '/@' . $attribute,
                    'type' => 'attribute'
                ];
            }
        }
    }
}

$test = new XsdToObject('http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd');