<?php

namespace JulienMru\PayboxGateway\Requests;

use Carbon\Carbon;
use JulienMru\PayboxGateway\Language;
use JulienMru\PayboxGateway\Currency;
use JulienMru\PayboxGateway\Services\Amount;
use JulienMru\PayboxGateway\Services\Pad;
use JulienMru\PayboxGateway\Services\HmacHashGenerator;
use JulienMru\PayboxGateway\Services\ServerSelector;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Routing\UrlGenerator;

class SeveralTimes extends AuthorizationWithCapture
{
    /**
     * {@inheritdoc}
     */
    /**
     * @var array
     */
    protected $payments = [];

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
        foreach($this->payments as $i => $payment) {
            if ($i == 1) {
                $parameters['PBX_TOTAL'] = $this->payments[1]['amount'];
                $parameters['PBX_DEVISE'] = $this->payments[1]['currencyCode'];
            } else {
                $parameters['PBX_2MONT' . ($i-1)] = $payment['amount'];
                $parameters['PBX_DATE' . ($i-1)]  = $payment['date']->format('j/m/Y');
            }
        }
        return $parameters;
    }
    
    public function setPayment1(float $amount, $currencyCode = Currency::EUR)
    {
        return $this->setPaymentN(1, Carbon::now(), $amount, $currencyCode);
    }
    
    public function setPayment2(Carbon $date, float $amount)
    {
        return $this->setPaymentN(2, $date, $amount);
    }
    
    public function setPayment3(Carbon $date, float $amount)
    {
        return $this->setPaymentN(3, $date, $amount);
    }
    
    public function setPayment4(Carbon $date, float $amount)
    {
        return $this->setPaymentN(4, $date, $amount);
    }
    
    public function setPaymentN(int $n, Carbon $date, float $amount, $currencyCode = Currency::EUR)
    {
        $this->payments[$n] = [
            'date' => $date,
            'amount' => $this->amountService->get($amount, $this->amountFill),
            'currencyCode' => $currencyCode,
        ];

        return $this;
    }
    
}
