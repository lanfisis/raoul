<?php
/**
 * Generator
 *
 * @category  Raoul
 * @author    David Buros <david.buros@gmail.com>
 * @copyright 2014 David Buros
 * @licence   WTFPL see LICENCE.md file
 */

namespace Raoul;

use \DOMDocument;
use \DOMElement;
use \DOMXpath;
use Deflection\Element\Classes;
use Deflection\Element\Docblock;
use Deflection\Element\Param;
use Deflection\Element\Functions;
use Deflection\Generator as ClassGenerator;

class Generator
{
    /**
     * WDSL to work with
     *
     * @var string
     */
    protected $wsdl = array();

    /**
     * Service name
     *
     * @var string
     */
    protected $service;

    /**
     * Folder path where generated the service classes
     * Current folder by default
     *
     * @var string
     */
    protected $folder = '.';

    /**
     * Namespace for generated services
     *
     * @var string
     */
    protected $namespace;

    /**
     * Class header additional informations
     *
     * @var array
     */
    protected $header = array();

    /**
     * Generate child class to overwride method
     *
     * @var boolean
     */
    protected $overwrite = true;


    /**
     * Elements
     *
     * @var array
     */
    protected $elements = array();


    /**
     * All file paths
     *
     * @var array
     */
    protected $paths = array();

    /**
     * Contructor!
     *
     * @param string $wsdl    WSDL
     * @param string $service Service name
     *
     * @return void
     */
    public function __construct($wsdl, $service)
    {
        $this->setWsdl($wsdl);
        $this->setService($service);
    }

    /**
     * Returns WSDL
     *
     * @return array
     */
    public function getWsdl()
    {
        return $this->wsdl;
    }

    /**
     * Set WSDL
     *
     * @param string $wsdl WSDL path
     *
     * @return \Raoul\Generator
     */
    public function setWsdl($wsdl)
    {
        $this->wsdl = $wsdl;
        return $this;
    }

    /**
     * Returns service name
     *
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Set service name
     *
     * @param string $service Service name
     *
     * @return \Raoul\Generator
     */
    public function setService($service)
    {
        $this->service = $service;
        return $this;
    }


    /**
     * Get element by name
     *
     * @param string $name Element name
     *
     * @return Deflection\Element\Classes
     */
    public function getElement($name)
    {
        return $this->elements[$name];
    }

    /**
     * Get elements
     *
     * @return array
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * Set element class by name
     *
     * @param string                     $name  Element name
     * @param Deflection\Element\Classes $class Element class
     *
     * @return \Raoul\Generator
     */
    public function addElement($name, $class)
    {
        $this->elements[$name] = $class;
        return $this;
    }


    /**
     * Returns folder path where generated the service classes
     *
     * @return string
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * Set folder path where generated the service classes
     *
     * @param string $folder Absolute or relative path
     *
     * @return \Raoul\Generator
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * Returns namespace for generated services
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set namespace for generated services
     *
     * @param string $namespace Namespace php style
     *
     * @return \Raoul\Generator
     */
    public function setNamespace($namespace)
    {
        $this->namespace = (string)$namespace;
        return $this;
    }

    /**
     * Returns header docblock additional information
     *
     * @return array
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Set class header docblock additional information as author,
     * copyright, ...
     *
     * @param array $header Header infos
     *
     * @return \Raoul\Generator
     */
    public function setHeader(array $header)
    {
        $this->header = $header;
        return $this;
    }

    /**
     * Define if we need to generate child class
     *
     * @return boolean
     */
    public function hasOverwrite($status = null)
    {
        $this->overwrite = $status !== null ? $status : $this->overwrite;
        return $this->overwrite ;
    }

    /**
     * Get all file paths
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Add a file path
     *
     * @param string $path A file path
     *
     * @return \Raoul\Generator
     */
    public function addPath($path)
    {
        $this->paths[] = $path;
        return $this;
    }

