<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Pointers;

/**
 * Static class providing a Feature Pointer (WP3.3+)
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Pointers
 */
class FeaturePointer
{
    /** The CSS Selector (ie., `#admin_toolbar`) where to show the pointer */
    protected $selector     = '';
    
    /** Array containing positioning ot the pointer */
    protected $position     = array();
    
    /** Array containing capabilities required to show the pointer */
    protected $capabilities = null; // Array of capabilities required
    
    /** Array containing the contents to show inside the pointer */
    protected $content      = '';
    
    private $name             = '';
    private $default_position = array('edge' => 'top', 'align' => 'center');
    
	/**
	 * Enqueues the feature pointer
     *
     * This class should be created during the onAdminScripts() callback
	 */
	public function __construct()
    {
        $class       = get_called_class();
        $this->name  = strtolower(strtr($class, '\\', '_'));
        
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

			$('<?php echo $this->selector; ?>').pointer( options ).pointer('open');
		});
		//]]>
		</script>
		<?php
	}
}