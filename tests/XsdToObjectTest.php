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
    $this->geonovum = $this->xsd->parse($contents);

    $this->geonovumContext = array_unique(array_map('dirname', array_keys($this->geonovum)));
    static $printed = FALSE;
    if (!$printed) {
      $printed = TRUE;
      print_r(array_keys($this->geonovum));
      print_r($this->geonovumContext);
    }
  }

  /**
   * This is our test reference file.
   *
   * http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd
   *
   * @dataProvider values
   */
  function testGeoNovumValues($path, $ok = 'X') {
    if ($ok == 'X') {
      $this->assertArrayHasKey($path, $this->geonovum, $path . ' found');
    }
    else {
      $this->assertArrayNotHasKey($path, $this->geonovum, $path . ' not found');
    }
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
    $this->assertTrue(in_array($path, $this->geonovumContext), $path . ' found');
  }

  public function values() {
    return $paths = array(
      array('/Manifest/Dossier/Plan/Naam'),
      array('/Manifest/Dossier/Plan/Datum'),
      array('/Manifest/Dossier/Naam', 'F'),
      array('/Manifest/Dossier/Datum', 'F'),
    );
  }

  public function attributes() {
    return array(
      array('/Manifest/@OverheidsCode'),
      array('/Manifest/Dossier/@Id'),
      array('/Manifest/Dossier/@Status'),
      array('/Manifest/Dossier/Plan/@Id'),
    );
  }

  public function context() {
    return array(
      array('/Manifest'),
      array('/Manifest/Dossier'),
    );
  }

}
