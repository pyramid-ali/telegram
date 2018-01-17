<?php
/**
 * Created by PhpStorm.
 * User: pyramid
 * Date: 11/15/17
 * Time: 12:18 PM
 */

namespace Alish\Telegram\API;


class LabeledPrice extends BaseTelegram
{

    /**
     * @var string $label
     * Portion label
     */
    protected $label;

    /**
     * @var integer $amount
     * Price of the product in the smallest units of the currency (integer, not float/double). For example, for a price of US$ 1.45 pass amount = 145.
     * See the exp parameter in currencies.json, it shows the number of digits past the decimal point for each currency (2 for the majority of currencies).
     */
    protected $amount;

}