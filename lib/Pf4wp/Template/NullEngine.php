<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Template;

/**
 * NullEngine provides a template engine that does nothing (hence `null`)
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Template
 */
class NullEngine implements EngineInterface
{
    public function render($name, array $parameters = array()) {}

    public function display($name, array $parameters = array()) {}
}