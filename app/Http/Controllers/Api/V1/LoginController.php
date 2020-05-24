<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\User;
use App\Http\Requests\CreditCardRequest;
use LVR\CreditCard\CardCvc;
use LVR\CreditCard\CardNumber;
use LVR\CreditCard\CardExpirationYear;
use LVR\CreditCard\CardExpirationMonth;
use Stripe;
use App\UserWalletTransaction;
use DB;

class LoginController extends Controller
{
    /**
     * Request $request
     */
    public function register(Request $request) {
        $validateData = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email'=>['required', 'string', 'email', Rule::unique('users')],
            'password'=>['required', 'confirmed']
        ]);

        $validateData['password'] = bcrypt($request->password);

        $user = User::create($validateData);

        // $accessToken = $user->createToken('authToken')->accessToken;

        return response(['user'=>$user]);
    }

    public function login(Request $request) {
        $login = $request->validate([
            'email'=>['required'],
            'password'=>['required']
        ]);
            
        if(!Auth::attempt($login)) {
            return response(['message'=>'Invalid login credentials.']);
        }

        $accessToken = Auth::user()->createToken('authToken')->accessToken;

        return response(['user'=>Auth::user(), 'access_token'=>$accessToken]);
    }

    public function get_wallet(Request $request) {
        
        $wallet = $request->user()->wallet;

        return response(['wallet'=>$wallet]);
    }

    public function credit(Request $request) {

        $validateRequest = $request->validate(
            [
                'card_number' => ['required', new CardNumber],
                'expiration_year'=>['required', new CardExpirationYear($request->get('expiration_month'))],
                'expiration_month'=>['required', new CardExpirationMonth($request->get('expiration_year'))],
                'cvc' => ['required', new CardCvc($request->get('card_number'))],
                'line1'=>['required', 'string', 'max:100'],
                'postal_code'=>['required'],
                'city'=>['required','max:50'],
                'state'=>['required', 'max:50'],
                'country'=>['required', 'max:50'],
                'amount'=>['required']
            ]
        );
        // Set API key 
        \Stripe\Stripe::setApiKey(\config('stripe.stripe_api_key'));

        $token  = Stripe\Token::create([
            'card' => [
              'number' => $request->card_number,
              'exp_month' => $request->expiration_month,
              'exp_year' => $request->expiration_year,
              'cvc' => $request->cvc,
            ],
        ]);
        $userId = $request->user()->id;
        
        $api_error = NULL;
        try {  
            $customer = \Stripe\Customer::create(array( 
                'email' => $request->user()->email,
                'name'=>$request->user()->name,
                'address' => [
                            'line1' => $request->line1,
                            'postal_code' => $request->postal_code,
                            'city' => $request->city,
                            'state' => $request->state,
                            'country' => $request->country,
                        ],
                'source'  => $token
            ));
            
        }catch(Exception $e) {
            $api_error = $e->getMessage();
        }

        if(empty($api_error) && $customer){  
            try {  
                $charge = Stripe\Charge::create(array( 
                    'customer' => $customer->id, 
                    'amount'   => ($request->amount*100), 
                    'currency' => 'INR', 
                    'description' => $request->description
                ));

            }catch(Exception $e) {  
                $api_error = $e->getMessage();
            }

            if(empty($api_error) && $charge){ 

                // Retrieve charge details 
                $chargeJson = $charge->jsonSerialize();

                // Check whether the charge is successful 
                if($chargeJson['amount_refunded'] == 0 && empty($chargeJson['failure_code']) && $chargeJson['paid'] == 1 && $chargeJson['captured'] == 1)
                {
                    // Insert tansaction data into the database
                    $transaction = DB::transaction(function () use ($userId, $chargeJson) {
                        $paymentStatus = $chargeJson['status'];
                        $amount = ($chargeJson['amount']/100);

                        $walletTransaction = UserWalletTransaction::create([
                            'user_id' => $userId,
                            'description' => $chargeJson['description'],
                            'amount' => $amount,
                            'currency' => $chargeJson['currency'],
                            'transaction_id' => $chargeJson['balance_transaction'],
                            'status' => $paymentStatus
                        ]);

                        // If the order is successful 
                        if($paymentStatus == 'succeeded'){ 
                            // update the wallet of user
                            DB::table('users')->update(['wallet' => DB::raw("wallet + {$amount}")]);
                        }else{ 
                            // no need any action failed transaction block
                            $walletTransaction = []; 
                        }
                        return $walletTransaction;
                    });

                    return response(['message'=>'Your amount is credited to wallet successfully.','transaction'=>['transaction_id'=>$transaction->transaction_id,'status'=>$transaction->status]]);
                } else {
                    return response(['message'=>'Your transaction is failed. Please try again later.']);
                }
            } else {
                return response(['message'=>'Your transaction is failed. Please try again later.']);
            }
        } else {
            return response(['message'=>'Something went wrong in creating customer.']);
        }
    }
}
