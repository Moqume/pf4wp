<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Widgets;

/**
 * Adds a user-definable title to the base Widget class
 *
 * A simple extension to the Widget class, which gives the user the ability to change the 
 * title, as is the case with most WordPress widgets. This will still allow other form
 * elements inside the Widget form.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Widgets
 * @api
 */
class TitledWidget extends Widget
{
    /** 
     * Filters the title prior to passing it to the parent's update()
     *
     * @param array $new_instance New settings for this instance as input by the user via onRenderForm()
     * @param array $old_instance Old settings for this instance
     * @internal
     */    
    function update($new_instance, $old_instance)
    {
        $new_instance['title'] = strip_tags($new_instance['title']);

        return parent::update($new_instance, $old_instance);
    }
    
    /**
     * Renders the title before the rest of the widget is rendered
     *
     * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
     * @param array $instance The settings for the particular instance of the widget
     * @internal
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
     * @internal
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
     * @api
     */
    public function onFormRender($instance) {}
}