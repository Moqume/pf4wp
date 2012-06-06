<?php die(); ?>
/**
 * @Name@ - Copyright @Year@ @Author@
 *
 * @Description@
 */

namespace @Namespace@;

// use Pf4wp\Menu\SubHeadMenu;  // Use this menu style to add the plugin to existing WordPress menus AND sub menus
use Pf4wp\Menu\StandardMenu;    // Use this menu style to give the plugin its own menu - see @onBuildMenu
// use Pf4wp\Menu\CombinedMenu; // Use this menu style to give the plugin its own menu AND sub menus

/**
 *
 */
class Main extends \Pf4wp\WordpressPlugin
{
    /**
     * If the public-side AJAX is enabled, set this variable is set to `true`
     */
    public $public_ajax = false;

    /**
     * The default options for the plugin, if any
     */
    protected $default_options = array();

    /**
     * Event called when the plugin is ready to register actions
     */
    public function onRegisterActions() {}

    /**
     * Event called when the plugin is activated
     *
     * Note: This event is called for each blog on a Multi-Site installation
     */
    public function onActivation() {}

    /**
     * Event called when the plugin is deactivated
     *
     * Note: This event is called for each blog on a Multi-Site installation
     */
    public function onDeactivation() {}

    /**
     * Event called when the plugin is uninstalled
     *
     * Note: This event is called for each blog on a Multi-Site installation
     */
    public function onUninstall() {}

    /**
     * Event called when the plugin is upgraded
     *
     * Note: This event is called for each blog on a Multi-Site installation
     *
     * If the previous version is 0.0, then this is a new installation.
     *
     * @param string $previous_version Previous version
     * @param string $current_version Current version
     */
    public function onUpgrade($previous_version, $current_version) {}

    /**
     * Event called when WordPress is fully loaded
     *
     * Note: This event is called on both the public (front-end) and admin (back-end) sides. Use
     * `is_admin()` if neccesary.
     */
    public function onWpLoaded() {}

    /**
     * Event called when Dashboard Widgets are to be registered
     */
    public function onDashboardWidgetRegister() {}

    /**
     * Event called when Sidebard (Theme) Widgets are to be registered
     */
    public function onWidgetRegister() {}

    /**
     * Event called to build a custom menu
     *
     * @see onMyMenu()
     * @return MenuEntry|string|bool Returns a MenuEntry, a string for a hook, or `false` if invalid
     */
    public function onBuildMenu()
    {
        $menu = new StandardMenu($this->getName());

        /* This will add a menu entry with the same name as the plugin. Click it will
         * call the event 'onMyMenu'.
         */
        $menu->addMenu($this->getDisplayName(), array($this, 'onMyMenu'));

        return $menu;
    }

    /**
     * Event called when the main menu entry is clicked
     *
     * @see onBuildMenu()
     */
    public function onMyMenu()
    {
        echo "Hello World!";
    }

    /**
     * Event called to retrieve the text to display after the plugin description, if any
     *
     * @return string String to display
     */
    public function onAfterPluginText()
    {
        return '';
    }

    /**
     * Event triggered when a public facing side of the plugin is ready for initialization
     *
     * This is a plugin-wide event. For page (screen) specific events, use onAdminLoad()
     *
     * @see onAdminLoad()
     */
    public function onAdminInit() {}

    /**
     * Event called when the admin/Dashboard starts to load
     *
     * @param object|null The current screen being displayed
     */
    public function onAdminLoad($current_screen) {}

    /**
     * Event called when the plugin is ready to load scripts for the admin/Dashboard
     */
    public function onAdminScripts() {}

    /**
     * Event called when the plugin is ready to load stylesheets for the admin/Dashboard
     */
    public function onAdminStyles() {}

    /**
     * Handle an Admin AJAX request
     *
     * This will process an AJAX call for this plugin. The 'ajaxResponse()' function
     * *MUST* be used to return any data, otherwise a 'No Event Response' error will be
     * returned to the AJAX caller.
     *
     * It will verify the NONCE prior to triggering this event. If it fails this
     * verification, an error will be returned to the AJAX caller
     *
     * Example:
     * <code>
     * function getAjax(ajaxFunc, ajaxData) {
     *     var resp = false;
     *
     *     $.ajax({
     *         type     : 'POST',
     *         dataType : 'json',
     *         url      : my_plugin_name_ajax.url,
     *         timeout  : 5000,
     *         async    : false,
     *         data     : { action: my_plugin_name_ajax.action, func: ajaxFunc, data: ajaxData, _ajax_nonce: my_plugin_name_ajax.nonce },
     *         success  : function(ajaxResp) {
     *             if (ajaxResp.nonce == my_plugin_name_ajax.nonceresponse &amp;&amp; ajaxResp.stat == 'ok')
     *                 resp = ajaxResp.data;
     *         }
     *     });
     *
     *     return resp;
     * }
     * </code>
     *
     * Wherein the plugin code, the onAjaxRequest() event is:
     * <code>
     * function onAjaxRequest( $func, $data ) {
     *    if ( $func == 'hello' ) {
     *        $this->ajaxResponse('Hello World!');
     *    } else {
     *        $this->ajaxResponse('I don\'t know what to do!', true);
     *    }
     * }
     * </code>
     *
     * @param string $action Action to take according to AJAX caller
     * @param mixed $data Data sent by the AJAX caller (WARNING: Data is unsanitzed)
     * @see ajaxResponse()
     */
    public function onAjaxRequest($action, $data) {}

    /**
     * Event triggered when a public facing side of the plugin is ready for initialization
     */
    public function onPublicInit() {}

    /**
     * Event triggered when queueing scripts on the public facing side
     */
    public function onPublicScripts() {}

    /**
     * Event triggered when queueing stylesheets on the public facing side
     */
    public function onPublicStyles() {}

    /**
     * Event triggered when the footer is about to be displayed on the public facing side
     */
    public function onPublicFooter() {}
}

