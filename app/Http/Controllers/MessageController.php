<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function index()
    {
        $messages = Message::all();
        return view('messages', compact('messages'));
    }
    public function sendMessage($phone)
    {
        Message::create(
            [
                'phone'=>$phone,
                'code'=>random_int(100000, 999999)
            ]
        );
        return response()->json (['message' => 'Code sent successfully']);
    }
}

