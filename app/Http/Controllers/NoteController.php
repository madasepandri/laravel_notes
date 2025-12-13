<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot access notes.');
        }

        $query = Note::query();

        if ($user->role === 'admin') {
            // Admin can see all notes from their organization
            $query->where('organization_id', $user->organization_id);
        } else {
            // Regular user can only see their own notes
            $query->where('user_id', $user->id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $notes = $query->latest()->paginate(10);
        
        $categories = collect();
        if ($user->organization_id) {
            $categories = $user->organization->categories()->pluck('name', 'id');
        }

        return view('notes.index', compact('notes', 'search', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot access note creation.');
        }
        $categories = collect();
        if ($user->organization_id) {
            $categories = $user->organization->categories()->pluck('name', 'id');
        }
        return view('notes.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'user') {
            abort(403, 'Only regular users can create notes.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        $user->notes()->create([
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'organization_id' => $user->organization_id,
        ]);

        return redirect()->route('notes.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note)
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot view notes.');
        } elseif ($user->role === 'admin') {
            // Admin can view notes within their organization
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            // Regular user can only view their own notes
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }
        return view('notes.show', compact('note'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Note $note)
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot edit notes.');
        } elseif ($user->role === 'admin') {
            // Admin can edit notes within their organization
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            // Regular user can only edit their own notes
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        $categories = collect();
        if ($user->organization_id) {
            $categories = $user->organization->categories()->pluck('name', 'id');
        }

        return view('notes.edit', compact('note', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note)
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot update notes.');
        } elseif ($user->role === 'admin') {
            // Admin can update notes within their organization
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            // Regular user can only update their own notes
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        $note->update($request->only('title', 'content', 'category_id'));

        return redirect()->route('notes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot delete notes.');
        } elseif ($user->role === 'admin') {
            // Admin can delete notes within their organization
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            // Regular user can only delete their own notes
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        $note->delete();

        return redirect()->route('notes.index');
    }
}
