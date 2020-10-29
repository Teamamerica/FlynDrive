<?php

namespace App\Http\Middleware;

class CorsMiddleware {
    public function handle($request, \Closure $next)
    {
		$response = $next($request);
		/*$response->header('Access-Control-Allow-Methods', 'HEAD, GET, POST, PUT, PATCH, DELETE, OPTIONS');
		$response->header('Access-Control-Allow-Headers', 'php-auth-user, php-auth-pw, token');
		$response->header('Access-Control-Allow-Credentials', true);
		$response->header('Access-Control-Allow-Origin', 'http://dev5.volateam.com');*/
		return $response;
	}
}