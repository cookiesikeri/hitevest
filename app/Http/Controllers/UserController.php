<?php

namespace App\Http\Controllers;

use App\Enums\AccountRequestAction;
use App\Enums\AccountRequestType;
use App\Enums\ActivityType;
use App\Enums\ShutdownLevel;
use App\Enums\TransactionType;
use App\Mail\CreditEmail;
use App\Mail\OnboardingMail;
use App\Mail\TransactionMail;
use App\Models\AccountRequest;
use App\Models\CustomerValidation;
use App\Models\LoanBalance;
use App\Models\Models\InviteeUser;
use App\Models\Models\OtpVerify;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\ManagesResponse;
use App\Traits\ManagesUploads;
use App\Traits\ManagesUsers;
use App\Traits\SendSms;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\WalletTransaction;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Traits\UploadImage;
use App\Jobs\ReferralJob;
use App\Jobs\UserActivityJob;
use App\Mail\OtpMail;
use App\Models\AccountNumber;
use App\Models\ReferralCode;
use App\Models\UserSecretQAndA;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator as FacadesValidator;
use Illuminate\Validation\Validator as ValidationValidator;

class UserController extends Controller
{
    use SendSms, ManagesUsers, ManagesResponse, ManagesUploads;

    protected $jwt;
    protected $utility;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;

