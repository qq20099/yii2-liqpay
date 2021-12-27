<?php
/**
 * Liqpay Payment Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category        LiqPay
 * @package         delagics/liqpay
 * @version         3.0
 * @author          Liqpay
 * @copyright       Copyright (c) 2014 Liqpay
 * @license         http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * EXTENSION INFORMATION
 *
 * LIQPAY API       https://www.liqpay.ua/documentation/ru
 *
 */

namespace qq20099\liqpay;

use yii\widgets\InputWidget;
use yii\helpers\Html;
use Yii;

/**
 * Payment method liqpay process
 *
 * @author      Liqpay <support@liqpay.com>
 */
class LiqPay extends InputWidget
{
    const SUPPORT_VERSION = 3;
    const DEFAULT_LANG = 'en';

    private $_api_url = 'https://www.liqpay.ua/api/';
    private $_checkout_url = 'https://www.liqpay.ua/api/3/checkout';
    protected $_supportedCurrencies = ['EUR','UAH','USD','RUB'];
    protected $_supportedLangs = ['en', 'ru'];
    private $_public_key;
    private $_private_key;

    /**
     * Constructor.
     *
     * @param string $public_key
     * @param string $private_key
     *
     * @throws InvalidArgumentException
     */
    public function __construct($public_key, $private_key)
    {
        if (empty($public_key))
            throw new \InvalidArgumentException('LiqPay: Public key is empty');

        if (empty($private_key))
            throw new \InvalidArgumentException('LiqPay: Private key is empty');

        $this->_public_key = $public_key;
        $this->_private_key = $private_key;
    }

    /**
     * Call API
     *
     * @param string $url
     * @param array $params
     *
     * @return string
     */
    public function api($path, $params = array())
    {
        if(!isset($params['version']))
            throw new \InvalidArgumentException('LiqPay: Version is null');

        $url         = $this->_api_url . $path;
        $public_key  = $this->_public_key;
        $private_key = $this->_private_key;
        $data        = base64_encode(json_encode(array_merge(compact('public_key'), $params)));
        $signature   = base64_encode(sha1($private_key.$data.$private_key, 1));
        $postfields  = http_build_query(array(
           'data'  => $data,
           'signature' => $signature
        ));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $server_output = curl_exec($ch);
        curl_close($ch);
        return json_decode($server_output);
    }

    /**
     * cnb_form
     *
     * @param array $params
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function cnb_form($params, $formId = '', $autosubmit = false, $liqpayBtnNum = 11)
    {

        if (isset($params['language']) && in_array($params['language'], $this->_supportedLangs))
            $lang = $params['language'];
        elseif(isset($params['language']) && !in_array($params['language'], $this->_supportedLangs))
            throw new \InvalidArgumentException('LiqPay: Language is not supported');
        else
            $lang = $params['language'] = self::DEFAULT_LANG;

        $params = $this->cnb_params($params);

        $form =
            Html::beginForm($this->_checkout_url, 'post', ['accept-charset' => 'utf-8', 'id'=>$formId]).
            Html::hiddenInput('data', base64_encode(json_encode($params))).
            Html::hiddenInput('signature', $this->cnb_signature($params)).
            (
                $autosubmit ?
                $this->autoSubmit($formId) :
                Html::input('image', 'btn_text', null, ['src'=>'//static.liqpay.com/buttons/p'.$liqpayBtnNum.$lang.'.radius.png'])
            ).
            Html::endForm();
        return $form;
    }

    public function autoSubmit($formId, $timeout = 1000)
    {
        return Html::script("setTimeout(function(){
            document.getElementById(\"$formId\").submit();}, $timeout);");
    }

    /**
     * cnb_signature
     *
     * @param array $params
     *
     * @return string
     */
    public function cnb_signature($params)
    {
        $params = $this->cnb_params($params);
        $private_key = $this->_private_key;

        $json = base64_encode( json_encode($params) );
        $signature = $this->str_to_sign($private_key . $json . $private_key);

        return $signature;
    }

    /**
     * cnb_params
     *
     * @param array $params
     *
     * @return array $params
     */
    private function cnb_params($params)
    {
        $params['public_key'] = $this->_public_key;

        if (!isset($params['version']))
            throw new \InvalidArgumentException('LiqPay: Version is null');
        if (!isset($params['amount']))
            throw new \InvalidArgumentException('LiqPay: Amount is null');
        if (!isset($params['currency']))
           throw new \InvalidArgumentException('LiqPay: Currency is null');
        if (!in_array($params['currency'], $this->_supportedCurrencies))
            throw new \InvalidArgumentException('LiqPay: Currency is not supported');
        if (!isset($params['description']))
            throw new \InvalidArgumentException('LiqPay: Description is null');

        return $params;
    }

    /**
     * str_to_sign
     *
     * @param string $str
     *
     * @return string
     */
    public function str_to_sign($str)
    {
        $signature = base64_encode(sha1($str,1));

        return $signature;
    }

}
