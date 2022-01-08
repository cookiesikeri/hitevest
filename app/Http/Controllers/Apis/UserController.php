<?php

namespace App\Http\Controllers\Apis;

use App\Jobs\PeaceAccountCreationJob;
use App\Mail\TransactionMail;
use App\Models\AccountNumber;
use App\Models\BankTransfer;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Functions;
use App\Traits\ManagesUsers;
use Illuminate\Http\Request;
use App\Interfaces\UserInterface;
use App\Mail\DebitEmail;
use App\Mail\OtpMail;
use App\Mail\TransactionPinChangeEmail;
use App\Models\Admin;
use App\Models\AgentSavings;
use App\Models\AirtimeTransaction;
use App\Models\Beneficiaries;
use App\Models\Business;
use App\Models\ContactMessage;
use App\Models\DataTransaction;
use App\Models\LoanBalance;
//use App\Models\LoanTransaction;
use App\Models\Models\OtpVerify;
use App\Models\PaystackRefRecord;
use App\Models\PosLocation;
use App\Models\PosRequest;
use App\Models\PowerTransaction;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Saving;
use App\Models\TVTransaction;
use App\Models\User;
use App\Models\UserSecretQAndA;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Traits\SendSms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use SendSms, ManagesUsers;

    protected $user;
    protected $utility;

    /**
     * UserController constructor.
     * @param UserInterface $user
     */
    public function __construct(UserInterface $user)
    {
        $this->user = $user;
        $this->utility = new Functions();
        $this->middleware('limit');
    }

    public function is_user($user_id)
    {
        return response()->json($this->user->is_user($user_id));
    }

    public function edit_profile(Request $request)
    {
        $data = array(
            'name' =>  $request->name,
            'address' =>  $request->address,
            'email' =>  $request->email,
            'phone' =>  $request->phone,
        );

        $validator = Validator::make($data, [
            'name' =>  'nullable|string',
            'address' =>  'nullable|string',
            'email' =>  'nullable|string',
            'phone' =>  'nullable|string|max:11'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $user = $this->user->is_user($request->id);

        if (is_int($user)) {
            return response()->json("User not found.");
        }

        if (strlen($data['name']) <= 1) {
            $data['name'] = $user->name;
        }

        // if(strlen($data['email']) <= 1) {
        //     $data['email'] = $user->email;
        // }

        if(strlen($data['address']) <= 1) {
            $data['address'] = $user->address;
        }

        if (strlen($data['phone']) <= 1) {
            $data['phone'] = $user->phone;
        }

        $update = $user->update($data);

        return response()->json($update);
    }



    public function create_user_wallet($user_id)
    {
        return response()->json($this->user->create_user_wallet($user_id));
    }

    public function get_user_wallet_balance($user_id)
    {
        return response()->json($this->user->get_user_wallet_balance($user_id));
    }


    public function user_has_sufficient_wallet_balance($user_id, $amount)
    {
        return response()->json($this->user->user_has_sufficient_wallet_balance($user_id, $amount));
    }

    public function update_user_wallet_balance($user_id, $amount)
    {
        return response()->json($this->user->update_user_wallet_balance($user_id, $amount));
    }

    public function debit_user_wallet($user_id, $amount)
    {
        return response()->json($this->user->debit_user_wallet($user_id));
    }


    public function getUserSecretQuestion($user_id){
        try{

            $user = User::on('mysql::read')->findOrFail($user_id);

            return response()->json(['message'=>'Success', 'secret_question'=>$user->secret_q_and_a]);
        }catch(ModelNotFoundException $me){
            return response()->json(['message'=>'User not found.'], 404);
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }





    public function log_wallet_transaction($user, $amount_entered, $new_balance, $transaction_type, $description, $transaction_status, $transaction_reference)
    {
        return response()->json($this->user->log_wallet_transaction($user, $amount_entered, $new_balance, $transaction_type, $description, $transaction_status, $transaction_reference));
    }

    public function generate_transaction_reference()
    {
        return response()->json($this->user->generate_transaction_reference());
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

            return response()->json(['message'=>'Referees Retrieved Successfully', 'referrals'=>$accounts,], 200);
        }catch(ModelNotFoundException $me){
            return response()->json(['message'=>'User not found.'], 404);
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 404);
        }

    }


    public function getTodayTransaction($user_id){
        try{
            $user = User::on('mysql::read')->findOrFail($user_id);
            $transactions = array();

            $wallTr = WalletTransaction::on('mysql::read')->where([['wallet_id', $user->wallet->id], ['created_at', Carbon::today()]])->get();

            return response()->json(['message'=>'Todays Transactions Retrieved Successfully', 'transactions'=>$wallTr], 200);
        }catch(Exception $e){

            return response()->json(['message'=>$e->getMessage()], 420);
        }
    }

    public function getMonthTransaction($user_id, $month){
        try{
            //return Carbon::parse('2021-04-07 13:09:58')->format('F');
            $user = User::on('mysql::read')->findOrFail($user_id);
            $transactions = array();

            $wallTr = WalletTransaction::on('mysql::read')->where('wallet_id', $user->wallet->id)->get();
            foreach($wallTr as $trans){
                $tDay = Carbon::parse($trans['created_at']);
                $today = Carbon::now();
                if($tDay == $today){
                    $transactions[] = $trans;
                }
            }
            return response()->json(['message'=>'Todays Transactions Retrieved Successfully', 'transactions'=>$wallTr], 200);
        }catch(Exception $e){

            return response()->json(['message'=>$e->getMessage()], 420);
        }
    }

    public function index()
    {
        $users = User::select('users.name', 'users.email', 'users.phone', 'users.account_type_id AS kyc_level', 'account_numbers.account_number', 'wallets.balance', 'users.created_at', 'users.updated_at')
            ->join('wallets', 'users.id', '=', 'wallets.user_id')->join('account_numbers', 'wallets.id', '=', 'account_numbers.wallet_id')->where('account_numbers.account_name', 'Wallet ID')->paginate(10);
        return response()->json(['success'=>true, 'data' =>$users, 'message' => 'users fetched successfully']);
    }

    public function IssueSupport(Request $request) {
        try{

            $validator = Validator::make($request->all(), [
                'name'      =>  'required|string|max:50',
                'email'     =>  'required|email|max:50',
                'phone'     =>  'nullable|string|max:15|min:5',
                'subject'   =>  'required|string',
                'message'   =>  'required|string',
                'file'      =>  'nullable',
            ]);

            // return $request;
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            // upload file
            if ($request->file != '') {
                try {
                    $file_path       = $request->file->store('public/complain_files');
                    $file_path_parts = pathinfo($file_path);
                    $file            = $file_path_parts['filename'] . '.' . $file_path_parts['extension'];
                } catch (\Exception $th) {
                    return response()->json(['message'=>$th->getMessage()], 505);
                }
            }

        $message= ContactMessage::on('mysql::write')->create([
            'name'=>$request->name,
            'email'=>$request->email,
            'phone'=>$request->phone,
            'subject'=>$request->subject,
            'message'=>$request->message,
            'file'=>$request->file,

        ]);
        return response()->json(['message'=>'Message sent successfully.'], 201);
        }catch(ModelNotFoundException $me){
            return response()->json(['message'=>'Model not found.'], 404);
        }catch(Exception $e){
            return response()->json(['message'=>$e->getMessage()], 505);
        }

}








}
