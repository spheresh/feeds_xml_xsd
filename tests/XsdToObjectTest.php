<?php

class XsdToObjectTest extends PHPUnit_Framework_TestCase {

  /**
   * @dataProvider xpaths
   */
  function testAnnotations($xpaths) {
    $paths = $xpaths;
    $annotations = array();
    foreach ($paths as $path => $values) {
      if (isset($values['annotation'])) {
        $annotation = $values['annotation'];
        $annotations[join(",", $annotation)][] = $path;
      }
    }
    $this->assertFalse(isset($annotations['']), 'Only existing annotation should be listed');
    //$this->assertSameSize(array(1), $annotations[0], "Expected one item in annotation");
  }

  /**
   * @dataProvider values
   */
  function testGeoNovumValues($xpaths, $path, $shouldExist) {
    if ($shouldExist) {
      $this->assertArrayHasKey($path, $xpaths, $path . ' found');
    }
    else {
      $this->assertArrayNotHasKey($path, $xpaths, $path . ' not found');
    }
  }

  /**
   * @dataProvider attributes
   */
  function testGeoNovumAttributes($xpath, $path) {
    $this->assertArrayHasKey($path, $xpath, $path . ' found');
  }

  public function values() {
    $values = $this->XsdProviderBase();
    $provider = array();
    foreach ($values as $row) {
      foreach ($row['values'] as $values) {
        $provider[] = array(
          $row['xpaths'],
          $values[0],
          $values[1]
        );
      }
    }
    return $provider;
  }

  public function attributes() {
    $values = $this->XsdProviderBase();
    $provider = array();
    foreach ($values as $row) {
      foreach ($row['attributes'] as $attributes) {
        $provider[] = array(
          $row['xpaths'],
          $attributes[0]
        );
      }
    }
    return $provider;
  }

  public function context() {
    $values = $this->XsdProviderBase();
    $provider = array();
    foreach ($values as $row) {
      foreach ($row['context'] as $context) {
        $provider[] = array(
          $row['xpaths'],
          $context[0]
        );
      }
    }
    return $provider;
  }

  public function xpaths() {
    $values = $this->XsdProviderBase();
    $provider = array();
    foreach ($values as $row) {
      $provider[] = array(
        $row['xpaths']
      );
    }
    return $provider;
  }

  public function XsdProviderBase() {
    $xsdStri2012 = new XsdToObject();
    $xpathStri2012 = $xsdStri2012->parse(file_get_contents(__DIR__ . "/fixtures/STRI2012.xsd"));

    $xsdXmldsig = new XsdToObject();
    $xpathXmldsign = $xsdXmldsig->parse(file_get_contents(__DIR__ . "/fixtures/xmldsig-core-schema.xsd"));

    return array(
      array(
        'parser' => $xsdStri2012,
        'xpaths' => $xpathStri2012,
        'values' => array(
          array('/Manifest/Dossier/Plan/Naam', TRUE),
          array('/Manifest/Dossier/Plan/Datum', TRUE),
          array('/Manifest/Dossier/Naam', FALSE),
          array('/Manifest/Dossier/Datum', FALSE),
        ),
        'attributes' => array(
          array('/Manifest/@OverheidsCode'),
          array('/Manifest/Dossier/@Id'),
          array('/Manifest/Dossier/@Status'),
          array('/Manifest/Dossier/Plan/@Id'),
        )
      ),
      array(
        'parser' => $xsdXmldsig,
        'xpaths' => $xpathXmldsign,
        'values' => array(
          array('/Signature', TRUE),
          array('/Object', TRUE)
        ),
        'attributes' => array(
          array('/Signature/@Id')
        )
      )
    );
  }

}
