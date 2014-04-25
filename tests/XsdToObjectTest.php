<?php

class XsdToObjectTest extends PHPUnit_Framework_TestCase {

  /**
   * @dataProvider annotations
   */
  function testAnnotations($xpaths, $xpath, $lang, $value) {
    $this->assertArrayHasKey('annotation', $xpaths[$xpath], 'Element has annotation');
    $this->assertArrayHasKey(
      $lang,
      $xpaths[$xpath]['annotation'],
      'Element has annotation in language "' . $lang . '"'
    );
    $this->assertSame($value, $xpaths[$xpath]['annotation'][$lang], 'Annotation is correct');
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

  /**
   * @dataProvider XsdProviderBase
   */
  function testErrors($arr) {
    $args = func_get_args();
    $file = $args[1];
    $errors = $args[6];
    $errorCount = $args[7];
    $this->assertSame($errorCount, count($errors), $file);
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

  public function annotations() {
    $values = $this->XsdProviderBase();
    $provider = array();
    foreach ($values as $row) {
      foreach ($row['annotations'] as $annotations) {
        $provider[] = array(
          $row['xpaths'],
          $annotations[0],
          $annotations[1],
          $annotations[2]
        );
      }
    }
    return $provider;
  }

  public function XsdProviderBase() {
    $result = array();

    $case = array(
      'parser' => new XsdToObject(),
      'file' => __DIR__ . "/fixtures/STRI2012.xsd",
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
      ),
      'annotations' => array(
        array('/Manifest/Dossier/Plan/Naam', 'nl', "waarde is gelijk aan IMRO:naam van het\n\t\t\t\tinstrument")
      )
    );
    $case['xpaths'] = $case['parser']->parse(file_get_contents($case['file']));
    $case['errors'] = $case['parser']->getErrors();
    $case['error_count'] = 6;
    $result[] = $case;

    $case = array(
      'parser' => new XsdToObject(),
      'file' => __DIR__ . "/fixtures/xmldsig-core-schema.xsd",
      'values' => array(
        array('/Signature', TRUE),
        array('/Object', TRUE)
      ),
      'attributes' => array(
        array('/Signature/@Id')
      ),
      'annotations' => array()
    );
    $case['xpaths'] = $case['parser']->parse(file_get_contents($case['file']));
    $case['errors'] = $case['parser']->getErrors();
    $case['error_count'] = 18;
    $result[] = $case;

    $case = array(
      'parser' => new XsdToObject(),
      'file' => __DIR__ . "/fixtures/STRI2012.xsd",
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
      ),
      'annotations' => array(
        array('/Manifest/Dossier/Plan/Naam', 'nl', "waarde is gelijk aan IMRO:naam van het\n\t\t\t\tinstrument")
      )
    );
    $parser = new XsdToObject();
    $xml_ds = $parser->parseToArray(file_get_contents( __DIR__ . "/fixtures/xmldsig-core-schema.xsd"));
    $case['parser']->addNamespaceArray('ds', $xml_ds);

    $case['xpaths'] = $case['parser']->parse(file_get_contents($case['file']));
    $case['errors'] = $case['parser']->getErrors();
    $case['error_count'] = 4;
    $result[] = $case;

    return $result;

  }

}
