<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class fahmidController extends Controller
{
     public function ProfileEdit(Request $request)
    {
        $user_id = Auth::user()->id;
        return view('profile', compact('user_id'));
    }
}
