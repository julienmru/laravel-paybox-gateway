<?php

namespace Cahri\PayboxGateway\Requests;

class AuthorizationWithCapture extends Authorization
{
    /**
     * {@inheritdoc}
     */
    public function getBasicParameters()
    {
        $parameters = parent::getBasicParameters();
        $parameters['PBX_AUTOSEULE'] = 'N';

        return $parameters;
    }
}
