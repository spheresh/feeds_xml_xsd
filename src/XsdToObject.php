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
   * @var
   */
  private $docNamespaces;

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
   * @var string
   * Namespace containing http://www.w3.org/2001/XMLSchema
   */
  private $schemaNs;

  /**
   * List of all \SimpleXMLElements with name="" attribute
   * @var array
   */
  private $namedElements = array();

  /**
   * Array of parsed elements from other XSD scheme files
   * @var array
   */
  private $foreignElements = array();

  /**
   * @var
   */
  private $selfReferencePrefix;

  /**
   * @var bool
   */
  public $debug = TRUE;


  public function addNamespaceArray($prefix, $array) {
    $newArray = array();
    foreach ($array as $element) {
      $newArray[$element['name']] = $element;
    }
    $this->foreignElements[$prefix] = $newArray;
  }

  /**
   * Parse xsd string into possible xpath's and documentation
   * @param string $xsd string containing xsd file
   * @return array
   */
  public function parse($xsd) {
    $xsdArray = $this->parseToArray($xsd);

    $xpaths = array();
    foreach ($xsdArray as $rootElement) {
      $xpaths = array_merge($xpaths, $this->resolveElementToXpath($rootElement, '/'));
    }
    return $xpaths;
  }

  /**
   * @param $element
   * @param string $currentPath
   * @return array
   */
  private function resolveElementToXpath($element, $currentPath = '/') {
    $xpaths = array();
    if (!isset($element['name'])) {
      return $xpaths;
    }
    $xpaths[] = $currentPath . $element['name'];
    if (isset($element['type'])) {
      if (isset($element['type']['attributes'])) {
        foreach ($element['type']['attributes'] as $attribute) {
          $xpaths[] = $currentPath . $element['name'] . '/@' . $attribute;
        }
      }
      if (isset($element['type']['elements'])) {
        foreach ($element['type']['elements'] as $subelement) {
          $xpaths = array_merge(
            $xpaths,
            $this->resolveElementToXpath($subelement, $currentPath . $element['name'] . '/')
          );
        }
      }
    }
    return $xpaths;
  }

  /**
   * @param $xsd
   * @return array
   */
  public function parseToArray($xsd) {
    $this->xsdFile = $xsd;
    $this->xsd = simplexml_load_string($xsd);
    $this->docNamespaces = $this->xsd->getDocNamespaces(TRUE);
    $schemaNs = '';

    foreach ($this->docNamespaces as $namespace => $nsuri) {
      if ($nsuri == 'http://www.w3.org/2001/XMLSchema') {
        $schemaNs = $namespace;
      }
      if ($namespace == '') {
        //If the elements don't have a namespace prefix and xmlns="..." is set then this is needed to run xpaths.
        $this->xsd->registerXPathNamespace('xsdparser', $nsuri);
      }
    }
    if ($schemaNs != '') {
      $schemaNs .= ':';
    }
    else {
      $schemaNs = 'xsdparser:';
    }
    $targetNamespace = (string) $this->xsd->xpath('/' . $schemaNs . 'schema/@targetNamespace')[0];
    $prefixes = array_flip($this->docNamespaces);

    if (isset($prefixes[$targetNamespace])) {
      $this->selfReferencePrefix = $prefixes[$targetNamespace];
    }

    $this->schemaNs = $schemaNs;

    // Loop through everything to get reference tree
    foreach ($this->xsd->xpath('//*[@name]') as $element) {
      $this->namedElements[(string) $element->attributes()->name] = $element;
      $this->namedElements[$this->selfReferencePrefix . ':' . (string) $element->attributes()->name] = $element;
    }

    // Loop through all root elements to start building the tree
    $tree = array();
    foreach ($this->xsd->xpath('/' . $schemaNs . 'schema/' . $schemaNs . 'element') as $element) {
      $tree[] = $this->parseElement($element);
    }
    return $tree;
  }

  /**
   * Parse element or element reference
   * @param \SimpleXMLElement $element XSD node containing element
   * @return array
   */
  private function parseElement($element) {
    $element->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');
    // If this is a <element ref=""> instead of an actual element, get the actual element and continue parsing
    $ref = $element->attributes()->ref;
    if ($ref !== NULL) {
      $element = $this->getRef($ref);
      if ($element === NULL) {
        return array();
      }
    }
    if (is_array($element)) {
      return $element;
    }
    $name = (string) $element->attributes()->name;
    $element->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');


    $returnElement = array(
      'name' => $name
    );

    //Check if the elements references a type or has its type info in the children
    if (isset($element->attributes()->type)) {
      $type = (string) $element->attributes()->type;
      $type = $this->getRef($type);
    }
    else {
      $type = $element->xpath('(' . $this->schemaNs . 'complexType | ' . $this->schemaNs . 'simpleType)');
      $type = $type[0];
    }
    if ($type !== NULL) {
      $returnElement['type'] = $this->parseType($type);
    }
    return $returnElement;
  }

  /**
   * @param String|Array $ref
   * @return null|\SimpleXMLElement
   */
  private function getRef($ref) {
    if (!is_string($ref)) {
      $refname = (string) $ref[0];
    }
    else {
      $refname = $ref;
    }
    if (isset($this->namedElements[$refname])) {
      return $this->namedElements[$refname];
    }
    $part = explode(':', $refname, 2);
    if (count($part) > 1 && isset($this->foreignElements[$part[0]])) {
      return $this->foreignElements[$part[0]][$part[1]];
    }
    if ($this->debug) {
      echo 'Reference resolve failed: ' . $refname;
    }
    return NULL;
  }

  /**
   * Parse ComplexType to get attributes and subnodes
   * @param \SimpleXMLElement $element XSD node containing simpleType
   * @return Array
   */
  private function parseType($element) {
    $element->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');
    $path = '(' . $this->schemaNs . 'sequence | ' . $this->schemaNs . 'all | ' . $this->schemaNs . 'choice)[last()]';
    $container = $element->xpath($path);
    $returnType = array();
    if (count($container) > 0) {
      $container = $container[0];


      $container->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');
      $elements = $container->xpath($this->schemaNs . 'element');
      foreach ($elements as $subElement) {
        $returnType['elements'][] = $this->parseElement($subElement);
      }
    }

    $attributes = $element->xpath($this->schemaNs . 'attribute');
    foreach ($attributes as $attribute) {
      $returnType['attributes'][] = $this->parseAttribute($attribute);
    }
    $attributegroups = $element->xpath($this->schemaNs . 'attributeGroup');
    foreach ($attributegroups as $attributegroup) {
      if (!isset($returnType['attributes'])) {
        $returnType['attributes'] = array();
      }
      $ref = $attributegroup->attributes()->ref;
      $attributegroup = $this->getRef($ref);
      $returnType['attributes'] = array_merge($returnType['attributes'], $this->parseAttributeGroup($attributegroup));
    }
    return $returnType;
  }

  /**
   * Parse SimpleXmlElement containing <attribute name="">
   * @param \SimpleXmlElement $element
   * @return string
   */
  private function parseAttribute($element) {
    return (string) $element->attributes()->name;
  }

  /**
   * Parse SimpleXmlElement containing <attributeGroup name="">
   * @param \SimpleXmlElement $element XSD node containing attributeGroup
   * @return array
   */
  private function parseAttributeGroup($element) {
    $attributes = array();
    foreach ($element->xpath($this->schemaNs . 'attribute') as $attribute) {
      $attributes[] = $this->parseAttribute($attribute);
    }
    return $attributes;
  }

  /**
   * Get all namespaces used in this document
   * @return Array
   */
  public function getDocNamespaces() {
    return $this->docNamespaces;
  }
}