<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Template;

/**
 * EngineInterface provides the interface for Template Engines
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Template
 */
interface EngineInterface
{
    /**
     * Renders a template
     *
     * @param string $name A template name
     * @param array $parameters An array of parameters to pass to the template
     * @return string The evaluated template as a string
     * @throws \InvalidArgumentException if the template does not exist
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function render($name, array $parameters = array());
    
    /**
     * Displays a template
     *
     * @param string $name A template name
     * @param array $parameters An array of parameters to pass to the template
     * @return string The evaluated template as a string
     * @throws \InvalidArgumentException if the template does not exist
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function display($name, array $parameters = array());
}