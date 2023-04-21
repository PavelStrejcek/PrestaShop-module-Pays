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
 * @copyright 2019 - 2023 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */
class PaysPsModelUtils
{

    /**
     * Comparing floats
     *
     * @param float $left
     * @param float $right
     * @param float $epsilon default is PHP_FLOAT_EPSILON for PHP 7.2+, or 0.00001
     * @return int Returns 0 if the two operands are equal, 1 if the left operand is larger than the right operand, -1 otherwise.
     */
    public static function floatcmp($left, $right, $epsilon = null)
    {
        $left = (float) $left;
        $right = (float) $right;

        if (defined('PHP_FLOAT_EPSILON')) {
            $epsilon = PHP_FLOAT_EPSILON;
        } else {
            $epsilon = 0.00001;
        }

        if (abs($left - $right) < $epsilon) {
            return 0;
        }
        if ($left > $right) {
            return 1;
        }
        return -1;
    }
}
