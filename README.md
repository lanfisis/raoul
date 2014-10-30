A library to generate php class from a WSDL

How it works
============

```php
$generator = new Raoul\Generator('myawesomeservice.wsdl', 'MyAwesomeService');
$generator->setNamespace('MyCompany');
$generator->setFolder(__DIR__.'/src');
$generator->setHeader(array (
    'author'    => 'David Buros <david.buros@gmail.com>',
    'copyright' => '2014 David Buros',
));
$generator->generate(true);
```

Licence
=======
DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
Read term on LICENCE.md file