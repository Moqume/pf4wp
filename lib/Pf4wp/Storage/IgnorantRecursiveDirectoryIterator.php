<?php

/*
 * This file is released in Public Domain
 *
 * See http://www.php.net/manual/en/class.recursivedirectoryiterator.php#101654
 */

namespace Pf4wp\Storage;

/**
 * Extends RecursiveDiretoryIterator. It ignores directories for which it does
 * not have permission to access, instead of throwing an UnexpectedValueException.
 *
 * @author antennen
 * @package Pf4wp
 * @subpackage Storage
 */
class IgnorantRecursiveDirectoryIterator extends \RecursiveDirectoryIterator {
    /** Override of getChildren in \RecursiveDirectoryIterator, to skip directories with insufficient rights to access
     * @internal
     */
    function getChildren() {
        try {
            return parent::getChildren();
        } catch(\UnexpectedValueException $e) {
            return new \RecursiveArrayIterator(array());
        }
    }
}