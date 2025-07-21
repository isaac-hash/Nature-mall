<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Traits\LoggableController;

class EmailVerificationController extends Controller
{
    use LoggableController;
    public function verify(Request $request, $id, $hash)
    {
        try {
            // 1. Check if the URL has a valid signature
            if (!URL::hasValidSignature($request)) {
                $this->logWarning('Email verification failed: Invalid or expired signature.', [
                    'user_id' => $id,
                    'hash_attempted' => $hash,
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Invalid or expired verification link.'], 403);
            }

            // 2. Find the user by ID
            $user = User::findOrFail($id);

            // 3. Check if the hash matches the user's email hash
            if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
                $this->logWarning('Email verification failed: Invalid verification hash.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'hash_attempted' => $hash,
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Invalid verification hash.'], 403);
            }

            // 4. Check if the email is already verified
            if ($user->hasVerifiedEmail()) {
                $this->logInfo('Email verification: Email already verified.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Email already verified.']);
            }

            // 5. Mark the email as verified
            $user->markEmailAsVerified();

            // Log successful email verification
            $this->logInfo('Email verified successfully.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Email verified successfully.']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log if user not found
            $this->logError('Email verification failed: User not found.', [
                'user_id_attempted' => $id,
                'error_message' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json(['message' => 'User not found.'], 404);
        } catch (\Exception $e) {
            // Log any other unexpected errors
            $this->logError('Email verification failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id_attempted' => $id,
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during email verification. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    
    public function resend(Request $request)
    {
        try {
            // Ensure the user is authenticated before proceeding
            if (!$request->user()) {
                $this->logWarning('Email resend attempt: Unauthenticated user.', [
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Check if the user's email is already verified
            if ($request->user()->hasVerifiedEmail()) {
                $this->logInfo('Email resend: User email already verified.', [
                    'user_id' => $request->user()->id,
                    'email' => $request->user()->email,
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Email already verified.']);
            }

            // Send the email verification notification
            $request->user()->sendEmailVerificationNotification();

            // Log successful resend
            $this->logInfo('Verification email resent successfully.', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Verification email resent.']);

        } catch (\Exception $e) {
            // Log any unexpected errors during resend
            $this->logError('Resending verification email failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $request->user() ? $request->user()->id : 'N/A',
                'email' => $request->user() ? $request->user()->email : 'N/A',
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during resending verification email. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
