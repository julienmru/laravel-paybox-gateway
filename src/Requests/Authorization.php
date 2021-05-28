<?php

namespace CariAgency\PayboxGateway\Requests;

use Carbon\Carbon;
use CariAgency\PayboxGateway\Language;
use CariAgency\PayboxGateway\Services\Amount;
use CariAgency\PayboxGateway\Services\HmacHashGenerator;
use CariAgency\PayboxGateway\Services\ServerSelector;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Routing\UrlGenerator;
use Spatie\ArrayToXml\ArrayToXml;
use CodeInc\StripAccents\StripAccents;

abstract class Authorization extends Request
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'paybox';

    /**
     * Interface language.
     *
     * @var string
     */
    protected $language = Language::FRENCH;

    /**
     * @var array|null
     */
    protected $customer = [];

    /**
     * @var array|null
     */
    protected $shoppingCart = [];

    /**
     * @var array|null
     */
    protected $returnFields = null;

    /**
     * @var string|null
     */
    protected $customerPaymentAcceptedUrl = null;

    /**
     * @var string|null
     */
    protected $customerPaymentRefusedUrl = null;

    /**
     * @var string|null
     */
    protected $customerPaymentAbortedUrl = null;

    /**
     * @var string|null
     */
    protected $customerPaymentWaitingUrl = null;

    /**
     * @var string|null
     */
    protected $transactionVerifyUrl = null;

    /**
     * @var HmacHashGenerator
     */
    protected $hmacHashGenerator;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var UrlGenerator
     */
    protected $urlGenerator;

    /**
     * @var ViewFactory
     */
    protected $view;

    /**
     * Authorization constructor.
     *
     * @param ServerSelector $serverSelector
     * @param Config $config
     * @param HmacHashGenerator $hmacHashGenerator
     * @param UrlGenerator $urlGenerator
     * @param ViewFactory $view
     * @param Amount $amountService
     */
    public function __construct(
        ServerSelector $serverSelector,
        Config $config,
        HmacHashGenerator $hmacHashGenerator,
        UrlGenerator $urlGenerator,
        ViewFactory $view,
        Amount $amountService
    ) {
        parent::__construct($serverSelector, $config, $amountService);
        $this->hmacHashGenerator = $hmacHashGenerator;
        $this->urlGenerator = $urlGenerator;
        $this->view = $view;
    }

    /**
     * Get parameters that are required to make request.
     *
     * @return array
     */
    public function getParameters()
    {
        $params = $this->getBasicParameters();

        $params['PBX_HMAC'] = $this->hmacHashGenerator->get($params);

        return $params;
    }

    /**
     * Get basic parameters (all parameters except HMAC hash).
     *
     * @return array
     */
    protected function getBasicParameters()
    {
        return [
            'PBX_SITE' => $this->config->get('paybox.site'),
            'PBX_RANG' => $this->config->get('paybox.rank'),
            'PBX_IDENTIFIANT' => $this->config->get('paybox.id'),
            'PBX_TOTAL' => $this->amount,
            'PBX_DEVISE' => $this->currencyCode,
            'PBX_LANGUE' => $this->language,
            'PBX_CMD' => $this->paymentNumber,
            'PBX_HASH' => 'SHA512',
            'PBX_PORTEUR' => $this->customer['email'],
            'PBX_RETOUR' => $this->getFormattedReturnFields(),
            'PBX_TIME' => $this->getFormattedDate($this->time ?: Carbon::now()),
            'PBX_EFFECTUE' => $this->getCustomerUrl('customerPaymentAcceptedUrl', 'accepted'),
            'PBX_REFUSE' => $this->getCustomerUrl('customerPaymentRefusedUrl', 'refused'),
            'PBX_ANNULE' => $this->getCustomerUrl('customerPaymentAbortedUrl', 'aborted'),
            'PBX_ATTENTE' => $this->getCustomerUrl('customerPaymentWaitingUrl', 'waiting'),
            'PBX_REPONDRE_A' => $this->getTransactionUrl(),
            'PBX_PORTEUR' => $this->customer['email'],
            'PBX_SHOPPING_CART' => str_replace("\n", "", ArrayToXml::convert($this->shoppingCart, 'shoppingcart')),
            'PBX_BILLING' => str_replace("\n", "", ArrayToXml::convert(['Address' => [
                'FirstName' => $this->formatTextValue($this->customer['firstname'], 'ANP', 30),
                'LastName' => $this->formatTextValue($this->customer['lastname'], 'ANP', 30),
                'Address1' => $this->formatTextValue($this->customer['address'], 'ANS', 50),
                'ZipCode' => $this->formatTextValue($this->customer['postcode'], 'ANS', 16),
                'City' => $this->formatTextValue($this->customer['city'], 'ANS', 50),
                'Country' => intval($this->customer['country']),
            ]], 'Billing')),
        ];
    }

    protected function formatTextValue($value, $type, $maxLength = null)
    {
        /*
        AN : Alphanumerical without special characters
        ANP : Alphanumerical with spaces and special characters
        ANS : Alphanumerical with special characters
        N : Numerical only
        A : Alphabetic only
        */

        switch ($type) {
            default:
            case 'AN':
                $value = StripAccents::strip($value);
                break;
            case 'ANP':
                $value = StripAccents::strip($value);
                $value = preg_replace('/[^-. a-zA-Z0-9]/', '', $value);
                break;
            case 'ANS':
                break;
            case 'N':
                $value = preg_replace('/[^0-9.]/', '', $value);
                break;
            case 'A':
                $value = StripAccents::strip($value);
                $value = preg_replace('/[^A-Za-z]/', '', $value);
                break;
        }
        // Remove carriage return characters
        $value = trim(preg_replace("/\r|\n/", '', $value));

        // Cut the string when needed
        if (!empty($maxLength) && is_numeric($maxLength) && $maxLength > 0) {
            if (function_exists('mb_strlen')) {
                if (mb_strlen($value) > $maxLength) {
                    $value = mb_substr($value, 0, $maxLength);
                }
            } elseif (strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength);
            }
        }

        return $value;
    }

    /**
     * Set interface language.
     *
     * @param string $language
     *
     * @return $this
     */
    public function setLanguage($language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Set customer data.
     *
     * @param array $customer
     *
     * @return $this
     */
    public function setShoppingCart($shoppingCart)
    {
        $this->shoppingCart = $shoppingCart;

        return $this;
    }

    /**
     * Set customer data.
     *
     * @param array $customer
     *
     * @return $this
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Set customer e-mail.
     *
     * @param string $email
     *
     * @return $this
     */
    public function setCustomerEmail($email)
    {
        $this->customer['email'] = $email;

        return $this;
    }

    /**
     * Get formatted date in format required by Paybox.
     *
     * @param Carbon $date
     *
     * @return string
     */
    protected function getFormattedDate(Carbon $date)
    {
        return $date->format('c');
    }

    /**
     * Set return fields that will be when Paybox redirects back to website.
     *
     * @param array $returnFields
     *
     * @return $this
     */
    public function setReturnFields(array $returnFields)
    {
        $this->returnFields = $returnFields;

        return $this;
    }

    /**
     * Get return fields formatted in valid way.
     *
     * @return string
     */
    protected function getFormattedReturnFields()
    {
        $returnFields = (array) ($this->returnFields ?: $this->config->get('paybox.return_fields'));

        return collect($returnFields)->map(function ($value, $key) {
            return $key . ':' . $value;
        })->implode(';');
    }

    /**
     * Set back url for customer when payment is accepted.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setCustomerPaymentAcceptedUrl($url)
    {
        $this->customerPaymentAcceptedUrl = $url;

        return $this;
    }

    /**
     * Set back url for customer when payment is refused.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setCustomerPaymentRefusedUrl($url)
    {
        $this->customerPaymentRefusedUrl = $url;

        return $this;
    }

    /**
     * Set back url for customer when payment is aborted.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setCustomerPaymentAbortedUrl($url)
    {
        $this->customerPaymentAbortedUrl = $url;

        return $this;
    }

    /**
     * Set back url for customer when payment is waiting.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setCustomerPaymentWaitingUrl($url)
    {
        $this->customerPaymentWaitingUrl = $url;

        return $this;
    }

    /**
     * Set url for transaction verification.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setTransactionVerifyUrl($url)
    {
        $this->transactionVerifyUrl = $url;

        return $this;
    }

    /**
     * Get customer url.
     *
     * @param string $variableName
     * @param string $configKey
     *
     * @return string
     */
    protected function getCustomerUrl($variableName, $configKey)
    {
        return $this->$variableName ?: $this->urlGenerator->route(
            $this->config->get('paybox.customer_return_routes_names.' . $configKey));
    }

    /**
     * Get transaction url.
     *
     * @return string
     */
    protected function getTransactionUrl()
    {
        return $this->transactionVerifyUrl ?: $this->urlGenerator->route(
            $this->config->get('paybox.transaction_verify_route_name'));
    }

    /**
     * Send request with authorization.
     *
     * @param string $viewName
     * @param array $parameters
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function send($viewName, array $parameters = [])
    {
        $parameters = $parameters ?: $this->getParameters();

        return $this->view->make($viewName,
            ['parameters' => $parameters, 'url' => $this->getUrl()]);
    }
}
