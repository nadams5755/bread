<?php
class Bread
{
    private array $options;
    private Bread_Bmlt $bmlt1;
    private array $translate = [];
    function __construct($options)
    {
        if (! class_exists('WP_Filesystem_Direct')) {
            require_once '../../../wp-admin/includes/class-wp-filesystem-base.php';
            require_once '../../../wp-admin/includes/class-wp-filesystem-direct.php';
            require_once '../../../wp-includes/class-wp-error.php';
        }
        $this->load_translations();
        $this->options = $options;
        $this->bmlt1 = new Bread_Bmlt($this);
    }
    function bmlt()
    {
        return $this->bmlt1;
    }
    function getOptions()
    {
        return $this->options;
    }
    function load_translations()
    {
        $files = scandir("includes/lang");
        foreach ($files as $file) {
            if (strpos($file, "translate_") !== 0) {
                continue;
            }
            include 'includes/lang/' . $file;
            $key = substr($file, 10, -4);
            $this->translate[$key] = $bread_translate;
        }
    }
    public function getTranslateTable()
    {
        return $this->translate;
    }
    public static function arraySafeGet(array $arr, string $key = "0"): string
    {
        return $arr[$key] ?? '';
    }
    public function getday($day, $abbreviate = false, $language = 'en')
    {
        $key = "WEEKDAYS";
        if ($abbreviate) {
            $key = "WKDYS";
        }
        return $this->translate[$language][$key][$day];
    }
}
