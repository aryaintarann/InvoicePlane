<?php

function &get_instance()
{
    return $GLOBALS['test_application'];
}

function log_message($level, $message)
{
    $GLOBALS['test_logs'][] = $message;
}

function is_php($version)
{
    return version_compare(PHP_VERSION, $version, '>=');
}

function config_item($key)
{
    return ['legacy_calculation' => true, 'disable_read_only' => true, 'charset' => 'UTF-8'][$key] ?? null;
}

function show_error($message, $code = 500)
{
    throw new RuntimeException(is_array($message) ? implode(' ', $message) : $message);
}

function env($key, $default = null)
{
    return $default;
}

define('IP_DEBUG', false);

function site_url($path)
{
    return 'https://invoice.example/' . $path;
}

function trans($key)
{
    return $key;
}

require_once BASEPATH . 'core/Model.php';
class Base_Controller extends CI_Model {}
require_once BASEPATH . 'database/DB.php';
require_once APPPATH . 'helpers/sql_helper.php';
require_once APPPATH . 'helpers/file_security_helper.php';
require_once APPPATH . 'helpers/date_helper.php';
require_once APPPATH . 'helpers/settings_helper.php';
require_once APPPATH . 'helpers/echo_helper.php';
require_once APPPATH . 'helpers/number_helper.php';
require_once APPPATH . 'core/MY_Model.php';
require_once APPPATH . 'core/Form_Validation_Model.php';
require_once APPPATH . 'core/Response_Model.php';
require_once APPPATH . 'core/Validator.php';
require_once APPPATH . 'core/Password_Encryption_Trait.php';

#[AllowDynamicProperties]
class IntegrationApplication
{
    public $db;

    public $load;

    public $session;

    public $input;

    public function __construct($db)
    {
        $this->db     = $db;
        $this->load   = new IntegrationLoader($this);
        $this->config = new class () {
            public function item($key)
            {
                return config_item($key);
            }

            public function set_item($key, $value) {}
        };
        $this->session = new class () {
            public function set_flashdata($key, $value) {}

            public function userdata($key)
            {
                return $key === 'ip_lang' ? 'english' : null;
            }
        };
        $this->input = new class () {
            public function ip_address()
            {
                return '127.0.0.1';
            }
        };
        $this->mdl_settings = new class () {
            public function setting($key, $default = '', $escape = false)
            {
                return $GLOBALS['test_settings'][$key] ?? $default;
            }

            public function load_settings() {}

            public function save($key, $value)
            {
                $GLOBALS['test_settings'][$key] = $value;
            }
        };
    }
}

class IntegrationLoader
{
    public function __construct(private IntegrationApplication $app) {}

    public function library($name): void
    {
        $this->app->{$name} = new stdClass();
    }

    public function helper($names): void
    {
        foreach ((array) $names as $name) {
            $path = APPPATH . 'helpers/' . $name . '_helper.php';
            require_once is_file($path) ? $path : BASEPATH . 'helpers/' . $name . '_helper.php';
        }
    }

    public function model($paths): void
    {
        foreach ((array) $paths as $path) {
            $name = basename($path);
            if (isset($this->app->{$name})) {
                continue;
            }
            require_once APPPATH . 'modules/' . dirname($path) . '/models/' . ucfirst($name) . '.php';
            $class              = ucfirst($name);
            $this->app->{$name} = new $class();
        }
    }
}

function integration_database()
{
    return DB([
        'dsn'      => '', 'hostname' => getenv('IP_TEST_DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('IP_TEST_DB_PORT') ?: 33317,
        'username' => getenv('IP_TEST_DB_USER') ?: 'root',
        'password' => getenv('IP_TEST_DB_PASSWORD') ?: 'invoiceplane_test',
        'database' => 'invoiceplane_whatsapp_test', 'dbdriver' => 'mysqli',
        'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false,
        'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '',
        'encrypt'  => false, 'compress' => false, 'stricton' => false, 'failover' => [], 'save_queries' => true,
    ], true);
}
