<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Apis\UtilityController;

use App\Http\Controllers\PasswordResetRequestController;
use App\Http\Controllers\Apis\UserController as ApisUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Resource;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/





Route::post('register/{referral_code}', [UserController::class, 'register']);
Route::post('register', [UserController::class, 'register']);
Route::post('forgot_password', [PasswordResetRequestController::class, 'forgotPassword']);
Route::post('verify_password_token', [PasswordResetRequestController::class, 'verifyToken']);
Route::post('password_reset', [PasswordResetRequestController::class, 'resetPassword']);
Route::post('send-otp', [UserController::class, 'sendOTP']);
Route::post('verify-otp', [UserController::class, 'verifyOtp']);
Route::post('resend-otp', [UserController::class, 'resendOtp']);

Route::post('change-password', [HomeController::class, 'changePassword']);



Route::post('delete-user/{id}', [UserController::class, 'DeleteUser']);

Route::get('all-users', [UserController::class, 'AllUser']);



    Route::post('login', [UserController::class, 'login']);
    Route::post('admin/login', [AdminController::class, 'login']);

    Route::prefix('utility')->name('utility.')->group(function () {
        //utility controllers
        Route::get('states', [UtilityController::class, 'getStates']);
        Route::get('countries', [UtilityController::class, 'Countries']);
        Route::get('eductional-qualification', [UtilityController::class, 'EduQUalification']);
        Route::get('faqs', [UtilityController::class, 'FAQs']);
        Route::get('idcards', [UtilityController::class, 'IDcard']);
        Route::get('cities', [UtilityController::class, 'Cities']);
        Route::get('security-questions', [UtilityController::class, 'SecretQuestions']);
        Route::get('investment-plans', [UtilityController::class, 'InvestmentPlans']);
        Route::get('deposit-plans', [UtilityController::class, 'DepositPlans']);
        Route::get('payment-options', [UtilityController::class, 'PaymentOption']);


    });

    // all routes that needs the cors middlewares added
    Route::middleware(['cors'])->group(function () {
        //user routes
        Route::prefix('user')->name('user.')->group(function () {

        //user profile
        Route::post('logout', [UserController::class, 'logout']);
        Route::post('set-secret-question', [UserController::class, 'setSecretQandA']);
        Route::post('users/{user}', [UserController::class, 'updateProfile']);


        Route::post('issue-support', [ApisUserController::class, 'IssueSupport']);


    });

    Route::prefix('dashboard')->name('dashboard.')->group(function () {

        Route::post('user-invest', [HomeController::class, 'UserInvest']);
        Route::post('user-deposit', [HomeController::class, 'UserDeposit']);
        Route::post('withdraw-money', [HomeController::class, 'WithdrawMoney']);
        Route::post('transer-wallet', [HomeController::class, 'TransferWallet']);
        Route::get('referrals/{user_id}', [HomeController::class, 'getReferrals']);



    });


        Route::get('users', [UserController::class, 'Users']);
        Route::get('user/{userid}', [UserController::class, 'findUser']);

        //
        Route::get('generate-locator', function () {
            $digits_needed = 12;
            $random_number = '';
            $count = 0;
            while ($count < $digits_needed) {
                $random_digit = mt_rand(0, 9);
                $random_number .= $random_digit;
                $count++;
            }
            return response()->json($random_number);
        });




    });

Route::fallback(function(Request $request){
    return $response = [
        'status' => 404,
        'code' => '004',
        'title' => 'route does not exist',
        'source' => array_merge($request->all(), ['path' => $request->getPathInfo()])
    ];
});
