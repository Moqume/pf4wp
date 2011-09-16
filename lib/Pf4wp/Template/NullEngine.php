<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Template;

/**
 * NullEngine provides a template engine that does nothing (eq. NULL)
 *
 * @author Mike Green <myatus@gmail.com>
 */
class NullEngine implements EngineInterface
{
    public function render($name, array $parameters = array()) {}

    public function display($name, array $parameters = array()) {}
}