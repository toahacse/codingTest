<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(){
        $withdrawals = Transaction::where('transaction_type', 'withdrawal')->where('user_id', auth()->user()->id)->orderBy('id', 'DESC')->get(); 
        return view('withdrawal.index', compact('withdrawals'));
    }
    
    public function store(Request $request){
        try {
            $req = $request->all();
            
            $user = User::find(auth()->user()->id);
            $accountType = $user->account_type;
            $balance = $user->balance;
     
            if($balance < (int)$req['amount']) throw new Exception("You don't have enough balance!", 404);
    
            $isIndividual = $accountType === 'individual';
     
            $isFriday = now()->dayOfWeek === 5;
     
            $monthlyWithdrawals = $user->withdrawals()->whereMonth('created_at', now()->month)->sum('amount');
    
            $isFreeWithdrawal = $isIndividual && ($isFriday || $monthlyWithdrawals < 5000);
     
            $withdrawalRate = $isIndividual ? ($isFreeWithdrawal ? 0 : 0.015) : 0.025;
       
            if($withdrawalRate == 0.025){
                $fee = $req['amount'] * ($withdrawalRate/100);
            }else{
                $freeWithdrawalLimit = 1000;
                if ($req['amount'] > $freeWithdrawalLimit) {
                    $fee = ($req['amount'] - $freeWithdrawalLimit) * ($withdrawalRate/100);
                } else {
                    $fee = 0;
                }
            }
    
            Transaction::create([
                'user_id' =>  auth()->user()->id,
                'transaction_type' => 'withdrawal',
                'amount' => $req['amount'],
                'fee' => $fee,
                'date' => now(),
            ]);
    
            $user->update([
                'balance' => $user->balance - ($req['amount']+$fee) 
            ]);
            
            return redirect()->route('withdrawal.index')->with('success', 'Successfully Withdrawal');

        }catch(Exception $e){
            return redirect()->back()->withErrors($e->getMessage());
        }
    }
}
