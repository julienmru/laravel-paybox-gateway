<?php

namespace Cahri\PayboxGateway\Requests;

use Carbon\Carbon;
use Cahri\PayboxGateway\Language;
use Cahri\PayboxGateway\Currency;
use Cahri\PayboxGateway\Services\Amount;
use Cahri\PayboxGateway\Services\Pad;
use Cahri\PayboxGateway\Services\HmacHashGenerator;
use Cahri\PayboxGateway\Services\ServerSelector;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Routing\UrlGenerator;

class Subscription extends Authorization
{
    /**
     * {@inheritdoc}
     */
    /**
     * @var int|null
     */
    protected $recurringAmount = 0;
    /**
     * @var int|null
     */
    protected $paymentDay = 0;
    /**
     * @var int|null
     */
    protected $paymentFrequency = 1;
    /**
     * @var int|null
     */
    protected $paymentCount = 0;
    /**
     * @var int|null
     */
    protected $paymentShift = 0;

    /**
     * Authorization constructor.
     *
     * @param ServerSelector $serverSelector
     * @param Config $config
     * @param HmacHashGenerator $hmacHashGenerator
     * @param UrlGenerator $urlGenerator
     * @param ViewFactory $view
     * @param Amount $amountService
     * @param Pad $padService
     */
    public function __construct(
        ServerSelector $serverSelector,
        Config $config,
        HmacHashGenerator $hmacHashGenerator,
        UrlGenerator $urlGenerator,
        ViewFactory $view,
        Amount $amountService,
        Pad $padService
    ) {
        parent::__construct($serverSelector, $config, $hmacHashGenerator, $urlGenerator, $view, $amountService);
        $this->padService = $padService;
    }

    public function getBasicParameters()
    {
        $parameters = parent::getBasicParameters();

        $parameters['PBX_CMD'] 	.= 'PBX_2MONT'.$this->padService->get($this->recurringAmount, 10);
        $parameters['PBX_CMD'] 	.= 'PBX_QUAND'.$this->padService->get($this->paymentDay, 2);
        $parameters['PBX_CMD'] 	.= 'PBX_FREQ'.$this->padService->get($this->paymentFrequency, 2);
        $parameters['PBX_CMD'] 	.= 'PBX_NBPAIE'.$this->padService->get($this->paymentCount, 2);
        $parameters['PBX_CMD'] 	.= 'PBX_DELAIS'.$this->padService->get($this->paymentShift, 3);

        return $parameters;
    }
    
    public function setInitialAmount($amount, $currencyCode = Currency::EUR)
    {
        $this->amount = $this->amountService->get($amount, $this->amountFill);
        $this->currencyCode = $currencyCode;

        return $this;
    }
    
    public function setRecurringAmount($amount)
    {
        $this->recurringAmount = $this->amountService->get($amount, $this->amountFill);

        return $this;
    }
    
    public function setPaymentDay($day)
    {
        $this->paymentDay = (int)$day;

        return $this;
    }
    
    public function setPaymentFrequency($frequency)
    {
        $this->paymentFrequency = (int)$frequency;

        return $this;
    }
    
    public function setPaymentCount($count)
    {
        $this->paymentCount = (int)$count;

        return $this;
    }
    
    public function setPaymentShift($shift)
    {
        $this->paymentShift = (int)$shift;

        return $this;
    }
}
