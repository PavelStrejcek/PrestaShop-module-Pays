<?php
/** Installed only by the opt-in integration suite in a disposable shop. */
if (!defined('_PS_VERSION_')) { exit; }
class Pays_Test_Probe extends Module
{
    public function __construct()
    {
        $this->name = 'pays_test_probe';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Pays regression tests';
        $this->displayName = 'Pays mail probe (tests only)';
        parent::__construct();
    }
    public function install() { return parent::install() && $this->registerHook('actionEmailSendBefore'); }
    public function hookActionEmailSendBefore($params)
    {
        if ($params['template'] === 'payment') {
            file_put_contents('/tmp/pays-test-mail-probe.jsonl', json_encode([
                'in_transaction' => (int) Db::getInstance()->getValue('SELECT @@in_transaction', false),
            ]) . "\n", FILE_APPEND | LOCK_EX);
        }
        return false; // The test shop must never send real messages.
    }
}