    /**
     *
     * @param type $bootstrap
     *
     * @return \Raoul\Generator|boolean
     */
    public function generate($bootstrap = false)
    {
        if (!$this->isValidWsdlFilename($this->wsdl)) {
            return false;
        }

        $service = $this->getService();
        $document = new DOMDocument();
        $document->load($this->wsdl);

        $elements = $this->extractElements($document);
        $types = $this->getTypesDefinitionsFromElements($elements);
        $classes = $this->getTypeClassesFromDefinitions($types, $service.'\Type');
        $this->createFilesFromClasses($classes);

        $operations = $this->extractOperations($document);
        $methods = $this->getMethodsDefinitionsFromOperations($operations);
        $services = $this->getServiceClassesFromDefinitions($methods, $service);
        $this->createFilesFromClasses($services);

        if ($bootstrap) {
            $this->generateBoostrap($service);
        }

        return $this;
    }

    /**
     * Generate a boostrap file with all include
     *
     * @param string $service Service name
     *
     * @return \Raoul\Generator
     */
    protected function generateBoostrap($service)
    {
        $content = '<?php'."\n";
        foreach ($this->getPaths() as $file) {
            $content .= 'require_once __DIR__."/'.$file.'"'.";\n";
        }
        $filename = rtrim($this->getFolder(), '/').'/'.$service.'Bootstrap.php';
        !file_exists($filename) ?: unlink($filename);
        file_put_contents($filename, $content);
        chmod($filename, 0777);
        return $this;
    }


    /**
     * Generate php class files
     *
     * @param array $classes Array of classes
     *
     * @return \Raoul\Generator
     */
    protected function createFilesFromClasses(array $classes)
    {
        foreach ($classes as $class) {
            $path = $this->generateFolderByNamespace($class->getNamespace());
            if ($path) {
                $generator = new ClassGenerator($class);
                $filename = $path.'/'.$class->getName().'.php';
                !file_exists($filename) ?: unlink($filename);
                file_put_contents($filename, $generator->asString());
                chmod($filename, 0777);
                $file = str_replace('\\', '/', $class->getNamespace()).'/'.$class->getName().'.php';
                $this->addPath($file);
            }
        }
        return $this;
    }

    /**
     * Generate type classes from definitions
     *
     * @param array  $definitions Class definitions
     * @param string $namespace   Namsapce used
     * @param string $service     Service name
     *
     * @return array
     */
    protected function getTypeClassesFromDefinitions(array $definitions, $namespace)
    {
        $classes = array();
        $namespace = ($this->getNamespace() ? $this->getNamespace().'\\' : '').$namespace;
        foreach ($definitions as $name => $definition) {
            $class = new Classes();
            $class->setName(ucfirst($name));
            $class->setNamespace($namespace);
            $class->setDocblock($this->getDocBlock('Model for '.ucfirst($name)));

            if ($this->hasOverwrite()) {
                $parent = new Classes();
                $parent->setName(ucfirst($name));
                $parent->setNamespace($namespace.'\Base');
                $parent->setDocblock($this->getDocBlock('Parent model for '.ucfirst($name)));
                $parent->isAbstract(true);
                $classes[] = $parent;

                $class->addUse($namespace.'\Base\\'.ucfirst($name), ucfirst($name).'Base');
                $class->setExtends($name.'Base');
            }

            $this->addParamsFromDefinition(isset($parent)?$parent:$class, $definition);
            $this->addConstructFromDefinition(isset($parent)?$parent:$class, $definition);
            $this->addMethodsFromDefinition(isset($parent)?$parent:$class, $definition);
            $this->addElement($name, $class);
            $classes[] = $class;
        }
        return $classes;
    }

    /**
     * Generate service class from definition
     *
     * @param array  $definition Class definition
     * @param string $service    Service name
     *
     * @return array
     */
    protected function getServiceClassesFromDefinitions(array $definition, $service)
    {
        $classes = array();
        $namespace = $this->getNamespace();
        $name = ucfirst($service);
        $class = new Classes();
        $class->setName($name);
        $class->setNamespace($namespace);
        $class->setDocblock($this->getDocBlock('Proxy for '.$name.' service'));
        $class->setExtends('\SoapClient');

        if ($this->hasOverwrite()) {
            $parent = new Classes();
            $parent->setName($name);
            $parent->setNamespace($namespace.'\Base');
            $parent->setDocblock($this->getDocBlock('Parent model for proxy for '.$name.' service'));
            $parent->setExtends('\SoapClient');
            $parent->isAbstract(true);
            $classes[] = $parent;

            $class->addUse($namespace.'\Base\\'.$name, $name.'Base');
            $class->setExtends($name.'Base');
        }

        $this->addParamsForService(isset($parent)?$parent:$class);
        $this->addConstructForService(isset($parent)?$parent:$class);
        $this->addServicesFromDefinition(isset($parent)?$parent:$class, $definition);

        $classes[] = $class;
        return $classes;
    }

