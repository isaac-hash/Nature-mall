<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        
        
        return response()->json([
            'message' => 'User information not implemented yet',
            'user' => $request->user() // Assuming user is authenticated
        ]);
    }
}