        $this->utility = new Functions();
    }

    public function register(Request $request, $referral_code=null)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|between:2,100',
            'email'         => 'required|string',
            'phone' => 'required|string',
            'password'     => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $user                 = new InviteeUser();
            $user->name           = $request->input('name');
            $user->phone   = $request->input('phone');
            $user->email   = $request->input('email');
            $user->password       = $request->input('password');
            if(isset($referral_code)){
                $user->referral_code = $referral_code;
            }
            $user->save();

            $this->sendOTP($user);

            return $user;

        } catch (\Exception $e) {
            //return error message

            return response()->json([
                'errors'  => $e->getMessage(),
                'message' => 'User Registration Failed!',
            ], 409);

        }
    }

    public function resendOtp(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|uuid',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            } else {
                $user_id = $request->input('user_id');
                $user = InviteeUser::on('mysql::read')->findOrFail($user_id);
                $this->sendOtp($user);
            }
        }catch(ModelNotFoundException $me){
            return response()->json(['message'=>'User not found.'], 404);
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function sendOTP($user)
    {
        $otp = mt_rand(10000,99999);
        OtpVerify::on('mysql::write')->create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(env('OTP_VALIDITY'))
        ]);

        Mail::to($user->email)->send(new OtpMail($user->name, $otp));

        return "OTP successfully generated";
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        } else {
            $otp = $request->input('otp');
            $userId = $request->input('user_id');

            $verifyOtp = OtpVerify::on('mysql::read')->where([
                'otp' => $otp,
                'user_id' => $userId
            ])->first();
            if(!empty($verifyOtp)){
                if(Carbon::now() >= $verifyOtp->expires_at){
                    return response()->json(['errors' => 'OTP is no longer valid'],403);
                }
                return $this->registerUsers($userId);
            } else {
                return response()->json(['errors' => 'OTP does not exist'],404);
            }
        }
    }

    public function registerUsers($userId)
    {
        try {
             $inviteeUser = InviteeUser::on('mysql::read')->where('id',$userId)->first();
             $user = '';
             return DB::transaction(function() use ($inviteeUser,$user) {
                 if(!empty($inviteeUser)){
                     $acc_no = '51'.substr(uniqid(mt_rand(), true), 0, 8);

                     $user                 = new User();
                     $user->name           = $inviteeUser->name;
                     $user->phone   = $inviteeUser->phone;
                     $user->email   = $inviteeUser->email;
                     $user->password       = app('hash')->make($inviteeUser->password);
                     $user->save();

                     $wallet = $user->wallet()->save(new Wallet());

                     $accNumber = AccountNumber::on('mysql::write')->create([
                         'account_number'=>$acc_no,
                         'account_name' => 'Wallet ID',
                         'wallet_id'=>$wallet->id,
                     ]);


                     $referralCode = $this->createReferralCode($inviteeUser->name, $user->id);

                     $credentials = $inviteeUser->only(['phone', 'password']);

                     $fullname = $inviteeUser->name;
                     $name_array = explode(' ', $fullname);
                     $first_name = $name_array[0] ?? '';
                     $last_name = $name_array[1] ?? '';


                     CustomerValidation::on('mysql::write')->create([
                         'user_id' => $user->id
                     ]);
                     if (!$token = Auth::attempt($credentials)) {
                         return response()->json(['message' => 'Unable to create token, kindly login'], 401);
                     }
                     Mail::to($inviteeUser->email)
                         ->send(new OnboardingMail($inviteeUser));

                    if(isset($inviteeUser->referral_code)){
                        ReferralJob::dispatch($inviteeUser->referral_code, $user->id);
                    }

                     return response()->json([
                         'status'  => 201,
                         'message' => 'Account created Successfully',
                         'user'    => [
                             'id'           => $user->id,
                             "name"         => $user->name,
                             "phone" => $user->phone,
                         ],
                         'referral_link'=>$referralCode->referral_link,
                         'account_number'=>$user->wallet->account_numbers,
                         'walletBalance' => $user->wallet->balance,
                         'data'           => $this->respondWithToken($token)
                     ], 201);
                 } else {
                     return response()->json([
                         'message' => 'Identity could not be verified.',
                     ], 403);
                 }

             });

        } catch (\Exception $e) {

            return response()->json([
                'errors'  => $e->getMessage(),
                'message' => 'User Registration Failed!',
            ], 409);

        }
    }

    public function createReferralCode($name, $user_id){
        $now = Carbon::now();
        $ex = explode(" ", $name);
        $refCode = str_split($ex[0])[0].$now->second.$now->micro;

        $referralCode = ReferralCode::on('mysql::write')->create([
            'user_id'=>$user_id,
            'referral_code'=>$refCode,
            'referral_link'=>'http://api.hitevest.com/register/'.$refCode,
        ]);

        return $referralCode;
    }


    public function login(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'nullable|string|min:6',
            ]);

            // return $request;
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            $credentials = $request->only(['email', 'password']);

            if (!$token = Auth::attempt($credentials)) {
                return response()->json([
                    'status'    =>  -1,
                    'message'   => 'Invalid credentials'
                ], 401);
            }

            if ($this->isAdminShutdownStatus()) {
                return response()->json(['message' => 'your account has been suspended by the admin. contact customer\'s support'], 405);
            }

            $user = User::on('mysql::read')->where('email', $request->email)->first();
            if(empty($user->customer_verification)){
                CustomerValidation::on('mysql::write')->create([
                    'user_id' => $user->id
                ]);

            }

            if(!empty($user) && $user->status !== 1 && env('APP_ENV') !== 'production'){
                return response()->json(['status' =>  -1,'message' => 'Your Account has been deactivated, please contact Admin'],401);
            }

            if(!$user->referral_code || $user->referral_code == NULL){
                //Create referral code and link for user
                $referralCode = $this->createReferralCode($user->name, $user->id);
            }

            $this->saveUserActivity(ActivityType::LOGIN, '', $user->id);
            // $this->updateUserAccountType($user->id);

            $wallet = Wallet::where('user_id', $user->id)->first();


            return response()->json([
                'status'   =>   200,
                'message'  =>   'Successful',
                'user'     =>   [
                    'id'            => $user->id,
                    "name"    => $user->name,
                    "email"         => $user->email,
                    "phone"         => $user->phone,
                    "bvn"       => $user->bvn,
                    "image"     => $user->image,
                    "dob"       => $user->dob,
                    "sex"       => $user->sex,
                    "status"       => $user->status,
                    'withdrawal_limit' => $user->withdrawal_limit,
                    'shutdown_level' => $user->shutdown_level = 0? 'NO' : 'YES',
                ],

                'referral_link'=> $user->referral_code->referral_link,
                'walletBalance'   =>   $user->wallet->balance,
                'access_token' => $token,
                "expires" => auth()->factory()->getTTL() * 60 * 2,
            ]);

        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    //fetch users
    public function Users()
    {
        try {
        $users = User::orderBy('id', 'desc')->get();
        return response()->json([
        "message" => "All Users retrieved succesfully",
        'users' => $users,
        'status' => 'success',
    ], 200);
    } catch(\Exception $e) {
        return response()->json(['message' => $e->getMessage()],500);
    }
    }

    public function FindUser($userid){

        $userid =  User::find($userid);
        if(!$userid)
        {
            return response()->json([
                "message" => "No record found",
                'status' => 'false',
            ], 404);
        }else
    {
        return response()->json([
            'user' => $userid,
            'status' => 'success',
            "message" => "User retreived succesfully",
            'status' => 'success',
        ], 200);
    }
    }

    //find current user
    public function getAuthUser(Request $request)
    {
        return response()->json(auth()->user());
    }
    //show all users
    public function show(User $user)
    {
        return response()->json($user, 200);
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->createNewToken(auth()->refresh());
    }




    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return array
     */
    protected function respondWithToken($token)
    {


        return array('access_token'=>$token, 'expires_in'=>auth()->factory()->getTTL() * 60 *2,);
    }




    public function update(Request $request, User $user)
    {
        $user = Auth::user();


        $validator = Validator::make($request->all(),[
            'email'         => 'string|unique:users',
            'phone' => 'string|unique:users',
            'image' => 'mimes:jpeg,bmp,png,jpg',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $input = array();

        if ($request->filled('dob')) {
            $input['dob'] = $request->dob;
        }
        if ($request->filled('sex')) {
            $input['sex'] = $request->sex;
        }

        if ($request->filled('password')) {
            $request->merge([
                'password' => bcrypt($request->input('password'))
            ]);
        }

//        return "success";

        if($request->hasFile('image')){

            $file = $request->file('image');
            $disk = 'local';
            $ext = $file->getClientOriginalExtension();
            $path = 'cover-image'.time().'.'.$ext;

            $storage = Storage::disk($disk)->putFileAs('user/profile',$file,$path);
            // $input['profile_image'] = $storage;
            $exists = Storage::disk('local')->get($storage);
            $store = '';
            if($exists){
                $store = Storage::disk('local')->url($storage);
            }

            //return $store;
            $input['image'] = $store;

        }

        if ($this->ownsRecord($user->id)) {

            $user->update($input);


            return response()->json([
                'status'   =>   1 ,
                'message'  =>   'Account updated succesfully',
                'user'     =>   [
                    'id'            => $user->id,
                    "name"    => $user->name,
                    "email"         => $user->email,
                    "phone"         => $user->phone,
                    "bvn"       => $user->bvn,
                    "image"     => $user->image,
                    "dob"       => $user->dob,
                    "sex"       => $user->sex,
                    "status"       => $user->status,
                    'withdrawal_limit' => $user->withdrawal_limit,
                    'shutdown_level' => $user->shutdown_level = 0? 'NO' : 'YES',
                ],

                'referral_link'=> $user->referral_code->referral_link,
                'walletBalance'   =>   $user->wallet->balance,

                //'access_token' => $token,
                //"expires" => auth()->factory()->getTTL() * 60 * 2,
            ]);
        }
        return response()->json(['message' => 'you are not the owner of this account'], 419);
    }


    public function setSecretQandA(Request $request){
        try{
            $request->validate( [
                'user_id' => 'required|uuid',
                'question'=>'required',
                'answer'=>'required',
            ]);

            $user_id = $request->input('user_id');
            $user = User::on('mysql::read')->findOrFail($user_id);

            UserSecretQAndA::on('mysql::write')->create([
                'user_id'=>$user->id,
                'question'=>$request->question,
                'answer'=>Hash::make($request->answer),
            ]);

            return response()->json(['message'=>'Users secret question and answer saved.'], 201);
        }catch(ModelNotFoundException $me){
            return response()->json(['message'=>'User not found.'], 404);
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function change_password(Request $request){
        try{

            $validator = Validator::make($request->all(), [
                    'password' => 'required|confirmed|min:6',
                    'old_password' => 'required',
            ]);

            // return $request;
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }


            $User = Auth::user();

            if(Hash::check($request->old_password, $User->password))
            {
                $User->password = bcrypt($request->password);
                $User->save();

                return response()->json(['message'=>'Password updated successfully.'], 201);

            } else {


                return response()->json(['message'=>'An error occured while changing Password.'], 500);

            }

            }catch(Exception $e){
                return response()->json(['message'=>$e->getMessage()], 500);
            }

    }


}