    /**
     * Add service constructor
     *
     * @param Classes $class Class
     *
     * @return \Raoul\Generator
     */
    protected function addConstructForService(Classes $class)
    {
        $docblock = new Docblock();
        $docblock->setDescription('Service contructor');
        $docblock->addParam('return', 'void');
        $docblock->addVar('wsdl', 'string', 'URI of the WSDL file');
        $docblock->addVar('options', 'array', 'An array of options');

        $function = new Functions();
        $function->setDocblock($docblock);
        $function->isPublic(true);
        $function->setName('__construct');
        $function->addParam('wsdl');
        $function->addParam('options = array()', 'array');
        $function->setContent(array(
            'foreach ($this->classmap as $key => $value) {',
            array(
                'if (!isset($options[\'classmap\'][$key])) {',
                array('$options[\'classmap\'][$key] = $value;'),
                '}',
            ),
            '}',
            'parent::__construct($wsdl, $options);',
        ));

        $class->addFunction($function);
        return $this;
    }

    /**
     * Add specific params for service class
     *
     * @param Classes $class Class
     *
     * @return \Raoul\Generator
     */
    protected function addParamsForService(Classes $class)
    {
        $docblock= new Docblock();
        $docblock->setDescription('Class mapping for SOAP client');
        $docblock->addParam('var', 'array');

        $length = max(array_map('mb_strlen', array_keys($this->getElements()))) + 1;
        $classmap = array_map(
            function($model, $element) use ($length) {
                $key = str_pad($element.'\'', $length);
                return '\''.$key.' => \''.$model->getFullName().'\',';
            },
            $this->getElements(),
            array_keys($this->getElements())
        );

        $param = new Param();
        $param->setName('classmap');
        $param->isProtected();
        $param->setDocblock($docblock);
        array_unshift($classmap, 'array(');
        array_push($classmap, ')');
        $param->setValue($classmap);

        $class->addParam($param);
        return $this;
    }

    protected function addServicesFromDefinition(Classes $class, $definition)
    {
        foreach ($definition as $method => $infos) {
            $docblock = new Docblock();
            $docblock->setDescription('Method '.$method);
            $return = $this->getElement(array_pop($infos['outputs']));

            $docblock->addParam('return', $this->getType($return->getName(), $return->getNamespace()));

            $function = new Functions();
            $function->setDocblock($docblock);
            $function->isPublic(true);
            $function->setName(lcfirst($method));
            $params = array();

            foreach ($infos['inputs'] as $name => $type) {
                $type = $this->getElement($type);
                $docblock->addVar($name, $this->getType($type->getName(), $type->getNamespace()), 'Value of '.$name);
                $class->addUse($this->getType($type->getName(), $type->getNamespace()));
                $function->addParam($name, $this->getArgType($type->getName()));
                $params[] = '$'.$name;
            }
            $content = array(
                'return $this->__soapCall("'.$method.'", array('.implode(',', $params).'));'
            );
            $function->setContent($content);
            $class->addFunction($function);
        }
        return $this;
    }


    /**
     * Add all param to class with public scope to be fill by php SOAPClient
     *
     * @param Deflection\Element\Classes $class      Current class
     * @param array                      $definition Param definition
     *
     * @return \Raoul\Generator
     */
    protected function addParamsFromDefinition(Classes $class, $definition)
    {
        foreach ($definition['all'] as $name => $infos) {
            $param = new Param();
            $docblock = new Docblock();
            $description = ucfirst($name);
            if ($infos['nullable'] === true) {
                $param->setValue('null');
            }
            if ($infos['collection'] === true) {
                $description = $description.' collection';
                $param->setValue('array()');
            }

            $docblock->setDescription($description);
            $docblock->addParam('var', $this->getType($infos['type'], $class->getNamespace()));

            $param->setName($name);
            $param->isPublic();
            $param->setDocblock($docblock);
            $class->addParam($param);
        }
        return $this;
    }

