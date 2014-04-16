<?php

class XsdToObjectTest extends PHPUnit_Framework_TestCase {

  private $xsd = NULL;

  function setup() {
    $this->xsd = new XsdToObject();
    $this->setupGeoNovum();
  }

  function setupGeoNovum() {
    $uri = __DIR__ . "/fixtures/STRI2012.xsd";
    $contents = file_get_contents($uri);
    $this->assertNotEmpty($contents, "Found XSD content");
    $this->geonovum = $this->xsd->parse($contents);
  }

  /**
   * This is our test reference file.
   *
   * http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd
   *
   * @dataProvider values
   */
  function testGeoNovumValues($path) {
    $this->assertArrayHasKey($path, $this->geonovum, $path . ' found');
  }

  /**
   * @dataProvider attributes
   */
  function testGeoNovumAttributes($path) {
    $this->assertArrayHasKey($path, $this->geonovum, $path . ' found');
  }

  /**
   * @dataProvider context
   */
  function testGeoNovumContext($path) {
    $this->assertArrayHasKey($path, $this->geonovum, $path . ' found');
  }

  public function values() {
    return $paths = array(
      array('/Manifest/Dossier/Naam'),
    );
  }

  public function attributes() {
    return array(
      array('/Manifest/@OverheidsCode'),
      array('/Manifest/Dossier/@Id'),
      array('/Manifest/Dossier/@Status'),
    );
  }

  public function context() {
    return array(
      array('/Manifest'),
      array('/Manifest/Dossier'),
    );
  }

}
