<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class JsonResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set Accept header to JSON
        $request->headers->set('Accept', 'application/json');

        // Capture any exceptions that might occur in API processing
        try {
            $response = $next($request);
            
            // Log the response status for debugging
            Log::info('API Response Status: ' . $response->getStatusCode());
            
            return $response;
        } catch (\Exception $e) {
            Log::error('JsonResponseMiddleware caught exception: ' . $e->getMessage());
            
            // Return JSON error response instead of HTML
            return response()->json([
                'message' => 'Error processing request: ' . $e->getMessage()
            ], 500);
        }
    }
}
