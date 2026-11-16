<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

class AuthenticateWithRejectionMarker extends Authenticate
{
    public const REJECTED = 'sakip.authentication_rejected';

    /**
     * Tandai hanya penolakan guard; exception dari Action bukan bukti penolakan awal.
     *
     * @param  Request  $request
     * @param  array<string|null>  $guards
     */
    protected function unauthenticated($request, array $guards): never
    {
        $request->attributes->set(self::REJECTED, true);
        parent::unauthenticated($request, $guards);
    }
}
