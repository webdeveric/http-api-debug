<?php
namespace WDE\Plugin;

class AdminPage
{
    protected $header;
    protected $slug;
    protected $page_hook;

    protected $parent;

    public function __construct( $header, $slug )
    {
        $this->header = $header;
        $this->slug   = $slug;

        \add_action('admin_menu', array( &$this, 'setupMenu' ) );
        \add_action('admin_menu', array( &$this, 'setupLoad' ) );
    }

    public function setupLoad()
    {
        \add_action('load-' . $this->page_hook, array( &$this, 'load' ) );
    }

    public function load()
    {
        if ( $this->onPage() && isset( $_SERVER['REQUEST_METHOD'] ) && ! empty( $_SERVER['REQUEST_METHOD'] ) ) {
            $method = 'handle' . ucfirst( strtolower( $_SERVER['REQUEST_METHOD'] ) );
            if ( method_exists($this, $method) )
                $this->$method();
        }
    }

    protected function onPage()
    {
        static $on_page;
        if ( ! isset($on_page) ) {
            $screen = \get_current_screen();
            $on_page = $screen->id === $this->page_hook;
        }
        return $on_page;
    }

    public function setParent(AdminPage $page)
    {
        $this->parent = $page;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setupMenu()
    {
        // Add calls to add_menu_page here.
    }

    protected function handlePost()
    {
    }

    protected function handleGet()
    {
    }

    protected function getPageContent()
    {
        return '';
    }

    public function render()
    {
    ?>
        <div class="wrap">
            <h2><?php echo $this->header; ?></h2>
            <?php echo $this->getPageContent(); ?>
        </div>
    <?php
    }

    public function __invoke()
    {
        $this->render();
    }
}
