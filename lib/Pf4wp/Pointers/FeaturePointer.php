<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
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
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Pointers
 */
class FeaturePointer
{
    /** The CSS Selector (ie., `#admin_toolbar`) where to show the pointer
     * @api
     */
    protected $selector = '';
    
    /** Array containing positioning ot the pointer
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
    
    /** Default positions
     * @internal
     */
    private $default_position = array('edge' => 'top', 'align' => 'center');
    
	/**
	 * Enqueues the feature pointer
     *
     * This class should be created during the \Pf4wp\WordpressPlugin\onAdminScripts() callback
     *
     * @api
	 */
	public function __construct()
    {
        // Internal name
        $this->name = strtolower(strtr(get_called_class(), '\\', '_')); // Namespace will provide enough collision prevention
        
		if (isset($this->capabilities)) {
			foreach ($this->capabilities as $capability) {
				if (!current_user_can($capability))
					return;
			}
		}

		// Get dismissed pointers
		$dismissed = explode(',', (string)get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));

		// Pointer has been dismissed
		if (in_array($this->name, $dismissed))
			return;

		// Bind pointer print function
		add_action('admin_print_footer_scripts', array($this, 'printPointer'));

		// Add pointers script and style to queue
		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('wp-pointer');
	}
    
    /**
     * Prints (echoes) the script responsible for placing the pointer
     *
     * Note: Public scope due to callback
     *
     * @internal
     */
    public function printPointer() {
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
}