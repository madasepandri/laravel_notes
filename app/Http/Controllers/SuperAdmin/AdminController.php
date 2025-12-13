<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $admins = User::where('role', 'admin')->paginate(10);
        return view('superadmin.admins.index', compact('admins'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('superadmin.admins.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255', // New validation rule
        ]);

        // Find or create the organization
        $organization = Organization::firstOrCreate([
            'name' => $request->organization_name,
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'organization_id' => $organization->id, // Use the ID of the found/created organization
        ]);

        return redirect()->route('superadmin.admins.index')->with('success', 'Admin created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Not used for this resource
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $admin)
    {
        $organizations = Organization::all();
        return view('superadmin.admins.edit', compact('admin', 'organizations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $admin)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $admin->id,
            'password' => 'nullable|string|min:8|confirmed',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $admin->update([
            'name' => $request->name,
            'email' => $request->email,
            'organization_id' => $request->organization_id,
            'password' => $request->password ? Hash::make($request->password) : $admin->password,
        ]);

        return redirect()->route('superadmin.admins.index')->with('success', 'Admin updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $admin)
    {
        $admin->delete();
        return redirect()->route('superadmin.admins.index')->with('success', 'Admin deleted successfully.');
    }
}
