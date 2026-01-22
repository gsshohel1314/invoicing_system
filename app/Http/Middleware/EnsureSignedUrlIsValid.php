<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSignedUrlIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'লিঙ্কটি মেয়াদোত্তীর্ণ বা অবৈধ। নতুন ইনভয়েসের জন্য আবার অনুরোধ করুন।');
        }

        return $next($request);
    }
}