    /**
     * Add constructor method with mandatory params
     *
     * @param Deflection\Element\Classes $class      Current class
     * @param array                      $definition Param definition
     *
     * @return \Raoul\Generator
     */
    protected function addConstructFromDefinition(Classes $class, $definition)
    {
        if (count($definition['mandatory']) > 0) {
            $content = array();
            $docblock = new Docblock();
            $docblock->setDescription('Construct '.$class->getName());

            $function = new Functions();
            $function->setDocblock($docblock);
            $function->isPublic(true);
            $function->setName('__construct');
            foreach ($definition['mandatory'] as $name => $infos) {
                $docblock->addParam('return', 'void');
                $docblock->addVar($name, $this->getType($infos['type'], $class->getNamespace()), 'Value of '.$name);
                $function->addParam($name.' = null', $this->getArgType($infos['type'], $class->getNamespace()));
                $content[] = '$this->'.$name.($infos['collection'] === true ? '[]' : '').' = $'.$name.';';
            }
            $function->setContent($content);
            $class->addFunction($function);
        }
        return $this;
    }

    /**
     * Add getter and setter for each param
     *
     * @param Deflection\Element\Classes $class      Current class
     * @param array                      $definition Param definition
     *
     * @return \Raoul\Generator
     */
    protected function addMethodsFromDefinition(Classes $class, $definition)
    {
        foreach ($definition['all'] as $name => $infos) {
            foreach (array('get', 'set') as $type) {
                $method = $type.ucfirst($name);
                $description = ucfirst($type).' '.$name;
                $docblock = new Docblock();

                $function = new Functions();
                $function->setDocblock($docblock);
                $function->isPublic(true);
                $function->setName($method);

                if ($type == 'set') {
                    $method = ($infos['collection'] === true) ? 'add'.ucfirst($name) : $method;
                    $description = ($infos['collection'] === true) ? 'Add element on '.$name.' collection' : $description;
                    $docblock->setDescription($description);
                    $docblock->addParam('return', $class->getNamespace().'\\'.$class->getName());
                    $docblock->addVar('value', $this->getType($infos['type'], $class->getNamespace()), $name);
                    $function->addParam(
                        'value'.($infos['nullable'] === true ? ' = null' : ''),
                        $this->getArgType($infos['type'], $class->getNamespace())
                    );
                    $function->setContent(array(
                        '$this->'.$name.($infos['collection'] === true ? '[]' : '').' = $value;',
                        'return $this;',
                    ));
                } else {
                    $docblock->setDescription($description);
                    $docblock->addParam('return', $this->getType($infos['type'], $class->getNamespace()));
                    $function->setContent(array(
                        'return $this->'.$name.';',
                    ));
                }

                $class->addFunction($function);
            }
        }
        return $this;
    }

    /**
     * Returns docblock element
     *
     * @param string $description Description
     *
     * @return Deflection\Element\Docblock
     */
    protected function getDocBlock($description)
    {
        $docblock = new Docblock();
        $docblock->setDescription($description);
        $docblock->setParams($this->getHeader());
        return $docblock;
    }

    /**
     * Returns element from wsdl as DOMElement
     *
     * @param DOMDocument $document WSDl as XML Document
     *
     * @return array
     */
    protected function extractElements(DOMDocument $document)
    {
        $elements = array();
        $complexType = $document->getElementsByTagName('complexType');
        foreach ($complexType as $element) {
            if ($element->getAttribute('name') === '') {
                $element = $element->parentNode;
            }
            $elements[] = $element;
        }
        return $elements;
    }

    /**
     * Returns services wsdl as DOMElement
     *
     * @param DOMDocument $document WSDl as XML Document
     *
     * @return array
     */
    protected function extractOperations(DOMDocument $document)
    {
        $elements = array();
        $xpath = new DOMXpath($document);
        $operations = $xpath->query("/*/wsdl:portType/wsdl:operation");
        foreach ($operations as $operation) {
            $input = $operation->getElementsByTagName('input')->item(0);
            $output = $operation->getElementsByTagName('output')->item(0);
            $request = $xpath->query("/*/wsdl:message[@name='".$input->getAttribute('name')."']")->item(0);
            $response = $xpath->query("/*/wsdl:message[@name='".$output->getAttribute('name')."']")->item(0);
            $elements[$operation->getAttribute('name')] = array(
                'input'  => $this->childToArray($request, 'part'),
                'output' => $this->childToArray($response, 'part'),
            );
        }
        return $elements;
    }

