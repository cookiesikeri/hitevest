<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rules\MatchOldPassword;
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
use App\Models\TransferTransaction;
use App\Models\UserDeposit;
use App\Models\UserInvestment;
use App\Models\UserSecretQAndA;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator as FacadesValidator;
use Illuminate\Validation\Validator as ValidationValidator;

class HomeController extends Controller
{
    use SendSms, ManagesUsers, ManagesResponse, ManagesUploads;

    protected $jwt;
    protected $utility;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;

        $this->utility = new Functions();
    }

    public function UserDeposit(Request $request){
        try{

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|uuid',
            'type' => 'required',
            'address' => 'string|nullable',
            'amount' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }


        $user_id = $request->input('user_id');
        $user = User::on('mysql::read')->findOrFail($user_id);

        $data = UserDeposit::on('mysql::write')->create([

            'user_id' => auth()->user()->id,
            'type'=>$request->type,
            'address'=>$request->address,
            'amount'=>$request->amount,
            'transaction_id'=>$this->generateTransactionID(),


        ]);

        return response()->json(['data' => $data, 'message' => 'Payment_successful'],201);

    }catch(ModelNotFoundException $me){
        return response()->json(['message'=>'User not found.'], 404);
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function generateTransactionID() {
        $transactionID = 0;

            $id = random_int(1000, 999999999999);
            $length = strlen((string)$id);
            if($length < 12 || $length > 12) {
                $id = $this->generateTransactionID();
            }
            $transactionID = 'HIT-' . $id;
            return $transactionID;

        }


        public function WithdrawMoney(Request $request)
        {
            try{

                $validator = Validator::make($request->all(), [
                    'user_id' => 'required|uuid',
                    'address' => 'required',
                    'amount' => 'required|numeric|min:500'
                ],
                [
                    'amount.required'      =>  'Amount is required and cannot be empty',
                    'amount.min'          =>  'Amount must be at least $1000.'

                ]);
                if ($validator->fails()) {
                    return response()->json($validator->errors(), 422);
                }

            $user = Auth::user();
            $ref = Str::random(10);


            $wallet = $user->wallet()->first();

            $current_balance = $wallet->deposit_Wallet;

            if ($current_balance >= $request->amount)
            {

                // Debit the User the order amount
                $new_balance = bcsub($current_balance, $request->amount);
                $wallet->deposit_Wallet = $new_balance;

                $wallet_transaction = new WalletTransaction();
                $wallet_transaction->type = 'Withdraw Money';
                $wallet_transaction->status = 'Pending';
                $wallet_transaction->amount = $request->amount;
                $wallet_transaction->address = $request->address;
                $wallet_transaction->user_id = Auth::id();
                $wallet_transaction->reference = $ref;

                $wallet->walletTransaction()->save($wallet_transaction);
                $wallet->save();

                // $user->request_moneys()->save($order);
                return response()->json(['message' => 'Withdrawer_Request_Successful', 'status' => true],200);

                return redirect()->back();

            }else{
                // $current_balance->status =0;
                $wallet_transaction = new WalletTransaction();
                $wallet_transaction->type = 'Withdraw Money';
                $wallet_transaction->status = 'Low Balance';
                $wallet_transaction->amount = $request->amount;
                $wallet_transaction->address = $request->address;
                $wallet_transaction->user_id = Auth::id();
                $wallet_transaction->reference = $ref;

                $wallet->walletTransaction()->save($wallet_transaction);
                $wallet->save();
                return response()->json(['message' => 'Withdrawer_Request_Failed!_Wallet_Balance_Low', 'status' => false],419);

            }
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 422);
        }

        }


        public function getReferrals($user_id){
            try{
                $user = User::on('mysql::read')->findOrFail($user_id);
                $accounts = array();

                foreach($user->referrals as $ben){

                    //$acc = AccountNumber::on('mysql::read')->where('account_number', $ben['beneficiary_account_number'])->first();
                    $ref = User::on('mysql::read')->find($ben->referred_id);
                    if($ref && $ref != null){
                        $accounts[] = $ref;
                    }
                }

                return response()->json(['message'=>'Referees_Retrieved_Successfully', 'referrals'=>$accounts,], 200);
            }catch(ModelNotFoundException $me){
                return response()->json(['message'=>'User not found.'], 404);
            }catch(Exception $e){
                return response()->json(['message'=>$e->getMessage()], 500);
            }

        }

        public function TransferWallet(Request $request) {
            try{

                $validator = Validator::make($request->all(), [
                    'amount' => 'required',
                    'email' => 'required|email'

                ]);
                if ($validator->fails()) {
                    return response()->json($validator->errors(), 422);
                }

            if ($request->amount <= 1000) {

                return response()->json(['message'=>'Amount_to_transfer_CANNOT_be_less_than_1000.'], 419);
            }

            // $id = Session::get('user');
            $id = Auth::user()->wallet()->first();
            $sender = $this->fetch_customer($id->id);
            $receiver = User::where('email',$request->email)->with(['wallet'])->first();
    // dd($id, $receiver);
            if ($receiver) {
                if ($this->update_wallet($sender->wallet->id, $receiver->wallet->id, $request->amount)) {

                    return response()->json(['message'=>'Transfer_sent_successfully.', 'status' => true], 200);

                } else {
                        return response()->json(['message'=>'Insufficient_Wallet_Balance.', 'status' => false], 413);
                }
            } else {

                return response()->json(['message'=>'Receiver_not_found_on_this_platform.', 'status' => false], 404);

            }

        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 500);
        }
        }


        public function update_wallet($sender_wallet_id, $receiver_wallet_id, $amount) {
            try {
                $sender_wallet = Wallet::where('id',$sender_wallet_id)->first();
                if ($sender_wallet->deposit_Wallet >= $amount) {
                    DB::beginTransaction();

                    $sender_balance = $sender_wallet->deposit_Wallet - $amount;
                    $sender_wallet->update(['deposit_Wallet' => $sender_balance]);

                    $receiver_wallet = Wallet::where('id', $receiver_wallet_id)->first();
                    $receiver_balance = $receiver_wallet->deposit_Wallet + $amount;
                    $receiver_wallet->update(['deposit_Wallet' => $receiver_balance]);

                    TransferTransaction::create([
                        'sender_id' => $sender_wallet_id,
                        'receiver_id' => $receiver_wallet_id,
                        'amount' => $amount
                    ]);

                    DB::commit();
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $exception) {
                DB::rollback();
                Log::info($exception->getMessage());
                return false;
            }
        }

        public function fetch_customer($id) {
            return User::where('id',$id)->with(['wallet'])->first();
        }

        public function storePassword(Request $request)
        {
            $request->validate([
                'current_password' => ['required', new MatchOldPassword],
                'new_password' => ['required'],
                'new_confirm_password' => ['same:new_password'],
            ]);

            User::find(auth()->user()->id)->update(['password'=> Hash::make($request->new_password)]);

            dd('Password change successfully.');
        }

    }



