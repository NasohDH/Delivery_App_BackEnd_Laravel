<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::all();

        if ($ads->isEmpty()) {
            return response()->json([
                'message' => 'No Ad available'
            ], 404);
        }

        return response()->json([
            'data' => $ads,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'image' => 'required|image|max:2048',
            'store_id' => 'required|exists:stores,id'
        ]);

        if ($validator->fails()){
            return response()->json([
                'message' => "failed to create the Ad",
                'data' =>$validator->errors()
            ],401);
        }

        $path = $request->file('image')->store('images/ads', 'public');
        $path = 'storage/' . str_replace("public/", "", $path);

        $ad = Ad::create([
            'image' => $path,
            'store_id' => $request->store_id
        ]);

        return response()->json([
            'message' => 'Ad created successfully.',
            'ad' => $ad,
        ], 201);
    }

    public function destroy($id)
    {
        $ad = Ad::find($id);

        if (!$ad) {
            return response()->json([
                'message' => 'Ad not found.',
            ], 404);
        }

        if (Storage::disk('public')->exists($ad->image)) {
            Storage::disk('public')->delete($ad->image);
        }

        $ad->delete();

        return response()->json([
            'message' => 'Ad deleted successfully.',
        ], 200);
    }
}
