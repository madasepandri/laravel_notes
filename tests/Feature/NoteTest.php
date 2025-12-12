<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_see_notes()
    {
        $response = $this->get(route('notes.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_an_authenticated_user_can_see_their_own_notes()
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('notes.index'));

        $response->assertStatus(200);
        $response->assertSee($note->title);
    }

    public function test_an_authenticated_user_cannot_see_other_users_notes()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get(route('notes.index'));

        $response->assertStatus(200);
        $response->assertDontSee($note->title);
    }

    public function test_an_authenticated_user_can_create_a_note()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Test Note',
            'content' => 'This is a test note.',
        ]);

        $response->assertRedirect(route('notes.index'));
        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'title' => 'Test Note',
            'content' => 'This is a test note.',
        ]);
    }

    public function test_an_authenticated_user_can_update_their_own_note()
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch(route('notes.update', $note), [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);

        $response->assertRedirect(route('notes.index'));
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);
    }

    public function test_an_authenticated_user_cannot_update_other_users_notes()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->patch(route('notes.update', $note), [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);

        $response->assertStatus(403);
    }

    public function test_an_authenticated_user_can_delete_their_own_note()
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('notes.destroy', $note));

        $response->assertRedirect(route('notes.index'));
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_an_authenticated_user_cannot_delete_other_users_notes()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->delete(route('notes.destroy', $note));

        $response->assertStatus(403);
        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }
}
