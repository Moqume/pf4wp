<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Pointers;

/**
 * Static class providing a Feature Pointer (WP3.3+)
 *
 * The basic requirements is to override the variables $selector and $content, optionally 
 * $position and $capabilities. Then create the class during an onAdminScripts() callback.
 *
 * An onBeforeShow event is triggered when the pointer is about to be rendered, giving ample
 * opportunity to set the title and/or contents of the pointer
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Pointers
 */
class FeaturePointer
{
    const META_DISMISSED = 'dismissed_wp_pointers';
    
    /** The CSS Selector (ie., `#admin_toolbar`) where to show the pointer
     * @api
     */
    protected $selector = '';
    
    /** Array containing positioning of the pointer
     * @api
     */
    protected $position = array();
    
    /** Array containing capabilities required to show the pointer. Null if not required to check.
     * @api
     */
    protected $capabilities = null;
    
    /** 
     * String containing the contents to show inside the pointer 
     *
     * The string may contain a <h3> header, which will be used as a title.
     * The remaining content should be Javascript safe.
     * @api
     */
    protected $content  = '';
    
    /** Internal name/ID used by WordPress and CSS
     * @internal
     */
    private $name = '';
    
    /** Textdomain for translations
     * @since 1.0.5
     * @internal
     */
    private $textdomain = '';
    
    /** Default positions
     * @internal
     */
    private $default_position = array('edge' => 'top', 'align' => 'center');
    
	/**
	 * Enqueues the feature pointer
     *
     * This class should be created during the \Pf4wp\WordpressPlugin\onAdminScripts() callback
     *
     * @param string $textdomain Textdomain to use for translations
     * @api
	 */
	public function __construct($textdomain = '')
    {
        // textdomain
        $this->textdomain = $textdomain;
        
        // Internal name
        $this->name = strtolower(strtr(get_called_class(), '\\', '_')); // Namespace will provide enough collision prevention
        
		if (isset($this->capabilities)) {
			foreach ($this->capabilities as $capability) {
				if (!current_user_can($capability))
					return;
			}
		}
        
        // Return now if this pointer has been dismissed
        if ($this->isDismissed())
			return;

		// Bind pointer print function
		add_action('admin_print_footer_scripts', array($this, 'printPointer'));

		// Add pointers script and style to queue
		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('wp-pointer');
	}
    
    /**
     * Returns whether the pointer has been dismissed by the user
     *
     * @since 1.0.5
     * @return bool Returns `true` if the pointer has been dismissed, `false` otherwise
     * @api
     */
    public function isDismissed()
    {
        // Get dismissed pointers
        $dismissed = explode(',', (string)get_user_meta(get_current_user_id(), static::META_DISMISSED, true));

		if (in_array($this->name, $dismissed))
            return true; // We're in the list of dismissed pointers, return true
        
        return false;
    }
    
    /**
     * Sets the content of the pointer
     *
     * @since 1.0.5
     * @param string $content The content of the pointer
     * @param string $title The title of the pointer. If omitted, it can be provided in the $content as an HTML H3 tag if required.
     * @api
     */
    public function setContent($content, $title = '')
    {
        if (empty($title)) {
            $this->content = $content;
        } else {
            $this->content = sprintf('<h3>%s</h3>%s', $title, $content);
        }
    }
    
    /**
     * Resets the pointer, if previously dismissed
     *
     * @since 1.0.5
     * @api
     */
    public function reset()
    {
        $current_user_id = get_current_user_id();
        
        // Get dismissed pointers
        $dismissed = get_user_meta($current_user_id, static::META_DISMISSED, true);
        $dismissed_a = explode(',', (string)$dismissed);
        
        // Remove ourselves
        $new_dismissed_a = array_diff($dismissed_a, array($this->name));
        
        // Update user meta
        update_user_meta($current_user_id, static::META_DISMISSED, implode(',', $new_dismissed_a), $dismissed);
    }
    
    /**
     * Prints (echoes) the script responsible for placing the pointer
     *
     * Note: Public scope due to callback
     *
     * @internal
     */
    public function printPointer() {
        // Call onBeforeShow event
        $this->onBeforeShow($this->textdomain); // Since 1.0.5
        
        if (empty($this->content))
            return;
            
        $args = array(
			'content'  => $this->content,
			'position' => array_merge($this->default_position, $this->position),
		);

		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready(function($) {
			var options = <?php echo json_encode($args); ?>;

			if (!options)
				return;

			options = $.extend(options, {
				close: function() {
					$.post(ajaxurl, {
						pointer: '<?php echo $this->name; ?>',
						action: 'dismiss-wp-pointer'
					});
				}
			});

			$('<?php echo $this->selector; ?>').pointer(options).pointer('open');
		});
		//]]>
		</script>
		<?php
	}
    
    /**
     * Event called before the pointer is displayed (printed)
     *
     * @since 1.0.5
     * @param string $textdomain Textdomain as passed during __contruct(), used for translations
     * @api
     */
    public function onBeforeShow($textdomain) {}
}