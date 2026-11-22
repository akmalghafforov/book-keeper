<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RejectBearerDraftReview
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('admin/transaction-drafts', 'admin/transaction-drafts/*') && $request->bearerToken()) {
            return response()->json(['message' => __('Draft review requires a human web session.')], 403);
        }

        return $next($request);
    }
}
