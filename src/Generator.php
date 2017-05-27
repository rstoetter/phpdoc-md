<?php

// Rainer Stötter: added argument --sort-see, April 2017
// Rainer Stötter: added return values and descriptions, April 2017
// Rainer Stötter: removed file extension .md from link, April 2017
// Rainer Stötter: added argument --public-off, April 2017
// Rainer Stötter: added argument --private-off, April 2017
// Rainer Stötter: added argument --protected-off, April 2017
// Rainer Stötter: added argument --level, April 2017
// Rainer Stötter: added argument --sort-index, April 2017

namespace PHPDocMD;

use Twig_Environment;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;

/**
 * This class takes the output from 'parser', and generate the markdown
 * templates.
 *
 * @copyright Copyright (C) Evert Pot. All rights reserved.
 * @author    Evert Pot (https://evertpot.coom/)
 * @license   MIT
 */
class Generator
{
    /**
     * Output directory.
     *
     * @var string
     */
    protected $outputDir;

    /**
     * The list of classes and interfaces.
     *
     * @var array
     */
    protected $classDefinitions;

    /**
     * Directory containing the twig templates.
     *
     * @var string
     */
    protected $templateDir;

    /**
     * A simple template for generating links.
     *
     * @var string
     */
    protected $linkTemplate;

    /**
     * Filename for API Index.
     *
     * @var string
     */
    protected $apiIndexFile;



    /**
     * if true, then the api index and the component indices are sorted. Defaults to false.
     *
     * @var bool
     * @author    Rainer Stötter
     *
     */

    protected $m_do_sort = false;

    /**
     * If set, then we operate on class level and generate one md file for each class
     *
     * @var int
     * @see m_level
     * @author    Rainer Stötter
     *
     */

    const LEVEL_CLASS = 0;

    /**
     * If set, then we operate on component level and generate one md file for each class component (const, method ..)
     *
     * @var int
     * @see m_level
     * @author    Rainer Stötter
     *
     */

    const LEVEL_COMPONENT = 1;

    /**
     *  Defines the level we are operating. Defaults to self::LEVEL_CLASS.
     *
     * @var int
     * @see LEVEL_CLASS
     * @see LEVEL_COMPONENT
     * @author    Rainer Stötter
     *
     */


    protected $m_level = self::LEVEL_CLASS;



    /**
     * @param array  $classDefinitions
     * @param string $outputDir
     * @param string $templateDir
     * @param string $linkTemplate
     * @param string $apiIndexFile
     */
    function __construct(
	  array $classDefinitions,
	  $outputDir,
	  $templateDir,
	  $linkTemplate = '%c.md',
	  $apiIndexFile = 'ApiIndex.md',
	  $do_sort = false,
	  $level = 'class'
	  )
    {
        $this->classDefinitions = $classDefinitions;
        $this->outputDir = $outputDir;
        $this->templateDir = $templateDir;
        $this->linkTemplate = $linkTemplate;
        $this->apiIndexFile = $apiIndexFile;

        $this->m_do_sort = $do_sort;

        $level = strtolower( $level );
        $this->m_level = ( $level == 'component' ? self::LEVEL_COMPONENT : self::LEVEL_CLASS );

        if ( $this->m_do_sort ) echo "\n sorting the output";
        if ( $this->m_level == self::LEVEL_CLASS ) echo "\n working on class level";
        if ( $this->m_level == self::LEVEL_COMPONENT ) echo "\n working on component level";

    }


    /**
     *  Sorts an associative array by its subkeays
     *
     * @param array $ary the array to sort
     * @param string $subkey  the subkey which should be sorted
     * @param int $sort_order the way to sort $ary SORT_ASC or SORT_DESC
     * @see LEVEL_CLASS
     * @see LEVEL_COMPONENT
     * @author    Rainer Stötter
     *
     */


     private function SortBySubkey( & $ary, $subkey, $sort_order = SORT_ASC) {
	  foreach ( $ary as $el ) {
	      $keys[] = $el[ $subkey ];
	  }
	  array_multisort( $keys, $sort_order, $ary );
      }

