<?php
/**
 * @file
 * Convert a given XSD into an array structure.
 */


/**
 * Class XsdToObject
 */
class XsdToObject {

  /**
   * @var string
   * File URI to process.
   */
  private $xsdFile;

  /**
   * @var /SimpleXML
   * contents of file in SimpleXML data structure
   */
  private $xsd;

  /**
   * @var array
   * Contains element types found.
   */
  private $types = array();

  /**
   * @var array
   * Contains definitions for attribute groups.
   */
  private $attributeGroups = array();

  /**
   * @var array
   * List of elements found.
   */
  private $elements = array();

  /**
   * @var string
   * Namespace containing http://www.w3.org/2001/XMLSchema
   */
  private $schemaNs;

  /**
   * Parse xsd string into possible xpath's and documentation
   * @param string $xsd string containing xsd file
   * @return array
   */
  public function parse($xsd) {
    $this->xsdFile = $xsd;
    $this->xsd = simplexml_load_string($xsd);
    $namespaces = $this->xsd->getDocNamespaces(TRUE);
    $schemaNs = '';

    foreach ($namespaces as $namespace => $nsuri) {
      $this->xsd->registerXPathNamespace($namespace, $nsuri);
      if ($nsuri == 'http://www.w3.org/2001/XMLSchema') {
        $schemaNs = $namespace;
      }
    }
    if ($schemaNs != '') {
      $schemaNs .= ':';
    }

    $this->schemaNs = $schemaNs;

    // Loop through all simpleTypes to get annotations
    foreach ($this->xsd->xpath('///' . $schemaNs . 'simpleType') as $element) {
      $this->parseType($element);
    }

    // Loop through all attributeGroups to get attribute definitions
    foreach ($this->xsd->xpath('///' . $schemaNs . 'attributeGroup') as $element) {
      $this->parseAttributeGroup($element);
    }

    // Loop through all elements to get the xpaths
    foreach ($this->xsd->xpath('/' . $schemaNs . 'schema/' . $schemaNs . 'element') as $element) {
      $this->parseElement($element);
    }

    return $this->elements;
  }

  /**
   * Parse element to the $this->elements array
   * @param \SimpleXMLElement $element XSD node containing element
   * @param string $parentPath
   */
  private function parseElement($element, $parentPath = '/') {

    $name = (string) $element->attributes()->name;
    $children = $element->children('xs', TRUE);
    foreach ($element->xpath($this->schemaNs . 'complexType/' . $this->schemaNs . 'attribute') as $attribute) {
      $this->elements[$parentPath . $name . '/@' . $attribute->attributes()->name] = array(
        'type' => 'attribute'
      );
    }
    foreach ($element->xpath(
               $this->schemaNs . 'complexType/' . $this->schemaNs . 'attributeGroup'
             ) as $attributeGroup) {
      $groupName = (string) $attributeGroup->attributes()->ref;
      foreach ($this->attributeGroups[$groupName] as $attribute => $meta) {
        $this->elements[$parentPath . $name . '/' . $attribute] = $meta;
      }
    }
    if ($children->count() > 0) {
      // Find both complexType/Sequence/element and complexType/All/element
      foreach ($element->xpath($this->schemaNs . 'complexType/*/' . $this->schemaNs . 'element') as $subElement) {
        $this->parseElement($subElement, $parentPath . $name . '/');
      }
    }
    elseif (isset($element->attributes()->ref)) {
      $ref = (string) $element->attributes()->ref;
      $xpathquery = '(/' . $this->schemaNs . 'schema/' . $this->schemaNs . 'element[@name="' . $ref . '"])[1]';
      $refelement = $this->xsd->xpath($xpathquery);
      if ($refelement) {
        $this->parseElement($refelement[0], $parentPath);
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

      $this->elements[$parentPath . $name] = array(
        'type' => 'element',
        'min' => $min,
        'max' => $max,
      );
      if (!empty($type) && isset($this->types[$type])) {
        $this->elements[$parentPath . $name]['annotation'] = $this->types[$type];
      }
    }
  }

  /**
   * Parse simpleTypes to $this->types to get annotations
   * @param \SimpleXMLElement $element XSD node containing simpleType
   */
  private function parseType($element) {
    $name = (string) $element->attributes()->name;
    foreach ($element->xpath($this->schemaNs . 'annotation/' . $this->schemaNs . 'documentation') as $doc) {
      $lang = (string) $doc->attributes('xml', TRUE)->lang;
      $text = (string) $doc;
      $this->types[$name][$lang] = $text;
    }
  }

  /**
   * Parse attributeGroups to $this->attributeGroups to get all attributes
   * @param \SimpleXmlElement $element XSD node containing attributeGroup
   */
  private function parseAttributeGroup($element) {
    $name = (string) $element->attributes()->name;
    $this->attributeGroups[$name] = array();
    foreach ($element->xpath($this->schemaNs . 'attribute') as $attribute) {
      $attributename = (string) $attribute->attributes()->name;
      $this->attributeGroups[$name]['@' . $attributename] = array(
        'type' => 'attribute'
      );
    }
  }
}
