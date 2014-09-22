<?php
namespace WDE\HTTPAPIDebug;

class LogModel
{
    protected $db;

    public function __construct($wpdb)
    {
        $this->db = $wpdb;
    }

    public function save()
    {
        admin_notice('Save log entry here.');
        return true;
    }
}
