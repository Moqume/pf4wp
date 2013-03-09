<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Widgets;

use Pf4wp\WordpressPlugin;

/**
 * Provides a standard sidebar/theme Widget
 *
 * Set the $title, and $description protected properties, which will be displayed 
 * in the `Appearance->Widget` screen. Optionally set the $width protected property
 * to adjust the minimum width of the Widget edit screen. The edit screen's contents
 * are rendered with `onFormRender()` and related methods. Changes in this form are
 * passed back to `onUpdate()`.
 *
 * On the public (visitor's) side, the Widget will render its contents with the
 * on `onWidgetRender()` method.
 *
 * The Widget is initialized by the `WordpressPlugin\registerWidget()` method during
 * the `WordpressPlugin\onWidgetRegister()` callback
 *
 * Also see wp-includes/widgets.php for \WP_Widget details
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Widgets
 * @see \Pf4wp\WordpressPlugin\registerWidget()
 * @see \Pf4wp\WordpressPlugin\onWidgetRegister()
 * @api
 */
class Widget extends \WP_Widget
{
    /** Back-reference to owner object
     * @internal
     */
    protected $owner;
    
    /**
     * Title to provide the Widget on the Dashboard
     *
     * Note: this does not equal the title displayed in the theme, see TitledWidget
     * @api
     */
    protected $title = '';
    
    /**
     * Description to display for the Widget on the Dashboard
     * @api
     */
    protected $description = '';
    
    /**
     * Width of the widget form, if wider than 250 pixels
     * @api
     */
    protected $width = 250;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $name = strtolower(preg_replace('/\W/', '-', get_class($this)));
        parent::__construct($name, $this->title, array('description' => $this->description), array('width' => $this->width));
    }
    
    /**
     * Performs initialization of the widget
     *
     * Called by WordpressPlugin\registerWidget()
     * 
     * @param WordpressPlugin $owner WordpressPlugin object of the widget owner
     * @internal
     */
    public function doInit(WordpressPlugin $owner)
    {
        $this->owner = $owner;
    }
    
    
    /*---------- WP_Widget Overrides ----------*/
    
    /**
     * Overrides the default widget() method
     *
     * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
     * @param array $instance The settings for the particular instance of the widget
     * @see onBeforeWidgetRender()
     * @see onWidgetRender()
     * @internal
     */
    function widget($args, $instance)
    {
        extract($args);
        echo $before_widget;
        
        $this->onBeforeWidgetRender($args, $instance);
        $this->onWidgetRender($args, $instance);

        echo $after_widget;
    }
    
    /**
     * Overrides the default form() method
     *
     * @param array $instance Current settings
     * @see onBeforeFormRender()
     * @see onFormRender()
     * @internal
     */
    function form($instance)
    {
        if (!is_admin())
            return;
        
        $this->onBeforeFormRender($instance);
        $this->onFormRender($instance);
    }
    
    /**
     * Overrides the default update() method
     *
     * @param array $new_instance New settings for this instance as input by the user via onRenderForm()
     * @param array $old_instance Old settings for this instance
     * @see onUpdate()
     * @internal
     */
    function update($new_instance, $old_instance)
    {
        $res = $this->onUpdate($new_instance, $old_instance);
        
        return (!isset($res)) ? $new_instance : $res;
    }
    
    
    /*---------- Internal events ----------*/
    
    /**
     * Internal event called before rendering Widget contents
     *
     * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
     * @param array $instance The settings for the particular instance of the widget
     * @see onWidgetRender()
     * @internal
     */
    protected function onBeforeWidgetRender($args, $instance) {}
    
    /**
     * Internal event called before rendered Form contents
     *
     * @param array $instance Current settings
     * @see onFormRender()
     * @internal
     */
    protected function onBeforeFormRender($instance) {}
    


    /*---------- Public events ----------*/

    /**
     * Event triggered when ready to render the widget contents
     *
     * @see widget()
     * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
     * @param array $instance The settings for the particular instance of the widget
     * @api
     */
    public function onWidgetRender($args, $instance) {}
    
    /**
     * Event triggered when ready to render the widget's configuration page
     *
     * @see form()
     * @param array $instance Current settings
     * @api
     */
    public function onFormRender($instance)
    {
        parent::form($instance);
    }
    
    /**
     * Event triggered when the settings for this widget are to be changed
     *
     * @see update()
     * @param array $new_instance New settings for this instance as input by the user via onRenderForm()
     * @param array $old_instance Old settings for this instance
     * @return array Settings to save or bool false to cancel saving
     * @api
     */
    public function onUpdate($new_instance, $old_instance) {}
}