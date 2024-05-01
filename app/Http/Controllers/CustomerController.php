<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = User::where('id', '<>' , auth()->user()->id)->get();
        return response()->json([
            'customers' => $customers,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
        */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
            'is_admin' => $request->is_admin,
        ]);
        return response()->json(200);
    }

    /**
     * Display the specified resource.
     */
    public function show($userId)
    {
        $user = User::find($userId);
        return response()->json([
            'user' => $user
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $userId)
    {
        $user = User::find($userId);
        $user->update([
            'email' => $request->email,
            'name' => $request->name,
            'password' => Hash::make($request->password),
        ]);
        return response()->json(200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $credentials = Credential::where('user_id', $user->id)->first();
        $user->delete();
        $credentials->delete();
    }
}
