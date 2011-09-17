<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Template;

/**
 * TwigEngine provides access to the Twig Template Engine
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Template
 */
class TwigEngine implements EngineInterface
{
    protected $engine;
    
    /**
     * Constructor
     *
     * @param string $template_path Path containing the templates
     * @param mixed $options Options to pass to the template engine
     */
    public function __construct($template_path, $options)
    {
        if (class_exists('\Twig_Autoloader')) {
            \Twig_Autoloader::register();
           
            $this->engine = new \Twig_Environment(new \Twig_Loader_Filesystem($template_path), $options);
        }
    }
    
    /**
     * Loads the given template
     *
     * @param mixed $name A template name or an instance of Twig_Template
     * @return \Twig_TemplateInterface A Twig_TemplateInterface instance
     * @throws \InvalidArgumentException if the template does not exist
     */
    protected function load($name)
    {
        if ($name instanceof \Twig_Template)
            return $name;

        try {
            return $this->engine->loadTemplate($name);
        } catch (\Twig_Error_Loader $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }    
    
    /**
     * Renders a template
     *
     * @param string $name A template name
     * @param array $parameters An array of parameters to pass to the template
     * @return string The evaluated template as a string
     * @throws \InvalidArgumentException if the template does not exist
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function render($name, array $parameters = array())
    {
        if (!isset($this->engine))
            return '';
            
        return $this->load($name)->render($parameters);
    }
    
    /**
     * Displays a template
     *
     * @param string $name A template name
     * @param array $parameters An array of parameters to pass to the template
     * @throws \InvalidArgumentException if the template does not exist
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function display($name, array $parameters = array())
    {
        if (!isset($this->engine))
            return;
            
        $this->load($name)->display($parameters);
    }
}