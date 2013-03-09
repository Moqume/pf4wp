<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Dynamic;

/**
 * Dynamic class base
 *
 * @package Pf4wp
 * @subpackage Dynamic
 * @since 1.0.6
 */
abstract class DynamicBase implements DynamicInterface
{
    const
        DYN_NAME = 'Name',
        DYN_DESC = 'Description';

    /**
     * Return whether this dynamic class is active
     *
     * @api
     * @return bool
     */
    static public function isActive()
    {
        return false;
    }

    /**
     * Return details about the dynamic class
     *
     * @api
     * @return array Array containing a name, description and whether it is active
     */
    static public function info()
    {
        return array(
            'name'   => static::DYN_NAME,
            'desc'   => static::DYN_DESC,
            'active' => static::isActive(),
        );
    }
}
