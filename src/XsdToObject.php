<?php
/**
 * Created by PhpStorm.
 * User: martijn
 * Date: 4/2/14
 * Time: 3:50 PM
 */

//namespace feeds_xsd_xml;
/*
TODO: file documentation
 - teach use PHP Storm settings for DSrupal 7
 - document path used (add reasons)
*/

/**
 * Class XsdToObject
 * @package feeds_xsd_xml
 */
class XsdToObject {
  private $xsdFile;
  private $xsd;
  private $types = array();
  private $elements = array();

  /**
   * Parse xsd string into possible xpath's and documentation
   * @param string $xsd string containing xsd file
   * @return array
   */
  public function parse($xsd) {
    $this->xsdFile = $xsd;
    $this->xsd = simplexml_load_string($xsd);
    $this->xsd->registerXPathNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    // Loop through all xs:simpleTypes to get annotations
    foreach ($this->xsd->xpath('///xs:simpleType') as $element) {
      $this->parseType($element);
    }

    // Loop through all xs:elements to get the xpaths
    foreach ($this->xsd->xpath('/xs:schema/xs:element') as $element) {
      $this->parseElement($element);
    }

    return $this->elements;
  }

  /**
   * Parse xs:element to the $this->elements array
   * @param \SimpleXMLElement $element XSD node containing xs:element
   * @param string $parentPath
   */
  private function parseElement($element, $parentPath = '/') {

    $name = (string) $element->attributes()->name;
    $children = $element->children('xs', TRUE);
    if ($children->count() > 0) {
      foreach ($element->xpath('xs:complexType//xs:element') as $subelement) {
        $this->parseElement($subelement, $parentPath . $name . '/');
      }
      foreach ($element->xpath('xs:complexType/xs:attribute') as $attribute) {
        $this->elements[$parentPath . $name . '/@' . $attribute->attributes()->name] = array(
          'type' => 'attribute'
        );
      }
    }
    else {
      $min = 1;
      if (isset($element->attributes()->minOccurs)) {
        $min = (string) $element->attributes()->minOccurs;
      }
      $max = 1;
      if (isset($element->attributes()->maxOccurs)) {
        $max = (string) $element->attributes()->maxOccurs;
      }
      $type = (string) $element->attributes()->type;
      if (!empty($type) && isset($this->types[$type])) {
        $annotation = $this->types[$type];
      }
      else {
        $annotation = array();
      }
      $this->elements[$parentPath . $name] = array(
        'type' => 'element',
        'min' => $min,
        'max' => $max,
        'annotation' => $annotation
      );
    }
  }

  /**
   * Parse simpleTypes to $this->types to get annotations
   * @param \SimpleXMLElement $element XSD node containing xs:simpleType
   */
  private function parseType($element) {
    $name = (string) $element->attributes()->name;
    foreach ($element->xpath('//xs:documentation') as $doc) {
      $lang = (string) $doc->attributes('xml', TRUE)->lang;
      $text = (string) $doc;
      $this->types[$name][$lang] = $text;
    }
  }
}
