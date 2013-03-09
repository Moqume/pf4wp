<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Database;

/**
 * Implements a very simple schema handler for databases
 *
 * All it does is contain schema CREATE, UPDATE and INSERT statements
 * and an `init()` function that either creates or upgrades the active
 * database accordingly.
 *
 * @author WordPress Team
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Database
 */
class SimpleSchema
{
    /**
     * The schema SQL CREATE, UPDATE and INSERT statement(s), where the following strings will
     * be replaced upon initialization:
     *
     * {#prefix#}           The actual prefix used by Wordpres for specific blogs or the main site
     * {#charset_collate#}  The character-set and collation used for tables
     */
    protected static $schema = '';
    
    /**
     * Initializes the database schema
     *
     * This is heavilly borrowed from WordPress' /wp-admin/include/upgrade.php
     *
     * @return bool Returns `true` if initialization was succesful, or `false` otherwise
     */
    public static function init()
    {
        global $wpdb;

        $schema = static::$schema;
        
        // Ensure the schema is an array
        if (!is_array($schema))
            $schema = explode(';', $schema);
        
        // Grab the default character set
        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
            
        // Grab the collation
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE {$wpdb->collate}";            

        $wp_tables  = array_merge($wpdb->tables, $wpdb->global_tables, $wpdb->ms_global_tables);
        $creates    = array(); // Create statements
        $inserts    = array(); // Insert statements

        foreach($schema as $query) {
            $query = trim(str_replace(array('{#prefix#}', '{#charset_collate#}'), array($wpdb->prefix, $charset_collate), $query));

            if (!empty($query)) {
                if (preg_match('#^CREATE TABLE (?:IF NOT EXISTS )?([^ ]*)#i', $query, $matches)) {
                    $creates[trim(strtolower($matches[1]), '`' )] = $query;
                } else if (stripos($query, 'CREATE DATABASE') == 0) {
                    array_unshift($creates, $query);
                } else if (stripos($query, 'INSERT INTO') == 0 || stripos($query, 'UPDATE') == 0) {
                    $inserts[] = $query;
                }
            }
        }
        
        if (($tables = $wpdb->get_col('SHOW TABLES;')) === false)
            return false;

        foreach ($tables as $table) {
            $table = strtolower($table);
            
            if (in_array($table, $wp_tables))
                continue; // Skip WP Tables
                
            // If the table already exists, check its structure to see what we need to alter instead
            if (array_key_exists($table, $creates)) {
                $columns = array();
                $indices = array();
                
                // Get all of the specified fields between the parens
                if (!preg_match("#\((.*)\)#ms", $creates[$table], $matches)) {
                    // This seems to be an invalid table, skip!
                    unset($creates[$table]);
                    continue;
                }
                
                // Differentiate between an index or column
                foreach (explode("\n", trim($matches[1])) as $field) {
                    $field      = rtrim(trim($field), ",");
                    $field_name = strtolower(trim(strstr($field, ' ', true), '`'));
                    
                    if (in_array($field_name, array('primary', 'index', 'fulltext', 'unique', 'key'))) {
                        $indicies[] = $field;
                    } else {
                        $columns[$field_name] = $field;
                    }
                }

                // Iterate existing table structure 
                foreach ((array)$wpdb->get_results("DESCRIBE `{$table}`;") as $table_field) {
                    if (array_key_exists(strtolower($table_field->Field), $columns)) {
                        $column = strtolower($table_field->Field);
                        
                        // Check the field type from the schema against the existing one
                        if (preg_match('#`?' . $table_field->Field . '`? ([^ ]*( unsigned)?)#i', $columns[$column], $matches)) {
                            $column_type = strtolower($matches[1]);
                        
                            if ($table_field->Type != $column_type)
                                $creates[] = "ALTER TABLE `{$table}` CHANGE COLUMN `{$table_field->Field}` " . $columns[$column];
                        }
                            
                        // Also check if there's default value specified in the schema
                        if (preg_match("# DEFAULT '(.*)'#i", $columns[$column], $matches)) {
                            $default_value = $matches[1];
                            
                            // If the existing default value does not match the default value specified in the table, alter it
                            if ($table_field->Default != $default_value)
                                $creates[] = "ALTER TABLE `{$table}` ALTER COLUMN `{$table_field->Field}` SET DEFAULT '{$default_value}'";
                        }

                        unset($columns[strtolower($table_field->Field)]);
                    } // Table field is not specified in schema
                }

                // Any fields not handled above will be treated as an addition to the existing table
                foreach ($columns as $column_details)
                    $creates[] = "ALTER TABLE `{$table}` ADD COLUMN {$column_details}";

                if ($table_indices = $wpdb->get_results("SHOW INDEX FROM `{$table}`;")) {
                    $index_fields = array();
                    
                    // Prepare a list of indices that we can chew on.
                    foreach ((array)$table_indices as $table_index) {
                        // Add the index to the index data array
                        $keyname = $table_index->Key_name;
                        $index_fields[$keyname]['columns'][] = array('field_name' => $table_index->Column_name, 'subpart' => $table_index->Sub_part);
                        $index_fields[$keyname]['unique']    = ($table_index->Non_unique == 0) ? true : false;
                    }

                    // Now chew it.
                    foreach ($index_fields as $index_name => $index_data) {
                        $index_string = '';
                        
                        if ($index_name == 'PRIMARY') {
                            $index_string .= 'PRIMARY ';
                        } else if($index_data['unique']) {
                            $index_string .= 'UNIQUE ';
                        }
                        
                        $index_string .= 'KEY ';
                        
                        if ($index_name != 'PRIMARY')
                            $index_string .= $index_name . ' ';

                        $index_columns = '';
                        
                        foreach ($index_data['columns'] as $column_data) {
                            if ($index_columns != '')
                                $index_columns .= ',';
                                
                            // Add the field to the column list string - yes, we should really use backticks
                            $index_columns .= '`'.$column_data['field_name'].'`';
                            
                            if ($column_data['subpart'] != '')
                                $index_columns .= '(' . $column_data['subpart'] . ')';
                        }
                        
                        // Add the column list to the index create string
                        $index_string .= '(' . $index_columns . ')';
                        
                        // If the index already exists, remove it from the table
                        if (!(($aindex = array_search($index_string, $indices)) === false))
                            unset($indices[$aindex]);
                    }
                }

                // Any index that's not yet in the table will be added
                foreach ((array)$indices as $index)
                    $creates[] = "ALTER TABLE `{$table}` ADD $index";

                // We've handle this table, so remove it from the $creates
                unset($creates[strtolower($table)]);
            }
        }

        $queries = array_merge($creates, $inserts);
        
        foreach ($queries as $query) {
			if ($wpdb->query($query) !== true)
                return false;
        }
        
        return true;
    }
}