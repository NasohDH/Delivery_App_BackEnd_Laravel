<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLocation;
use App\Models\User;
use App\Traits\SendsMessages;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\Fluent\Concerns\Has;

class AuthController extends Controller
{
    use SendsMessages;
    public function login(Request $request){
        $validator = Validator::make($request->all() , [
            'phone' => ['required' ,'regex:/^\+(\d{1,3})[-.\s]?\(?(\d{1,4})\)?[-.\s]?\(?(\d{1,4})\)?[-.\s]?\d{4,10}$/'],
            'password' => ['required' , 'min:8']
        ]);

        if ($validator->fails()){
            return response()->json([
                'message' => "login failed",
                'data' =>$validator->errors()
            ],401 );
        }
        $user = User::where('phone', $request->phone)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
        ] ,200);
    }
    public function logout(Request $request){
        $request->user()->tokens()->delete();
        return response()->json([
            'messages'=>'logged out successfully'
        ],200);
    }
    public function sendCode(Request $request){

        $validator = Validator::make($request->all() , [
            'action' => 'required|in:register,reset_password'
        ]);
        if ($validator->fails()){
            return response()->json([
                'data' =>$validator->errors()
            ],400);
        }
        $action = $request->input('action');

        $rules = [
            'phone' => [
                'required',
                'regex:/^\+(\d{1,3})[-.\s]?\(?(\d{1,4})\)?[-.\s]?\(?(\d{1,4})\)?[-.\s]?\d{4,10}$/'
            ],
        ];
        if ($action === 'register') {
            $rules['phone'][] = 'unique:users,phone';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()){
            return response()->json([
                'message' => "Sending failed",
                'data' =>$validator->errors()
            ],401);
        }
        $phone = $request->input('phone');
        $this->sendMessage($phone , $action);
        return response()->json(['message' => 'Code sent successfully'],200);
    }
    public function verify(Request $request){

        $validator = Validator::make($request->all() , [
            'phone' => ['required'],
            'code'  => ['required','min:4','max:4']
        ]);
        if ($validator->fails()){
            return response()->json([
                'data' =>$validator->errors()
            ],400);
        }

        $phone = $request->input('phone');
        $code = $request->input('code');
        $action = Cache::get('action'.$code);

        if(Cache::get($phone)!=$code)
            return response()->json (['message' => 'Incorrect code'], 400);
        if($action === 'register')
            $token = base64_encode('1'.$phone);
        else
            $token = base64_encode('2'.$phone);

        Cache::forever($token, $phone);
        Cache::forget($phone);
        Cache::forget('action'.$code);

        return response()->json([
            'message' => 'Verification successful',
            'token' => $token
        ],200);
    }
    public function register(Request $request)
    {
        $validator = Validator::make($request->all() , [
            'token' => 'required',
            'password' => ['required','min:8', 'confirmed'],
            'first_name' => 'required',
            'last_name' => 'required',
            'location.country' => ['required' , 'string'],
            'location.city' =>  ['required' , 'string'],
            'image ' =>'image|mimes:png,jpg'
        ]);
        if ($validator->fails()){
            return response()->json([
                'message' => "Registration failed",
                'data' =>$validator->errors()
            ],401);
        }
        $token = $request->input('token');
        $decodedToken = base64_decode($token);
        if($decodedToken[0]!=='1'||(!Cache::get($token)))
            return response()->json (['message' => 'Incorrect token'], 400);

        $phone = substr($decodedToken, 1);
        $password = $request->input('password');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $location = $request->input('location');
        $path=null;
        if($request->file('image'))
            $path = $request->file('image')->store('images/profile-images', 'public');

        $user=User::create([
            'phone'=>$phone,
            'password'=>Hash::make($password),
            'phone_verified_at'=>Carbon::now(),
            'first_name'=>$first_name,
            'last_name'=>$last_name,
            'location'=>json_encode($location),
            'image' => $path
        ]);
        Cache::forget($token);

        $accessToken = $user->createToken('auth_token')->plainTextToken;

        ProcessLocation::dispatch($user->id, $location);

        return response()->json([
            'access_token' => $accessToken,
        ] ,200);
    }
    public function resetPassword(Request $request){
        $validator = Validator::make($request->all() , [
            'token' => 'required',
            'new_password' => ['required','min:8', 'confirmed'],
        ]);
        if ($validator->fails()){
            return response()->json([
                'message' => "Password reset failed",
                'data' =>$validator->errors()
            ],401);
        }
        $token = $request->input('token');
        $decodedToken = base64_decode($token);
        if($decodedToken[0]!=='2'||(!Cache::get($token)))
            return response()->json (['message' => 'Incorrect token'], 400);
        $phone = substr($decodedToken, 1);
        $newPassword=$request->input('new_password');
        $user = User::where('phone', $phone)->first();
        if (!$user)
            return response()->json(['message' => 'There is no user with this number'], 404);
        $user->password = Hash::make($newPassword);
        $user->save();
        Cache::forget($token);

        $accessToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
        ] ,200);
    }

    public function changePassword(Request $request){
        $validator = Validator::make($request->all() , [
            'old_password' => ['required','min:8'],
            'password' => ['required','min:8', 'confirmed'],
        ]);

        if ($validator->fails()){
            return response()->json([
                'message' => "validation failed",
                'data' =>$validator->errors()
            ],401);
        }

        $user = \Auth::user();

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'old password is incorrect',
            ], 403);
        }

        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'new password cannot be the same as the old password',
            ], 400);
        }

        try {
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'message' => 'Password changed successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
