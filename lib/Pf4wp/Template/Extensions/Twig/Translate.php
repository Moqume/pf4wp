<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Template\Extensions\Twig;

/**
 * Adds an extension to Twig, providing WordPress-based translations
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Template\Extensions\Twig
 */
class Translate extends \Twig_Extension
{
    /**
     * Reference to text domain used for translations (no need for a full back reference the owner)
     * @internal
     */
    private $textdomain;

    /**
     * Returns extension name to Twig
     */
    public function getName()
    {
        return 'translate';
    }

    /**
     * Sets the text domain used for translations
     *
     * @param string $textdomain The text domain to use for translations
     */
    public function setTextDomain($textdomain)
    {
        $this->textdomain = $textdomain;
    }

    /**
     * Returns the text domain used for transalations
     */
    public function getTextDomain()
    {
        return $this->textdomain;
    }

    /**
     * Returns the available filters to Twig
     */
    public function getFilters()
    {
        return array(
            'trans' => new \Twig_Filter_Method($this, 'transFilter'),
        );
    }

    /**
     * Returns available functions to Twig
     */
    public function getFunctions()
    {
        return array(
            '__' => new \Twig_Function_Method($this, 'transFilter'),
        );
    }

    /**
     * Translation Filter
     *
     * Translates a string through WordPress, using the specified textdomain
     *
     * @see getFilters()
     * @param string $string The string to filter
     * @return string Returns the translated string
     */
    public function transFilter($string)
    {
        return __($string, $this->textdomain);
    }
}
