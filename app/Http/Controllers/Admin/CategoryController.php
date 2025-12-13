<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organizationId = Auth::user()->organization_id;
        $categories = Category::where('organization_id', $organizationId)->paginate(10);
        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $organizationId = Auth::user()->organization_id;

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,NULL,id,organization_id,' . $organizationId,
        ]);

        Category::create([
            'name' => $request->name,
            'organization_id' => $organizationId,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        if ($category->organization_id !== Auth::user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }
        return view('admin.categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        if ($category->organization_id !== Auth::user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $organizationId = Auth::user()->organization_id;

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id . ',id,organization_id,' . $organizationId,
        ]);

        $category->update([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        if ($category->organization_id !== Auth::user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }
}
