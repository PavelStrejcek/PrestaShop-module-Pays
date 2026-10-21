<?php
// Only the platform surface needed by unit tests. Integration tests use real PS9.
class Configuration {
    public static $values = [];
    public static function get($name) { return self::$values[$name] ?? false; }
}
class PaymentModule {
    public $name, $tab, $version, $author, $need_instance, $ps_versions_compliancy;
    public $bootstrap, $controllers, $displayName, $description, $confirmUninstall, $context;
    public $active = true;
    public function __construct() {
        $this->context = (object) ['cookie' => new TestCookie(), 'cart' => (object) ['id' => 1]];
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
}
class OrderState {}
class Module {
    public static function getPaymentModules() { return [['name' => 'pays_ps']]; }
}
class ModuleFrontController { public $module, $context; }
class Customer { public $id = null; public function __construct($id) {} }
class Validate { public static function isLoadedObject($object) { return is_object($object) && !empty($object->id); } }
class TestRedirect extends RuntimeException {}
class Tools {
    public static $request = [];
    public static function getValue($key, $default = false) { return self::$request[$key] ?? $default; }
    public static function redirect($url) { throw new TestRedirect($url); }
}
