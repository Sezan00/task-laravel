<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendOtpEmailJob;

class AuthController extends Controller
{
    //  1. Register Method
    public function register(RegisterRequest $request){
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password'=> Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    }

    //  2. OTP Send Method
    public function sendOtp(Request $request){
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message'=>'User not found'], 404);
        }

        $otp = rand(100000, 999999);
        $user->otp_code = $otp;
        $user->otp_expires_at = Carbon::now()->addMinute(2);
        $user->save();

        SendOtpEmailJob::dispatch($user->email, $otp);

        return response()->json(['message' => 'OTP sent to your email']);
    }

    //  3. OTP Verify Method
   public function verifyOtp(Request $request)
{
    // Validate incoming request
    $request->validate([
        'email'    => 'required|email',
        'otp_code' => 'required'
    ]);

    // find with user email
    $user = User::where('email', $request->email)->first();

    
    //if email or User is worng then response
    if (!$user) {
        return response()->json([
            'status'  => 'not_found',
            'message' => 'User not found.'
        ], 404);
    }

    
    //when otp time is over then reaction
    if (!$user->otp_expires_at || now()->greaterThan($user->otp_expires_at)) {
        return response()->json([
            'status'  => 'expired',
            'message' => 'Your OTP has expired. Please request a new one.'
        ], 400);
    }

    //when OTP is worng 
    if ($user->otp_code != $request->otp_code) {
        return response()->json([
            'status'  => 'invalid_otp',
            'message' => 'The OTP you entered is incorrect.'
        ], 400);
    }

    //when everything is fine then remove OTP
    Auth::login($user);

    $user->otp_code = null;
    $user->otp_expires_at = null;
    $user->save();

    //when login successfully
    return response()->json([
        'status'  => 'success',
        'message' => 'Login successful.',
        'user'    => $user
    ], 200);
}   

    public function resendOtp(Request $request){
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if(!$user){
            return response()->json([
                'message' => 'user not found'
            ], 400);
        }

        $otp = rand(100000, 999999);
        $user->otp_code = $otp;
        $user->otp_expires_at = now()->addMinute(2);
        $user->save();
    

    Mail::raw("your 2nd otp is: {$otp}", function($message) use ($user) {
        $message->to($user->email)->subject("Your new OTP");
    });
    
    return response()->json([
        'message' => 'OTP resent successfully'
    ]);
    }
}
