<?php
namespace WDE\HTTPAPIDebug;
use WDE\Plugin\AdminPage;

class ListingPage extends AdminPage
{
    protected $table;
    public function __construct(LogTable $table)
    {
        $this->table = $table;
        parent::__construct( 'HTTP API Debug', 'http-api-debug' );
    }

    public function setupMenu()
    {
        $this->page_hook = \add_menu_page(
            'HTTP API Debug',
            'HTTP API Debug',
            'update_plugins',
            $this->slug,
            array( &$this, 'render' ),
            'dashicons-info'            
        );
    }

    protected function getPageContent()
    {
        ob_start();

        echo $this->table;

        return ob_get_clean();
    }

}
