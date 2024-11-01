<?php


namespace UNSProjectApp\Helpers;


class DataBase
{
    private $wpdb;
    /**
     * @var string|null
     */
    private $query;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @param string $sql
     *
     * @return $this
     */
    public function query($sql)
    {
        $this->query = $sql;

        return $this;
    }

    /**
     * Get results for a specific query
     * @return array|object|null
     */
    public function getResults()
    {
        return $this->wpdb->get_results($this->query, ARRAY_A);
    }

    public function getRow()
    {
        return $this->wpdb->get_row($this->query, ARRAY_A);
    }

    /**
     * Execute query
     */
    public function execute()
    {
        $this->wpdb->query($this->query);
    }

    /**
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->wpdb->base_prefix;
    }

    public function getInsertId()
    {
        return $this->wpdb->insert_id;
    }

    /**
     * @param string $text
     * @return string
     */
    public function sanitize($text)
    {
        return sanitize_text_field($text);
    }
}