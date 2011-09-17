<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Widgets;

/**
 * Adds a user-definable title to the base Widget class
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Widgets
 */
class TitledWidget extends Widget
{
    /** 
     * Filters the title prior to passing it to the parent's update()
     */    
    function update($new_instance, $old_instance)
    {
        $new_instance['title'] = strip_tags($new_instance['title']);

        return parent::update($new_instance, $old_instance);
    }
    
    /**
     * Renders the title before the rest of the widget is rendered
     */
    protected function onBeforeWidgetRender($args, $instance)
    {
        extract($args);
                
        $title = apply_filters('widget_title', $instance['title']);
        
        if ($title)
			echo $before_title . $title . $after_title;
            
        parent::onBeforeWidgetRender($args, $instance);
    }

    /**
     * Renders title option before rendering the rest of the form
     *
     * @param array $instance Current settings
     */
    protected function onBeforeFormRender($instance)
    {
        if ($instance) {
            $title = esc_attr($instance['title']);
        } else {
            $title = __('New title', $this->owner->getName());
        }

        printf('<p><label for="%s">%s</label>', $this->get_field_id('title'), __('Title:', $this->owner->getName()));
        printf('<input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>', $this->get_field_id('title'), $this->get_field_name('title'), $title);
        
        parent::onBeforeFormRender($instance);
    }
    
    /**
     * Event triggered when ready to render the widget's configuration page
     *
     * @param array $instance Current settings
     */
    public function onFormRender($instance) {}
}