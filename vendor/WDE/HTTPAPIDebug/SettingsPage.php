<?php
namespace WDE\HTTPAPIDebug;
use WDE\Plugin\AdminPage;

class SettingsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct( 'HTTP API Debug Settings', 'http-api-debug-settings' );
    }

    public function setupMenu()
    {
        $this->page_hook = \add_submenu_page(
            $this->parent->getSlug(),
            'Options',
            'Options',
            'update_plugins',
            $this->slug,
            array( &$this, 'render' )
        );
    }

    protected function getPageContent()
    {
        ob_start();

        echo '<form method="post" action="options.php">';
        // settings_fields($this->options_group);
        // do_settings_sections($this->options_page_slug);
        submit_button('Save Options');
        echo '</form>';

        return ob_get_clean();
    }

}