    /**
     * DOMList to array
     *
     * @param DOMElement $element DOM Element
     * @param string     $name    Attribute name
     *
     * @return type
     */
    protected function childToArray($element, $name)
    {
        $childs = array();
        $elements = $element->getElementsByTagName($name);
        foreach ($elements as $child) {
            $childs[] = $child;
        }
        return $childs;
    }

    /**
     * Returns informations require to create type models
     *
     * @param array  $elements DOMELement who represents WSDL elements
     *
     * @return array
     */
    protected function getTypesDefinitionsFromElements(array $elements)
    {
        $types = array();
        foreach ($elements as $element) {
            $params = array();
            $attributes = $element->getElementsByTagName('element');
            foreach ($attributes as $attribute) {
                $type = explode(':', $attribute->getAttribute('type'));
                $infos = array (
                    'mandatory'  => $this->isValid($attribute, 'minOccurs', '1'),
                    'type'       => $this->isValid($attribute, 'maxOccurs', 'unbounded') ? 'array' : $type[1],
                    'collection' => $this->isValid($attribute, 'maxOccurs', 'unbounded'),
                    'nullable'   => $this->isValid($attribute, 'nillable', 'true'),
                );
                $params['all'][$attribute->getAttribute('name')] = $infos;
                $params['mandatory'] = array();
                if ($this->isValid($attribute, 'minOccurs', '1')) {
                    $params['mandatory'][$attribute->getAttribute('name')] = $infos;
                }

            }
            $types[$element->getAttribute('name')] = $params;
        }
        return $types;
    }

    /**
     * Returns informations require to create service methods
     *
     * @param array $elements DOMELement who represents WSDL operations
     *
     * @return array
     */
    protected function getMethodsDefinitionsFromOperations(array $elements)
    {
        $methods = array();
        foreach ($elements as $name => $element) {
            $inputs = $outputs = array();
            foreach ($element['input'] as $input) {
                $type = explode(':', $input->getAttribute('element'));
                $inputs[$input->getAttribute('name')] = $type[1];
            }
            foreach ($element['output'] as $output) {
                $type = explode(':', $output->getAttribute('element'));
                $outputs[$output->getAttribute('name')] = $type[1];
            }
            $methods[$name] = array(
                'inputs' => $inputs,
                'outputs' => $outputs,
            );
        }
        return $methods;
    }

    /**
     * Valide if it's a correct WSDL filename
     *
     * @param string $filename WSDL filename
     *
     * @return boolean
     */
    protected function isValidWsdlFilename($filename)
    {
        return file_exists($filename);
    }

    /**
     * Verify if a DOMElement attribute exists and if its value is ok
     *
     * @param DOMElement $element   Element to work wit
     * @param string     $attribute DOMElement attribute
     * @param string     $value     Value to verify
     *
     * @return boolean
     */
    protected function isValid(DOMElement $element, $attribute, $value)
    {
        return $element->getAttribute($attribute)
            and $element->getAttribute($attribute) == $value;
    }

    /**
     * Format type
     *
     * @param string $type      Actual type
     * @param string $namespace Namespace
     *
     * @return string
     */
    protected function getType($type, $namespace = null)
    {
        switch ($type) {
            case 'int':
            case 'string':
            case 'array':
            case 'boolean':
                return $type;
            case 'long':
                return 'int';
            case 'double':
                return 'float';
            case 'date':
            case 'dateTime':
                return 'string';
            default:
                return $namespace ? $namespace.'\\'.$type : $type;
        }
    }

    /**
     * Return arg type
     *
     * @param string $type      Actual type
     * @param string $namespace Namespace
     *
     * @return string
     */
    protected function getArgType($type, $namespace = null)
    {
        $formated = $this->getType($type, $namespace);
        if (!in_array($formated, array('int', 'string', 'array', 'boolean'))) {
            return $formated;
        }
        return null;
    }

    /**
     * Create folder by namesapce if not exists
     * Return path
     *
     * @param string $namespace Namesapce
     *
     * @return string
     */
    protected function generateFolderByNamespace($namespace)
    {
        $path = rtrim($this->getFolder(), '/').'/'.str_replace('\\', '/', $namespace);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }
}