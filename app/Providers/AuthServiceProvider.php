<?php

namespace App\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Firebase\Auth\Token\Exception\InvalidToken;
use RuntimeException;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {		
			
			// For Allowing CORS
			$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
			
			$log = ['Date' => date('Y-m-d H:i:s'),
					'Type' => 'Ajax Call',
					'Data' => "IP: $_SERVER[REMOTE_ADDR], Origin: $http_origin"];
			$this->logError($log);
			
			if ($http_origin)
				header("Access-Control-Allow-Origin: $http_origin");					
			
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Allow-Headers: php-auth-user, php-auth-pw, token, X-Requested-With, content-type, authorization');
			header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
			header('X-Content-Type-Options: nosniff');

			// Get All Headers (For Testing Only)
			/*$get_first = function($x){
				return $x[0];
			};
			// Same as getallheaders(), just with lowercase keys
			print_r(array_map($get_first, $request->headers->all()));
			exit;*/
						
			$jwt = $request->header('authorization') ? str_replace('Bearer ', '', $request->header('authorization')) : '';
			
			//for testing only
			/*$jwt = 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjY0MWU3OWQzZjUwOWUyYzdhNjQ1N2ZjOTVmY2U1MGNjOGM3M2VmMDMiLCJ0eXAiOiJKV1QifQ.eyJuYW1lIjoiRGVubnkgQ2hvaSIsInBpY3R1cmUiOiJodHRwczovL2xoMy5nb29nbGV1c2VyY29udGVudC5jb20vLWU0UlBiZ1FHVE04L0FBQUFBQUFBQUFJL0FBQUFBQUFBQUFBL0FDSGkzcmVKall2XzAzblYzby1qb21Fc2U3LXd3MDZRY0EvcGhvdG8uanBnIiwiaXNzIjoiaHR0cHM6Ly9zZWN1cmV0b2tlbi5nb29nbGUuY29tL3RvdXJib3QtYXBpLWZpcmViYXNlIiwiYXVkIjoidG91cmJvdC1hcGktZmlyZWJhc2UiLCJhdXRoX3RpbWUiOjE1NjU3MjgwMTQsInVzZXJfaWQiOiJiU3dUWkROdXhNTTNRUGswUHJnRnNkMWp0dXIxIiwic3ViIjoiYlN3VFpETnV4TU0zUVBrMFByZ0ZzZDFqdHVyMSIsImlhdCI6MTU2NTcyODAxNCwiZXhwIjoxNTY1NzMxNjE0LCJlbWFpbCI6ImRjaG9pQHRlYW1hbWVyaWNhbnkuY29tIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsImZpcmViYXNlIjp7ImlkZW50aXRpZXMiOnsiZ29vZ2xlLmNvbSI6WyIxMDI5NjYzOTA5NjI5ODgxMDEwOTQiXSwiZW1haWwiOlsiZGNob2lAdGVhbWFtZXJpY2FueS5jb20iXX0sInNpZ25faW5fcHJvdmlkZXIiOiJnb29nbGUuY29tIn19.FJ4IyxyjYSGpXpBjRGuX7-nSREbfroePKbwmOfLwb1xLT2T3XezdMk299MYtXrIfquW5S2t8iPLkZhQkzyBC4FtA2GUfQV13EkkLvuDk5LUGr-ppaqDXHoA6R8xnCNGPpNkLwS2osNjRh0SPQA0YgeapLYV3x9lZHdcql4uL5sDWQpm2HjVOR4wGGuFINCMselH5p-QS8DmtjNOF-aledTgvoKIwWFMCPz5SQ3vfHjmOiUtlE77HdKetLKpY2zLXdgnsph1tX1JDR3Ceegn5_S3NyuBJxCqTb1U_sJCoDTFCiLW5QGJKu_ISl50uLSciGXTsEjys88goSkHFsLJAVw';*/
			
			if ($jwt && substr($request->header('authorization'), 0, 6) != 'Basic ') {
				//$serviceAccount = ServiceAccount::fromJsonFile(env('APP_PATH').'/tourbot-api-firebase-firebase-adminsdk-ub2bk-94a1b25bb5.json'); //Denny test				
				$serviceAccount = ServiceAccount::fromJsonFile(env('APP_PATH').'/tourbotapi-firebase-adminsdk-0m5bp-1dedae8d10.json');  //Tania prod
				
				$firebase = (new Factory)
							->withServiceAccount($serviceAccount)
							->create();		

				try {
					$verifiedIdToken = $firebase->getAuth()->verifyIdToken($jwt);
				} catch (InvalidToken $e) {
					//echo $e->getMessage();
					
					$log = ['Date' => date('Y-m-d H:i:s'),
					'Error' => 'Unauthorized',
					'Data' => "jwt: $jwt, message: ".$e->getMessage()];
					$this->logError($log);
					
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(['status' => 'failed', 'message' => 'Unauthorized', 'error' => 'Unauthorized']);				
					exit;
				} catch (RuntimeException $e) {
					//echo $e->getMessage();
					
					$log = ['Date' => date('Y-m-d H:i:s'),
					'Error' => 'Unauthorized',
					'Data' => "jwt: $jwt, message: ".$e->getMessage()];
					$this->logError($log);
					
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(['status' => 'failed', 'message' => 'Unauthorized', 'error' => 'Unauthorized']);				
					exit;
				}
				$uid = $verifiedIdToken->getClaim('sub');
				$user = $firebase->getAuth()->getUser($uid);          
				$email = $user->email;

				$user = User::where('email', $email)->first();
			}
			else {
				$userName = $request->header('php-auth-user') ? $request->header('php-auth-user') : '';
				$password = $request->header('php-auth-pw') ? $request->header('php-auth-pw') : '';
				$token = $request->header('token') ? $request->header('token') : '';
				
				$log = ['Date' => date('Y-m-d H:i:s'),
				'Type' => 'Login',
				'Data' => "username: $userName, password: $password, token: $token"];
				$this->logError($log);
				
				$user = User::where('UserName', $userName)
						->where(function ($query) use ($password, $token) {
							$query->where('Password', '=', $password)
								->orWhere('HashPW', '=', $token);
						})->first();		
			}
						
			if (!$user) { 			
				if ($jwt) {
					$data = "jwt: $jwt";
					if (isset($email))
						$data .= ", email: $email";
				}
				else
					$data = "username: $userName, password: $password, token: $token";
				$log = ['Date' => date('Y-m-d H:i:s'),
				'Error' => 'Unauthorized',
				'Data' => $data];
				$this->logError($log);
				
				header('Content-Type: application/json; charset=UTF-8');
				echo json_encode(['status' => 'failed', 'message' => 'Unauthorized', 'error' => 'Unauthorized']);				
				exit;
			}
			
			//print_r($user); exit;
					
			return $user;
        });
    }
	
	public function logError($log) {
		//first parameter passed to Monolog\Logger sets the logging channel name
		$orderLog = new Logger('error_logs');
		$orderLog->pushHandler(new StreamHandler(storage_path('logs/errors.log')), Logger::INFO);
		$orderLog->info('ErrorsLog', $log);
	}
}
