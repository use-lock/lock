<?php

declare(strict_types=1);

namespace App\Realms\Http\Controllers;

use App\Realms\Support\DomainCheckToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Answers on every host, so a domain can be probed before its realm exists. */
final class DomainCheckController
{
    public function __invoke(Request $request): Response
    {
        return response(DomainCheckToken::for($request->getHost()), 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
        ]);
    }
}
