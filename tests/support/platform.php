<?php
// Only the platform surface needed by unit tests. Integration tests use real PS9.
function pSQL($string) { return addslashes((string) $string); }
class DbQuery {
    public function select($fields) { return $this; }
    public function from($table, $alias = null) { return $this; }
    public function where($restriction) { return $this; }
    public function orderBy($fields) { return $this; }
    public function limit($limit, $offset = 0) { return $this; }
}
class Db {
    public static $rows = [];
    public static function getInstance() { return new self(); }
    public function executeS($sql) { return self::$rows; }
}
class Configuration {
    public static $values = [];
    public static function get($name) { return self::$values[$name] ?? false; }
}
class TestSmarty {
    public $assigned = [];
    public function assign($values) { $this->assigned = array_merge($this->assigned, (array) $values); }
    public function fetch($template) { return 'rendered:' . $template; }
}
class PaymentModule {
    public $name, $tab, $version, $author, $need_instance, $ps_versions_compliancy;
    public $bootstrap, $controllers, $displayName, $description, $confirmUninstall, $context;
    public $active = true;
    public function __construct() {
        $this->context = (object) [
            'cookie' => new TestCookie(),
            'cart' => (object) ['id' => 1],
            'smarty' => new TestSmarty(),
        ];
    }
    public function l($message, $context = null) { return $message; }
}
class TestCookie {
    private $values = [];
    public function __get($key) { return $this->values[$key] ?? null; }
    public function __set($key, $value) { $this->values[$key] = $value; }
}
class ObjectModel {
    const TYPE_INT = 1, TYPE_STRING = 2, TYPE_DATE = 5;
    public $id;
    public function __construct($id = null, $id_lang = null, $id_shop = null) { $this->id = $id; }
}
class OrderState {}
class Module {
    public static function getPaymentModules() { return [['name' => 'pays_ps']]; }
}
class ModuleFrontController { public $module, $context; }
class Customer {
    public $id = null, $firstname = 'Jan', $lastname = 'Novak', $email = 'jan@example.test';
    public function __construct($id = null) {}
}
class Currency {
    public $id, $iso_code = 'CZK';
    public function __construct($id = null) { $this->id = $id; }
}
class Language {
    public static $isoById = [];
    public $id, $iso_code = null;
    public function __construct($id = null) { $this->id = $id; $this->iso_code = self::$isoById[$id] ?? null; }
}
class Hook {
    public static function exec($hook_name, $hook_args = [], $id_module = null, $array_return = false) { return $array_return ? [] : ''; }
}
class Validate { public static function isLoadedObject($object) { return is_object($object) && !empty($object->id); } }
class TestRedirect extends RuntimeException {}
class Tools {
    public static $request = [];
    public static function getValue($key, $default = false) { return self::$request[$key] ?? $default; }
    public static function redirect($url) { throw new TestRedirect($url); }
}
