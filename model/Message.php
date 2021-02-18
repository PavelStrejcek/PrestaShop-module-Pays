<?php

/**
 * Module Pays
 *
 * This source file is subject to the Open Software License v. 3.0 (OSL-3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to application@brainweb.cz so we can send you a copy..
 *
 * @author    Pavel Strejček <aplikace@brainweb.cz>
 * @copyright 2019 - 2021 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */
class PaysPsModelMessage extends ObjectModel {

    /** @var int */
    public $id_message;

    /** @var int foreign key to module entity Order */
    public $id_cart;

    /** @var string ENUM type STATUS or ERROR */
    public $type;

    /** @var string status or error code */
    public $code;

    /** @var string message */
    public $message;

    /** @var string param %s for sprintf replacement */
    public $param;

    /** @var string timestamp created at */
    public $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pays_ps_message',
        'primary' => 'id_message',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            /* Classic fields */
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'type' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'code' => array('type' => self::TYPE_STRING, 'size' => 100),
            'message' => array('type' => self::TYPE_STRING, 'required' => false, 'size' => 1000),
            'param' => array('type' => self::TYPE_STRING, 'required' => false, 'size' => 4000),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        /* Lang fields */
        ),
    );
    public static $messageList;

    public function __construct($id = null, $id_lang = null, $id_shop = null, $createNonExistent = false) {
        if ($id && $createNonExistent) {
            $sql = new DbQuery();
            $sql->select('count(*) cnt')
                    ->from(self::$definition['table'], 'a')
                    ->where('a.' . self::$definition['primary'] . ' = ' . (int) $id);
            $result = Db::getInstance()->executeS($sql);
            if (empty($result[0]['cnt'])) {
                $newEntity = array(
                    self::$definition['primary'] => (int) $id
                );

                Db::getInstance()->insert(self::$definition['table'], $newEntity, true);
            }
        }

        parent::__construct($id, $id_lang, $id_shop);
    }

    public static function setMessageList($list) {
        self::$messageList = $list;
    }

    public static function getOrderMessages($id_cart) {
        $sql = new DbQuery();
        $sql->select('*')
                ->from(self::$definition['table'], 'vm')
                ->where("vm.id_cart = " . (int) $id_cart)
                ->orderBy("vm.id_message");
        $messages = Db::getInstance()->executeS($sql);
        $result = array();
        foreach ($messages as $message) {
            $result[] = new self($message['id_message']);
        }

        return $result;
    }

    public static function addMessages($msgs, $id_cart) {
        $combined = self::getCombinedMessages($msgs);
        foreach ($msgs as $msgCode => $msg) {
            $message = new self;
            $message->id_cart = $id_cart;
            $message->type = array_key_exists($msgCode, self::$messageList['ERROR']) ? 'ERROR' : 'STATUS';
            $message->code = $msgCode;
            $message->message = $combined[$msgCode];
            $message->param = is_array($msg) ? $msg[0] : null;
            $message->save(true);
        }
    }

    public static function getTranslatedMessages($id_cart) {
        $messages = self::getOrderMessages($id_cart);
        $result = array();
        foreach ($messages as $message) {
            if (array_key_exists($message->code, self::$messageList[$message->type])) {
                $translation = self::$messageList[$message->type][$message->code];
                if (Tools::strlen($message->param) > 0) {
                    $translation = strpos($translation, '%s') == false ? $translation . ' (' . $message->param . ')' : sprintf($translation, $message->param);
                }

                $result[] = array(
                    'translation' => $translation,
                    'object' => $message
                );
            } else {
                $result[] = array(
                    'translation' => $message->message,
                    'object' => $message
                );
            }
        }
        return $result;
    }

    /**
     * Combine keys and translated text
     * @param array $messageInfo array values: true for translation, array(0=>string) for translation and sprintf, string as message untranslated
     * @return array
     */
    public static function getCombinedMessages($messageInfo) {
        $result = array();
        foreach ($messageInfo as $key => $message) {
            if (true === $message) {
                if (array_key_exists($key, self::$messageList['ERROR'])) {
                    $result[$key] = self::$messageList['ERROR'][$key];
                } elseif (array_key_exists($key, self::$messageList['STATUS'])) {
                    $result[$key] = self::$messageList['STATUS'][$key];
                } else {
                    $result[$key] = $key;
                }
            } elseif (is_array($message)) {
                if (array_key_exists($key, self::$messageList['ERROR'])) {
                    $result[$key] = strpos(self::$messageList['ERROR'][$key], '%s') == false ? self::$messageList['ERROR'][$key] . ' (' . $message[0] . ')' : sprintf(self::$messageList['ERROR'][$key], $message[0]);
                } elseif (array_key_exists($key, self::$messageList['STATUS'])) {
                    $result[$key] = strpos(self::$messageList['STATUS'][$key], '%s') == false ? self::$messageList['STATUS'][$key] . ' (' . $message[0] . ')' : sprintf(self::$messageList['STATUS'][$key], $message[0]);
                } else {
                    $result[$key] = $key . ' (' . $message[0] . ')';
                }
            } else {
                $result[$key] = $message;
            }
        }
        return $result;
    }

}
