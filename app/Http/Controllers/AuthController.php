<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\StoreLoginRequest;
use App\Http\Requests\StoreSignInRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use App\Traits\LoggableController;




class AuthController extends Controller
{
    use LoggableController;

    public function Register(StoreSignInRequest $request)
    {
        try {
            // Validate the incoming request data
            $validated = $request->validated();

            // Hash the password before storing it
            $validated['password'] = Hash::make($validated['password']);

            // Create the new user
            $user = User::create($validated);

            // Send email verification notification
            $user->sendEmailVerificationNotification();

            // Manually generate the verification URL (as per your original code)
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60), // Link valid for 60 minutes
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            // Log successful registration
            $this->logInfo('User registered successfully.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully. Please check your email for verification link.',
                'data' => $user,
                'verification_url' => $verificationUrl,
            ], 201); // Use 201 Created status for successful resource creation

        } catch (\Exception $e) {
            // Log the error
            $this->logError('User registration failed.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(), // Log request data (be cautious with sensitive info)
                'ip_address' => $request->ip(),
            ]);

            // Return error response
            return response()->json([
                'status' => 'error',
                'message' => 'User registration failed. Please try again later.',
                'error_details' => $e->getMessage(), // Optionally include error details for debugging
            ], 500); // Use 500 Internal Server Error for unexpected errors
        }
    } 
    
    
    public function login(StoreLoginRequest $request)
    {
        try {
            // Validate the incoming request data
            $validated = $request->validated();

            // Attempt to find the user by email
            $user = User::where('email', $validated['email'])->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                // Log failed login attempt (invalid credentials)
                $this->logWarning('Failed login attempt: Invalid credentials.', [
                    'email_attempted' => $validated['email'],
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401); // 401 Unauthorized
            }

            // If user exists and credentials are valid, create a new API token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Log successful login
            $this->logInfo('User logged in successfully.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            // Return success response with user data and token
            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 200); // 200 OK

        } catch (\Exception $e) {
            // Log any unexpected server errors during login
            $this->logError('Login failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->except('password'), // Log request data, but exclude password
                'ip_address' => $request->ip(),
            ]);

            // Return a generic server error response
            return response()->json([
                'status' => 'error',
                'message' => 'Server error. Please try again later.',
                'error_details' => $e->getMessage() // Optionally include error details for debugging
            ], 500); // 500 Internal Server Error
        }
    }

    // public function requestResetOtp(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //     ]);

    //     $user = User::where('email', $request->email)->first();

    //     $otp = rand(100000, 999999);
    //     $expiresAt = Carbon::now()->addMinutes(10);

    //     $user->update([
    //         'otp' => $otp,
    //         'otp_expires_at' => $expiresAt,
    //     ]);

    //     // In production, send OTP via SMS/email. For now, log it:
    //     // \$this->logInfo("OTP for {$user->email}: {$otp}");

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'OTP sent successfully.',
    //         'otp' => $otp, // For testing purposes, remove in production
    //     ]);
    // }

    // public function verifyResetOtp(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //         'otp' => 'required',
    //         'password' => 'required|confirmed|min:8',
    //     ]);

    //     $user = User::where('email', $request->email)
    //         ->where('otp', $request->otp)
    //         ->first();

    //     if (!$user || Carbon::now()->gt($user->otp_expires_at)) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid or expired OTP.',
    //         ], 422);
    //     }

    //     $user->update([
    //         'password' => Hash::make($request->password),
    //         'otp' => null,
    //         'otp_expires_at' => null,
    //     ]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Password reset successful.',
    //     ]);
    // }
 
    public function Logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        // Optionally, you can also revoke all tokens for the user on all devices
        // $request->user()->tokens()->delete();
        // return response()->json(['message' => 'Logged out successfully'], 200);
        return response()->json(
            [
                'status' => 'success',
                'message' => 'Logged out successfully'
            ]
        );
    } 
    
    public function User()
    {
        return User::all();
    }
}
