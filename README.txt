Feeds XSD based Parser
===

This project parses a XML file based on a XSD Schema.

Import a XML file with XSD based mappings
---

- Install the required modules
- Configure a feed importer using this XSD Parser
- Run it.

Import a XML file then import referenced files
---

This needs feeds attached to a node.

Say you want to create nodes of type X. Then create content type feedX. Another feed creates content type Y
which are fetched by content type feedY. There is a relation between X and Y by a URL in a field from X say X.fieldY

To make this work you need to install `rules` module.

As feeds does not yet implement a rules compatible action to start an importer you need to create your own.

See the documentation for feeds and especially https://drupal.org/node/622700 which has a description for triggering this by rules.

PHP Unit
===

Run `vendor/bin/phpunit` to run the unit tests.

Manual tests
===

As an example run

```bash
$ ./tests/manual/testXsdToObject.php http://schemas.geonovum.nl/stri/2012/1.0/STRI2012.xsd

# This one fails now and then
$ ./tests/manual/testXsdToObject.php http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd
$ ./tests/manual/testXsdToObject.php http://www.w3.org/2000/07/xmldsig-core/xmldsig-core-schema.xsd
```

This gives JSON output

TBD
