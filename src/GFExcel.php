<?php

namespace GFExcel;

use GFAPI;
use GFCommon;
use GFExcel\Renderer\PHPExcelRenderer;
use GFFormsModel;

class GFExcel
{
    public static $name = 'Gravity Forms Results in Excel';
    public static $shortname = 'Results in Excel';
    public static $version = "1.3.2";
    public static $slug = "gf-entries-in-excel";

    const KEY_HASH = 'gfexcel_hash';
    const KEY_COUNT = 'gfexcel_download_count';

    public function __construct()
    {
        add_action("init", array($this, "add_permalink_rule"));
        add_action("request", array($this, "request"));
        add_filter("query_vars", array($this, "query_vars"));
    }

    /** Return the url for the form
     * @param $form
     * @return string
     */
    public static function url($form)
    {
        $blogurl = get_bloginfo("url");
        $permalink = "/index.php?gfexcel_action=%s&gfexcel_hash=%s";

        $action = self::$slug;
        $hash = self::getHash($form['id']);

        if (get_option('permalink_structure')) {
            $permalink = "/%s/%s";
        } else {
            $hash = urlencode($hash);
        }

        return $blogurl . sprintf($permalink, $action, $hash);
    }


    private static function getHash($form_id)
    {
        if (!GFAPI::form_id_exists($form_id)) {
            return false;
        }

        $meta = GFFormsModel::get_form_meta($form_id);

        if (!array_key_exists(static::KEY_HASH, $meta)) {
            $meta = static::setHash($form_id);
        }

        return $meta[static::KEY_HASH];
    }

    /**
     * Save new hash to the form
     * @return array metadata form
     */
    public static function setHash($form_id)
    {
        $meta = GFFormsModel::get_form_meta($form_id);

        $meta[static::KEY_HASH] = static::generateHash($form_id);
        GFFormsModel::update_form_meta($form_id, $meta);

        return $meta;
    }

    private static function generateHash($form_id)
    {
        $meta = GFFormsModel::get_form_meta($form_id);
        if (!array_key_exists(static::KEY_COUNT, $meta) ||
            array_key_exists(static::KEY_HASH, $meta)
        ) {
            //never downloaded before, or recreating hash
            // so make a pretty new one
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
        // Yay, we are someone from the first hour.. WHOOP, so we get to keep our old, maube insecure string
        return @GFCommon::encrypt($form_id);
    }

    public function add_permalink_rule()
    {
        add_rewrite_rule("^" . GFExcel::$slug . "/(.+)/?$",
            'index.php?gfexcel_action=' . GFExcel::$slug . '&gfexcel_hash=$matches[1]', 'top');

        $rules = get_option('rewrite_rules');
        if (!isset($rules["^" . GFExcel::$slug . "/(.+)/?$"])) {
            flush_rewrite_rules();
        }
    }

    public function request($query_vars)
    {
        if (!array_key_exists("gfexcel_action", $query_vars) ||
            !array_key_exists("gfexcel_hash", $query_vars) ||
            $query_vars['gfexcel_action'] !== self::$slug) {

            return $query_vars;
        }

        $form_id = $this->getFormIdByHash($query_vars['gfexcel_hash']);
        if (!$form_id) {
            return $query_vars;
        }

        $output = new GFExcelOutput($form_id, new PHPExcelRenderer());
        $this->updateCounter($form_id);

        return $output->render();
    }

    public function query_vars($vars)
    {
        $vars[] = "gfexcel_action";
        $vars[] = "gfexcel_hash";

        return $vars;
    }


    private function getFormIdByHash($hash)
    {
        global $wpdb;

        $table_name = GFFormsModel::get_meta_table_name();
        $wild = '%';
        $like = $wild . $wpdb->esc_like($hash) . $wild;
        if (!$form_row = $wpdb->get_row($wpdb->prepare("SELECT form_id FROM {$table_name} WHERE display_meta LIKE %s", $like), ARRAY_A)) {
            $result = @GFCommon::decrypt($hash);
            if (is_numeric($result)) {
                return $result;
            }
            return false; //bail
        }

        // possible match
        // Loading main form object (supports serialized strings as well as JSON strings)
        if (GFExcel::getHash($form_row['form_id']) === $hash) {
            //only now are we home save.
            return $form_row['form_id'];
        }

        return false;
    }

    /**
     * @param $form_id
     * @void
     */
    private function updateCounter($form_id)
    {
        $form_meta = GFFormsModel::get_form_meta($form_id);
        if (!array_key_exists(static::KEY_COUNT, $form_meta)) {
            $form_meta[static::KEY_COUNT] = 0;
        }
        $form_meta[static::KEY_COUNT] += 1;

        GFFormsModel::update_form_meta($form_id, $form_meta);
    }

}