<?php
/**
 * @file
 * Convert a given XSD into an array structure.
 */


/**
 * Class XsdToObject
 * TODO
 *
 * This code contains a lot of
 * @code
 * $element->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');
 * @endcode
 * lines which seems to be necessary as SimpleXML forgets it's namespaces when calling a method.
 *
 * Is this due to scope change?!?
 */
class XsdToObject {

  // TODO
  const REFERENCE_ELEMENT = 'E';

  // TODO
  const REFERENCE_TYPE = 'T';

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
   * TODO: get/set
   */
  public $debug = TRUE;


  /**
   * TODO
   *
   * @param $prefix
   * @param $array
   */
  public function addNamespaceArray($prefix, $array) {
    $newArray = array();
    foreach ($array as $element) {
      $newArray[$element['name']] = $element;
    }
    $this->foreignElements[$prefix] = $newArray;
  }

  /**
   * Parse xsd string into possible xpath's and documentation
   *
   * @param string $xsd contains XSD
   * @return array
   */
  public function parse($xsd) {
    $xsdArray = $this->parseToArray($xsd);

    $xpaths = array();
    foreach ($xsdArray as $rootElement) {
      // TODO: is this ok still
      $xpaths = array_merge($xpaths, $this->resolveElementToXpath($rootElement, '/'));
    }
    return $xpaths;
  }

  /**
   * TODO
   * @param $element
   * @param string $currentPath
   * @return array
   */
  private function resolveElementToXpath($element, $currentPath = '/') {
    $xpaths = array();
    if (!isset($element['name'])) {
      return $xpaths;
    }
    $annotations = array();
    if (isset($element['annotation'])) {
      $annotations['annotation'] = $element['annotation'];
    }
    $xpaths[$currentPath . $element['name']] = $annotations;
    if (isset($element['type'])) {
      if (isset($element['type']['attributes'])) {
        foreach ($element['type']['attributes'] as $attribute) {
          $xpaths[$currentPath . $element['name'] . '/@' . $attribute] = array();
        }
      }
      if (isset($element['type']['elements'])) {
        foreach ($element['type']['elements'] as $subelement) {
          $xpaths = $xpaths + $this->resolveElementToXpath($subelement, $currentPath . $element['name'] . '/');
        }
      }
    }
    return $xpaths;
  }

  /**
   * TODO
   * @param $xsd
   * @return array
   */
  public function parseToArray($xsd) {
    // TODO necessary?
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
    // TODO PHP 5.4 construct
    $targetNamespace = (string) $this->xsd->xpath('/' . $schemaNs . 'schema/@targetNamespace')[0];
    // TODO
    $prefixes = array_flip($this->docNamespaces);

    if (isset($prefixes[$targetNamespace])) {
      $this->selfReferencePrefix = $prefixes[$targetNamespace];
    }

    // TODO rename currentSchemaNs
    $this->schemaNs = $schemaNs;

    // Loop through everything to get reference tree
    foreach ($this->xsd->xpath('//*[@name]') as $element) {
      $type = XsdToObject::REFERENCE_ELEMENT;
      if (in_array($element->getName(), array('simpleType', 'complexType', 'attributeGroup'))) {
        $type = XsdToObject::REFERENCE_TYPE;
      }
      $this->namedElements[$type . (string) $element->attributes()->name] = $element;
      $this->namedElements[$type . $this->selfReferencePrefix . ':' . (string) $element->attributes()->name] = $element;
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
    // If this is a <element ref=""> instead of an actual element, get the referenced element and continue parsing
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
    // Remove colon
    $element->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');

    $returnElement = array(
      'name' => $name
    );

    //Check if the elements references a type or has its type info in the children
    if (isset($element->attributes()->type)) {
      $type = (string) $element->attributes()->type;
      $type = $this->getRef($type, XsdToObject::REFERENCE_TYPE);
    }
    else {
      $type = $element->xpath('(' . $this->schemaNs . 'complexType | ' . $this->schemaNs . 'simpleType)');
      $type = $type[0];
    }
    if ($type !== NULL) {
      // TODO: Not used?
      $elementname = $type->getName();
      if ($type->getName() == 'complexType') {
        $returnElement['type'] = $this->parseType($type);
      }
      else {
        $annotation = $this->parseAnnotation($type);
        if ($annotation !== NULL) {
          $returnElement['annotation'] = $annotation;
        }
      }
    }
    return $returnElement;
  }

  /**
   * TODO
   *
   * @param String|Array $ref
   * @param string $type
   * @return null|\SimpleXMLElement
   */
  private function getRef($ref, $type = XsdToObject::REFERENCE_ELEMENT) {
    if (!is_string($ref)) {
      $refname = (string) $ref[0];
    }
    else {
      $refname = $ref;
    }
    $refname = $type . $refname;
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
   * @param \SimpleXMLElement $element
   * @return null|Array
   */
  private function parseAnnotation($element) {

    $element->registerXPathNamespace(substr($this->schemaNs, 0, -1), 'http://www.w3.org/2001/XMLSchema');

    $annotations = $element->xpath($this->schemaNs . 'annotation/' . $this->schemaNs . 'documentation');
    if (!empty($annotations)) {
      $returnAnnotations = array();
      // Process each language separate.
      foreach ($annotations as $annotation) {
        $returnAnnotations[(string) $annotation->attributes('xml', TRUE)->lang] = (string) $annotation;
      }
      return $returnAnnotations;
    }
    return NULL;
  }

  /**
   * Parse ComplexType to get attributes and subnodes
   *
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
      $attributegroup = $this->getRef($ref, XsdToObject::REFERENCE_TYPE);
      $returnType['attributes'] = array_merge($returnType['attributes'], $this->parseAttributeGroup($attributegroup));
    }
    return $returnType;
  }

  /**
   * Parse SimpleXmlElement containing <attribute name="">
   *
   * @param \SimpleXmlElement $element
   * @return string
   */
  private function parseAttribute($element) {
    return (string) $element->attributes()->name;
  }

  /**
   * Parse SimpleXmlElement containing <attributeGroup name="">
   *
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
   *
   * @return Array
   */
  public function getDocNamespaces() {
    return $this->docNamespaces;
  }
}