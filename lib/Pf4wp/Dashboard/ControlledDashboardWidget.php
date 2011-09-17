<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Dashboard;

/**
 * ControlledDashboardWidget adds Controls to a standard Dashboard Widget
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Dashboard
 */
class ControlledDashboardWidget extends DashboardWidget
{
    /**
     * Registers the dashboard widget
     */
    public function register()
    {
        if ($this->registered)
            return;
        
        // Verify permissions
        $control_callback = null;
        if (current_user_can('edit_dashboard'))
            $control_callback = array($this, '_onControlCallback');
        
        wp_add_dashboard_widget($this->name, $this->title, array($this, 'onCallback'), $control_callback);
        
        $this->registered = true;
    }
    
    /**
     * Internal event called when plugin control content needs to be rendered and/or processed
     */
    public function _onControlCallback()
    {
        // Double check permission (already checked at register(), but never hurts to double check)
        if (!current_user_can('edit_dashboard'))
            return;
        
        $data = null;
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['widget_id']) && $_POST['widget_id'] == $this->name) {
            // Verify nonce
            if (isset($_POST['_nonce']) && wp_verify_nonce($_POST['_nonce'], $this->name.'-control')) {
                $data = $_POST;
                
                // Scrub items that aren't needed:
                unset($data['_nonce']);
                unset($data['_wp_http_referer']);
                unset($data['widget_id']);
                unset($data['submit']);
            }
            // else failed nonce verification
        }
        
        wp_nonce_field($this->name.'-control', '_nonce');
            
        $this->onControlCallback($data);        
    }
    
    /**
     * Event called when the plugin control contents need to be rendered and/or processed
     *
     * @param mixed|null $data Raw (unsanitized!) data to be processed, or null if no processing requested
     */
    public function onControlCallback($data) {}    
}