    /**
     * Starts the generator.
     */
    function run()
    {
        $loader = new Twig_Loader_Filesystem($this->templateDir, [
            'cache' => false,
            'debug' => true,
        ]);

        $twig = new Twig_Environment($loader);

        $GLOBALS['PHPDocMD_classDefinitions'] = $this->classDefinitions;
        $GLOBALS['PHPDocMD_linkTemplate'] = $this->linkTemplate;

        $filter = new Twig_SimpleFilter('classLink', ['PHPDocMd\\Generator', 'classLink']);
        $twig->addFilter($filter);

        if ( $this->m_do_sort ) {
	    asort( $this->classDefinitions );
        }

        if ( $this->m_level == self::LEVEL_CLASS ) {



	    foreach ($this->classDefinitions as $className => $data) {
		$output = $twig->render('class.twig', $data);

		file_put_contents($this->outputDir . '/' . $data['fileName'], $output);

	    }

	} else {

	    // Rainer Stötter : we are splitting into component files


	    foreach ( $this->classDefinitions as $className => $data_class ) {

		$output = $twig->render('component-class.twig', $data_class);

		file_put_contents($this->outputDir . '/' . $data_class['fileName'], $output);

		foreach ( $data_class['methods'] as $method => $data_method ) {

		    $class_name = $data_class[ 'shortClass' ];
		    $method_name = $data_method['name'];
		    $namespace = $data_class['namespace'];

		    $file_name = $class_name . '::' . $method_name . '().md';

		    $data = $data_method;
		    $data['shortClass'] = $class_name;
		    $data['namespace'] = $namespace;

 		    $output = $twig->render( 'component-method.twig', $data );

 		    file_put_contents($this->outputDir . '/' . $file_name, $output);

		}

		foreach ( $data_class['constants'] as $constant => $data_constant ) {

		    $class_name = $data_class[ 'shortClass' ];
		    $constant_name = $data_constant['name'];
		    $namespace = $data_class['namespace'];

		    $file_name = $class_name . '::' . $constant_name . '.md';

		    $data = $data_constant;
		    $data['shortClass'] = $class_name;
		    $data['namespace'] = $namespace;

 		    $output = $twig->render( 'component-constant.twig', $data );

 		    file_put_contents($this->outputDir . '/' . $file_name, $output);

		}

		foreach ( $data_class['properties'] as $property => $data_property ) {

		    $class_name = $data_class[ 'shortClass' ];
		    $property_name = $data_property['name'];
		    $namespace = $data_class['namespace'];

		    $file_name = $class_name . '::' . $property_name . '.md';

		    $data = $data_property;
		    $data['shortClass'] = $class_name;
		    $data['namespace'] = $namespace;

 		    $output = $twig->render( 'component-property.twig', $data );

 		    file_put_contents($this->outputDir . '/' . $file_name, $output);

		}


	    }
	    // var_dump( array_keys( $this->classDefinitions[ 'rstoetter\libdatephp\cDate' ] ) );
	}

	$index = $this->createIndex();

	$index = $twig->render('index.twig',
	    [
		'index'            => $index,
		'classDefinitions' => $this->classDefinitions,
	    ]
	);

	file_put_contents($this->outputDir . '/' . $this->apiIndexFile, $index);

    }

    /**
     * Creates an index of classes and namespaces.
     *
     * I'm generating the actual markdown output here, which isn't great...But it will have to do.
     * If I don't want to make things too complicated.
     *
     * @return array
     */
    protected function createIndex()
    {
        $tree = [];

        foreach ($this->classDefinitions as $className => $classInfo) {
            $current = & $tree;

            foreach (explode('\\', $className) as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }

                $current = & $current[$part];
            }
        }

        /**
         * This will be a reference to the $treeOutput closure, so that it can be invoked
         * recursively. A string is used to trick static analysers into thinking this might be
         * callable.
         */
        $treeOutput = '';

        $treeOutput = function($item, $fullString = '', $depth = 0) use (&$treeOutput) {
            $output = '';

            foreach ($item as $name => $subItems) {
                $fullName = $name;

                if ($fullString) {
                    $fullName = $fullString . '\\' . $name;
                }

                $output .= str_repeat(' ', $depth * 4) . '* ' . Generator::classLink($fullName, $name) . "\n";
                $output .= $treeOutput($subItems, $fullName, $depth + 1);
            }

            return $output;
        };

        return $treeOutput($tree);
    }

    /**
     * This is a twig template function.
     *
     * This function allows us to easily link classes to their existing pages.
     *
     * Due to the unfortunate way twig works, this must be static, and we must use a global to
     * achieve our goal.
     *
     * @param string      $className
     * @param null|string $label
     *
     * @return string
     */
    static function classLink($className, $label = null)
    {
        $classDefinitions = $GLOBALS['PHPDocMD_classDefinitions'];
        $linkTemplate = $GLOBALS['PHPDocMD_linkTemplate'];

        $returnedClasses = [];

        foreach (explode('|', $className) as $oneClass) {
            $oneClass = trim($oneClass, '\\ ');

            if (!$label) {
                $label = $oneClass;
            }

            if (!isset($classDefinitions[$oneClass])) {
                $returnedClasses[] = $oneClass;
            } else {
                $link = str_replace('\\', '-', $oneClass);
                $link = strtr($linkTemplate, ['%c' => $link]);

                $returnedClasses[] = sprintf("[%s](%s)", $label, $link);
            }
        }

        // remove the trailing '.md' from the link as with this suffix the content of the link is interpreted as
        // raw and not formatted in Markdown ( Rainer Stötter )

        foreach( $returnedClasses as & $item ) {

	    $item = str_replace( '.md', '', $item );

        }



        return implode('|', $returnedClasses);
    }
}
