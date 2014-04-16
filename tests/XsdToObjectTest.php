<?php

class XsdToObjectTest extends PHPUnit_Framework_TestCase {

  private $xsd = NULL;

  function setup() {
    $this->xsd = new XsdToObject();
  }

  function testXSD() {
    // Not an XSD
    $value = '<?xml version="1.0" encoding="UTF-8"?><x/>';
    $result = $this->xsd->parse($value);
    $this->assertEmpty($result, "Not a valid XSD");

    // Smallest possible XSD
    // http://www.vijaymukhi.com/documents/books/xsd/chap9.htm
    $value = <<<EOF
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
</xs:schema>
EOF;
    $result = $this->xsd->parse($value);
    $this->assertEmpty($result, "Smallest possible XSD");

    // Having an empty simpleType
    $value = <<<EOF
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:simpleType name="Name">
  </xs:simpleType>
</xs:schema>
EOF;

    $result = $this->xsd->parse($value);
    $this->assertEmpty($result, "Smallest possible XSD");

    // element with simpleType Name
    $value = <<<EOF
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:simpleType name="Name">
    <xs:restriction base="xs:string"/>
  </xs:simpleType>
  <xs:element name="Name" type="Name"/>
</xs:schema>
EOF;

    $result = $this->xsd->parse($value);
    $this->assertNotEmpty($result, "element with simpleType Name XSD");


    // Complex type Person with First and Last name.
    $value = <<<EOF
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:simpleType name="First">
    <xs:restriction base="xs:string"/>
  </xs:simpleType>
  <xs:simpleType name="Last">
    <xs:restriction base="xs:string"/>
  </xs:simpleType>
  <xs:element name="Name" type="Name"/>
</xs:schema>
EOF;

//    //$this->markTestSkipped("Skipping missing types. We do not test for valid XSD.");
//    $result = $this->xsd->parse($value);
//    var_dump($result);
//    $this->fail("This should fail as there is no 'Name' type.");
//    $this->assertNotEmpty($result, "element with simpleType Name XSD");

  }

  /**
   * This is our test reference file.
   *
   * http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd
   */
  function testGeoNovum() {
    $uri = __DIR__ . "/fixtures/STRI2012.xsd";
    $contents = file_get_contents($uri);
    $this->assertNotEmpty($contents, "Found XSD content");

    $result = $this->xsd->parse($contents);
    var_dump($result);

    $paths = array(
      '/Manifest',
      '/Manifest/Dossier',
      '/Manifest/Dossier/Naam',
      '/Manifest',

    );

    foreach ($paths as $path) {
      $this->assertArrayHasKey($path, $result, $path . ' found');
    }
  }
}
 