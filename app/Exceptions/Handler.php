<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {	
		if ($exception instanceof MethodNotAllowedHttpException) {
			return response()->json( [
                                        'status' => 'error',
                                        'message' => 'Method is not allowed for the requested route',
                                    ], 405 );
		}
		elseif ($exception instanceof NotFoundHttpException) {
			return response()->json( [
                                        'status' => 'error',
                                        'message' => 'Requested route not found',
                                    ], 405 );
		}	
		elseif ($exception) {
			return response()->json([
										'status' => 'error',
										'message' => $exception->getMessage()
									], 200);
	    }
	
        return parent::render($request, $exception);
    }
}
