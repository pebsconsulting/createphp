<?php
/**
 * @copyright CONTENT CONTROL GbR, http://www.contentcontrol-berlin.de
 * @author David Buchmann <david@liip.ch>
 * @license Dual licensed under the MIT (MIT-LICENSE.txt) and LGPL (LGPL-LICENSE.txt) licenses.
 * @package Midgard.CreatePHP
 */

namespace Midgard\CreatePHP\Metadata;

use Midgard\CreatePHP\RdfMapperInterface;
use Midgard\CreatePHP\Entity\Controller as Type;
use Midgard\CreatePHP\Entity\Property as PropertyDefinition;
use Midgard\CreatePHP\Entity\Collection as CollectionDefinition;
use Midgard\CreatePHP\Type\TypeInterface;
use Midgard\CreatePHP\Type\PropertyDefinitionInterface;

/**
 * This driver loads rdf mappings from xml files
 *
 * <type
 *      xmlns:sioc="http://rdfs.org/sioc/ns#"
 *      xmlns:dcterms="http://purl.org/dc/terms/"
 *      xmlns:skos="http://www.w3.org/2004/02/skos/core#"
 *      typeof="sioc:Post"
 * >
 *      <config key="my" value="value"/>
 *      <children>
 *          <property property="dcterms:title" identifier="title" tag-name="h2"/>
 *          <collection rel="skos:related" identifier="tags" tag-name="ul">
 *              <config key="my" value="value"/>
 *              <attribute key="class" value="tags"/>
 *          </collection>
 *          <property property="sioc:content" identifier="content" />
 *      </children>
 * </type>
 *
 * @package Midgard.CreatePHP
 */
class RdfDriverXml extends AbstractRdfDriver
{
    private $directories = array();

    /**
     * @param array $directories list of directories to look for rdf metadata
     */
    public function __construct($directories)
    {
        $this->directories = $directories;
    }

    /**
     * Return the type for the specified class
     *
     * @param string $className
     * @param RdfMapperInterface $mapper
     *
     * @return \Midgard\CreatePHP\Type\TypeInterface|null the type if found, otherwise null
     */
    function loadTypeForClass($className, RdfMapperInterface $mapper, RdfTypeFactory $typeFactory)
    {
        $xml = $this->getXmlDefinition($className);
        if (null == $xml) {
            return null;
        }

        $type = new Type($mapper, $this->getConfig($xml));

        foreach ($xml->getDocNamespaces(true) as $prefix => $uri) {
            $type->setVocabulary($prefix, $uri);
        }
        if (isset($xml['typeof'])) {
            $type->setRdfType($xml['typeof']);
        }
        foreach($xml->children->children() as $child) {
            switch($child->getName()) {
                case 'property':
                    $prop = new PropertyDefinition($child['identifier'], $this->getConfig($child));
                    $this->parseChild($prop, $child, $child['identifier'], $add_default_vocabulary);
                    $type->$child['identifier'] = $prop;
                    break;
                case 'collection':
                    $col = new CollectionDefinition($child['identifier'], $typeFactory, $this->getConfig($child));
                    $this->parseChild($col, $child, $child['identifier'], $add_default_vocabulary);
                    $type->$child['identifier'] = $col;
                    break;
            }
        }

        return $type;
    }

    /**
     * Build the attributes from the property|rel field and any custom attributes
     *
     * @param \ArrayAccess $child the child to read field from
     * @param string $field the field to be read, property for properties, rel for collections
     * @param string $identifier to be used in case there is no property field in $child
     * @param boolean $add_default_vocabulary flag to tell whether to add vocabulary for
     *      the default namespace.
     *
     * @return array properties
     */
    protected function parseChild($prop, $child, $identifier, &$add_default_vocabulary)
    {
        $type = $prop instanceof PropertyDefinitionInterface ? 'property' : 'rel';
        $attributes = array(
            $type => $this->buildInformation($child, $identifier, $type, $add_default_vocabulary)
        );
        if (isset($child->attribute)) {
            foreach ($child->attribute as $attribute) {
                $attributes[(string)$attribute['key']] = (string)$attribute['value'];
            }
        }
        $prop->setAttributes($attributes);
        if (isset($child['tag-name'])) {
            $prop->setTagName($child['tag-name']);
        }
    }

    /**
     * Get the configuration from <config key="x" value="y"/> elements.
     *
     * @param \SimpleXMLElement $xml the element maybe having config children
     *
     * @return array built from the config children of the element
     */
    protected function getConfig(\SimpleXMLElement $xml)
    {
        $config = array();
        foreach ($xml->config as $c) {
            $config[(string)$c['key']] = (string)$c['value'];
        }
        return $config;
    }

    /**
     * Load the xml information from the file system, if a matching file is
     * found in any of the configured directories.
     *
     * @param $className
     *
     * @return \SimpleXMLElement|null the definition or null if none found
     */
    protected  function getXmlDefinition($className)
    {
        $filename = $this->buildFileName($className);
        foreach ($this->directories as $dir) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $filename)) {
                return simplexml_load_file($dir . DIRECTORY_SEPARATOR . $filename);
            }
        }
        return null;
    }

    /**
     * Determine the filename from the class name
     *
     * @param string $className the fully namespaced class name
     *
     * @return string the filename for which to look
     */
    protected function buildFileName($className)
    {
        return str_replace('\\', '.', $className) . '.xml';
    }

    /**
     * Gets the names of all classes known to this driver.
     *
     * @return array The names of all classes known to this driver.
     */
    function getAllClassNames()
    {
        //TODO
    }
}
