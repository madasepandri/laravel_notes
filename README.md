## Membangun REST API dengan Laravel Sanctum

Setelah aplikasi web selesai, sekarang kita akan membangun REST API untuk aplikasi mobile atau integrasi dengan sistem lain. Kita akan menggunakan **Laravel Sanctum** untuk token-based authentication.

### Apa itu Laravel Sanctum?

Laravel Sanctum adalah package official Laravel untuk API authentication yang simple dan lightweight. Sanctum menyediakan:
- **Token-based authentication** untuk SPA dan mobile apps
- **Cookie-based authentication** untuk SPA same-domain
- API token management yang mudah

### Step 1: Install Laravel Sanctum

```bash
# Install Sanctum
composer require laravel/sanctum
```

**Penjelasan**: Sanctum sudah included di Laravel 11+, tapi untuk versi sebelumnya perlu install manual.

### Step 2: Publish Sanctum Config & Migration

```bash
# Publish config (optional)
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Jalankan migration untuk tabel personal_access_tokens
php artisan migrate
```

**Penjelasan**: Migration membuat tabel `personal_access_tokens` untuk menyimpan API tokens.

### Step 3: Tambahkan HasApiTokens Trait ke User Model

Edit `app/Models/User.php`, tambahkan trait `HasApiTokens`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;  // Import trait

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;  // Tambahkan HasApiTokens

    // ... rest of the code
}
```

**Penjelasan**: Trait `HasApiTokens` memberikan method untuk create, delete, dan manage API tokens.

### Step 4: Configure API Routes

Laravel sudah menyediakan `routes/api.php` untuk API routes. Edit `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

// API Versioning - v1
Route::prefix('v1')->group(function () {

    // Public endpoints (no auth required)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected endpoints (auth required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // Notes CRUD
        Route::apiResource('notes', NoteController::class);
    });
});
```

**Penjelasan**:
- **API Versioning**: Menggunakan prefix `/v1` untuk versioning API
- **Public Routes**: Register dan login tidak butuh authentication
- **Protected Routes**: Menggunakan middleware `auth:sanctum`
- **apiResource**: Generate routes untuk CRUD (index, store, show, update, destroy) tanpa create & edit

### Step 5: Buat API Auth Controller

```bash
# Buat folder Api dan AuthController
mkdir -p app/Http/Controllers/Api
php artisan make:controller Api/AuthController
```

Edit `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register user baru
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user (hapus current token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
```

**Penjelasan**:
- **register()**: Buat user baru, generate token, return token
- **login()**: Validasi credentials, generate token, return token
- **logout()**: Hapus current access token dari database
- **createToken('mobile-app')**: Buat token dengan nama "mobile-app"
- **plainTextToken**: Get token dalam format plain text (hanya available saat create)

### Step 6: Buat API Resource untuk Note

API Resource adalah cara elegant untuk transform model ke JSON response.

```bash
php artisan make:resource NoteResource
```

Edit `app/Http/Resources/NoteResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'category_id' => $this->category_id,
            'organization_id' => $this->organization_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'organization' => $this->whenLoaded('organization', function () {
                return [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

**Penjelasan**:
- **toArray()**: Define struktur JSON response
- **whenLoaded()**: Hanya include relasi jika sudah di-eager load
- Memberikan kontrol penuh atas data yang di-expose ke API

### Step 7: Buat Note Policy untuk Authorization

Policy adalah cara Laravel untuk organize authorization logic.

```bash
php artisan make:policy NotePolicy --model=Note
```

Edit `app/Policies/NotePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    /**
     * Determine if the user can view the note.
     */
    public function view(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine if the user can create notes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the note.
     */
    public function update(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine if the user can delete the note.
     */
    public function delete(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }
}
```

**Penjelasan**:
- Policy methods return `true` jika user authorized, `false` jika tidak
- User hanya bisa view/update/delete notes miliknya sendiri
- Semua user bisa create notes

### Step 8: Register Policy

Edit `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\Note;
use App\Policies\NotePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Note::class, NotePolicy::class);
    }
}
```

**Penjelasan**: Register policy agar Laravel tahu policy mana yang digunakan untuk model Note.

### Step 9: Buat API Note Controller

```bash
php artisan make:controller Api/NoteController --api
```

Edit `app/Http/Controllers/Api/NoteController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    /**
     * Display a listing of notes
     */
    public function index(Request $request)
    {
        $notes = Note::where('user_id', $request->user()->id)
            ->with(['category', 'organization'])
            ->latest()
            ->paginate(15);

        return NoteResource::collection($notes);
    }

    /**
     * Store a newly created note
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $note = Note::create([
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'organization_id' => $request->organization_id,
            'user_id' => $request->user()->id,
        ]);

        return new NoteResource($note);
    }

    /**
     * Display the specified note
     */
    public function show(Request $request, Note $note)
    {
        Gate::authorize('view', $note);

        return new NoteResource($note->load(['category', 'organization']));
    }

    /**
     * Update the specified note
     */
    public function update(Request $request, Note $note)
    {
        Gate::authorize('update', $note);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $note->update($request->only([
            'title',
            'content',
            'category_id',
            'organization_id',
        ]));

        return new NoteResource($note);
    }

    /**
     * Remove the specified note
     */
    public function destroy(Request $request, Note $note)
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return response()->json([
            'message' => 'Note deleted successfully',
        ]);
    }
}
```

**Penjelasan**:
- **index()**: List notes milik user dengan pagination (15 items/page)
- **store()**: Buat note baru untuk user yang login
- **show()**: Tampilkan detail note (dengan authorization check)
- **update()**: Update note (dengan authorization check)
- **destroy()**: Hapus note (dengan authorization check)
- **Gate::authorize()**: Check permission menggunakan Policy
- **NoteResource**: Transform response ke format JSON yang konsisten
- **with()**: Eager loading untuk relasi category dan organization

### Step 10: Testing API dengan Postman/Thunder Client

#### 1. Register User

```http
POST http://localhost:8000/api/v1/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response:**
```json
{
    "message": "User registered successfully",
    "user": {
        "name": "John Doe",
        "email": "john@example.com",
        "updated_at": "2026-01-12T10:30:00.000000Z",
        "created_at": "2026-01-12T10:30:00.000000Z",
        "id": 1
    },
    "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890"
}
```

**Simpan token** untuk digunakan di request berikutnya!

#### 2. Login

```http
POST http://localhost:8000/api/v1/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "message": "Login successful",
    "user": {...},
    "token": "2|aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890"
}
```

#### 3. Get Current User

```http
GET http://localhost:8000/api/v1/user
Authorization: Bearer {token}
```

**Response:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "organization_id": null,
    "role": null,
    "email_verified_at": null,
    "created_at": "2026-01-12T10:30:00.000000Z",
    "updated_at": "2026-01-12T10:30:00.000000Z"
}
```

#### 4. Create Note

```http
POST http://localhost:8000/api/v1/notes
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "My First Note",
    "content": "This is the content of my note",
    "category_id": 1
}
```

**Response:**
```json
{
    "data": {
        "id": 1,
        "title": "My First Note",
        "content": "This is the content of my note",
        "category_id": 1,
        "organization_id": null,
        "category": null,
        "organization": null,
        "created_at": "2026-01-12T10:35:00.000000Z",
        "updated_at": "2026-01-12T10:35:00.000000Z"
    }
}
```

#### 5. List Notes

```http
GET http://localhost:8000/api/v1/notes
Authorization: Bearer {token}
```

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "title": "My First Note",
            "content": "This is the content",
            ...
        }
    ],
    "links": {
        "first": "http://localhost:8000/api/v1/notes?page=1",
        "last": "http://localhost:8000/api/v1/notes?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 1,
        "total": 1
    }
}
```

#### 6. Show Note

```http
GET http://localhost:8000/api/v1/notes/1
Authorization: Bearer {token}
```

#### 7. Update Note

```http
PUT http://localhost:8000/api/v1/notes/1
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Updated Title",
    "content": "Updated content"
}
```

#### 8. Delete Note

```http
DELETE http://localhost:8000/api/v1/notes/1
Authorization: Bearer {token}
```

**Response:**
```json
{
    "message": "Note deleted successfully"
}
```

#### 9. Logout

```http
POST http://localhost:8000/api/v1/logout
Authorization: Bearer {token}
```

**Response:**
```json
{
    "message": "Logged out successfully"
}
```

### Step 11: Error Handling untuk API

Laravel secara otomatis mengembalikan error dalam format JSON untuk API requests. Berikut contoh error responses:

#### Validation Error (422)

```json
{
    "message": "The title field is required. (and 1 more error)",
    "errors": {
        "title": ["The title field is required."],
        "content": ["The content field must be a string."]
    }
}
```

#### Unauthorized (401)

```json
{
    "message": "Unauthenticated."
}
```

#### Forbidden (403)

```json
{
    "message": "This action is unauthorized."
}
```

#### Not Found (404)

```json
{
    "message": "No query results for model [App\\Models\\Note] 999"
}
```

### Step 12: Custom Error Handler (Optional)

Untuk error response yang lebih user-friendly, edit `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ... middleware config
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'message' => 'Resource not found',
                    ], 404);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'message' => 'Endpoint not found',
                    ], 404);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'message' => 'Unauthenticated',
                    ], 401);
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'message' => 'Unauthorized',
                    ], 403);
                }
            }

            return null; // Let default handler handle it
        });
    })->create();
```

**Penjelasan**: Custom error handler untuk API routes memberikan response JSON yang konsisten.

### Step 13: Rate Limiting untuk API

Protect API dari abuse dengan rate limiting. Edit `bootstrap/app.php`:

```php
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => RoleMiddleware::class,
        'org.active' => EnsureOrganizationIsActive::class,
    ]);

    // API Rate Limiting
    $middleware->throttleApi();

    // Custom rate limit untuk API tertentu
    $middleware->throttle([
        'api' => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by(fn (Request $request) => $request->user()?->id ?: $request->ip()),
    ]);
})
```

**Penjelasan**:
- Default: 60 requests per minute per user/IP
- Bisa dikustomisasi per route group
- Response 429 Too Many Requests jika limit exceeded

### Step 14: API Documentation dengan Scribe (Optional)

Install Scribe untuk auto-generate API documentation:

```bash
composer require --dev knuckleswtf/scribe
```

Generate dokumentasi:

```bash
# Publish config
php artisan vendor:publish --tag=scribe-config

# Generate documentation
php artisan scribe:generate
```

Edit `config/scribe.php` untuk customize:

```php
'title' => 'Laravel Notes API Documentation',
'description' => 'API documentation for Laravel Notes application',
'base_url' => env('APP_URL', 'http://localhost:8000'),
'routes' => [
    [
        'match' => [
            'prefixes' => ['api/*'],
        ],
    ],
],
```

Akses dokumentasi di `http://localhost:8000/docs`.

### Step 15: Testing API dengan PHPUnit

Buat feature test untuk API:

```bash
php artisan make:test Api/AuthTest
php artisan make:test Api/NoteTest
```

Edit `tests/Feature/Api/AuthTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }
}
```

Edit `tests/Feature/Api/NoteTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $token];
    }

    public function test_user_can_list_their_notes(): void
    {
        [$user, $token] = $this->authenticatedUser();

        Note::factory()->count(3)->create(['user_id' => $user->id]);
        Note::factory()->count(2)->create(); // Notes dari user lain

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notes');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_note(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/notes', [
                'title' => 'Test Note',
                'content' => 'Test Content',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'content'],
            ]);

        $this->assertDatabaseHas('notes', [
            'title' => 'Test Note',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_view_others_note(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $otherNote = Note::factory()->create(); // Note dari user lain

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notes/' . $otherNote->id);

        $response->assertStatus(403);
    }

    public function test_user_can_update_their_note(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/notes/' . $note->id, [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => ['title' => 'Updated Title'],
            ]);
    }

    public function test_user_can_delete_their_note(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/notes/' . $note->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }
}
```

Jalankan tests:

```bash
# Jalankan semua tests
php artisan test

# Jalankan specific test file
php artisan test --filter=AuthTest

# Jalankan dengan coverage
php artisan test --coverage
```

### Step 16: API Best Practices

#### 1. Versioning

Selalu gunakan versioning untuk API:
- ✅ `/api/v1/notes`
- ❌ `/api/notes`

Ini memudahkan untuk membuat breaking changes di versi baru tanpa merusak client yang existing.

#### 2. Consistent Response Format

Gunakan API Resources untuk konsistensi:
```json
{
    "data": {},           // Single resource
    "data": [],           // Collection
    "meta": {},           // Metadata (pagination, etc)
    "links": {}           // Pagination links
}
```

#### 3. HTTP Status Codes

Gunakan status code yang sesuai:
- `200 OK` - Success (GET, PUT, PATCH)
- `201 Created` - Resource created (POST)
- `204 No Content` - Success but no content (DELETE)
- `400 Bad Request` - Invalid request
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Not authorized
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation error
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

#### 4. Pagination

Selalu paginate collections:
```php
$notes = Note::paginate(15); // Default 15 items
return NoteResource::collection($notes);
```

#### 5. Eager Loading

Prevent N+1 queries dengan eager loading:
```php
$notes = Note::with(['category', 'organization'])->get();
```

#### 6. Filtering & Sorting

Tambahkan query parameters untuk filtering:
```php
public function index(Request $request)
{
    $query = Note::where('user_id', $request->user()->id);

    if ($request->has('category_id')) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->has('search')) {
        $query->where('title', 'like', '%' . $request->search . '%');
    }

    if ($request->has('sort')) {
        $query->orderBy($request->sort, $request->get('direction', 'asc'));
    }

    return NoteResource::collection($query->paginate(15));
}
```

#### 7. Security

- ✅ Selalu validate input
- ✅ Gunakan authorization (Policy/Gate)
- ✅ Enable rate limiting
- ✅ Hash passwords
- ✅ Use HTTPS di production
- ✅ Validate signature untuk webhooks
- ❌ Jangan expose sensitive data di response
- ❌ Jangan trust user input

### Step 17: Consume API dari Mobile App

Contoh menggunakan API dari Flutter:

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class ApiService {
  static const String baseUrl = 'http://localhost:8000/api/v1';
  static String? token;

  // Register
  static Future<Map<String, dynamic>> register(
    String name,
    String email,
    String password,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/register'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      }),
    );

    if (response.statusCode == 201) {
      final data = jsonDecode(response.body);
      token = data['token'];
      return data;
    } else {
      throw Exception('Failed to register');
    }
  }

  // Login
  static Future<Map<String, dynamic>> login(
    String email,
    String password,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      token = data['token'];
      return data;
    } else {
      throw Exception('Failed to login');
    }
  }

  // Get Notes
  static Future<List<dynamic>> getNotes() async {
    final response = await http.get(
      Uri.parse('$baseUrl/notes'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['data'];
    } else {
      throw Exception('Failed to load notes');
    }
  }

  // Create Note
  static Future<Map<String, dynamic>> createNote(
    String title,
    String content,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/notes'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'title': title,
        'content': content,
      }),
    );

    if (response.statusCode == 201) {
      return jsonDecode(response.body)['data'];
    } else {
      throw Exception('Failed to create note');
    }
  }
}
```

### Ringkasan API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/register` | ❌ | Register user baru |
| POST | `/api/v1/login` | ❌ | Login user |
| POST | `/api/v1/logout` | ✅ | Logout user |
| GET | `/api/v1/user` | ✅ | Get current user |
| GET | `/api/v1/notes` | ✅ | List notes (paginated) |
| POST | `/api/v1/notes` | ✅ | Create note |
| GET | `/api/v1/notes/{id}` | ✅ | Show note detail |
| PUT/PATCH | `/api/v1/notes/{id}` | ✅ | Update note |
| DELETE | `/api/v1/notes/{id}` | ✅ | Delete note |

### Kesimpulan API

Sekarang aplikasi Laravel Notes sudah memiliki REST API yang lengkap dengan:

✅ **Token Authentication** dengan Laravel Sanctum
✅ **API Resources** untuk transform response
✅ **Authorization** dengan Policy
✅ **Validation** untuk semua input
✅ **Pagination** untuk list endpoints
✅ **Error Handling** dengan response JSON konsisten
✅ **Rate Limiting** untuk protect dari abuse
✅ **API Versioning** untuk maintainability
✅ **Testing** dengan PHPUnit

API ini siap digunakan untuk:
- Mobile apps (Flutter, React Native, Swift, Kotlin)
- Frontend SPA (Vue.js, React, Angular)
- Integrasi dengan sistem lain
- Third-party integrations

---

## Kesimpulan

Selamat! Anda telah berhasil membangun aplikasi Laravel lengkap dengan:

✅ **Multi-Tenancy**: Data terisolasi per organisasi
✅ **RBAC**: Super Admin, Admin, dan User dengan permission berbeda
✅ **Payment Gateway**: Integrasi Midtrans untuk aktivasi organisasi
✅ **CRUD Operations**: Notes, Users, Categories dengan authorization
✅ **REST API**: API lengkap dengan Laravel Sanctum untuk mobile apps
✅ **Security**: Data scoping, middleware, validation, signature verification

### Fitur-fitur Utama:

1. **Super Admin** dapat mengelola semua admin organisasi
2. **Admin** dapat aktivasi organisasi dengan pembayaran, mengelola users, categories, dan melihat semua notes
3. **User** dapat membuat dan mengelola notes miliknya sendiri
4. **Multi-tenant** memastikan data organisasi terisolasi
5. **Payment** terintegrasi dengan Midtrans Snap
6. **REST API** dengan token authentication untuk mobile apps dan integrasi sistem lain

### Best Practices yang Diterapkan:

- ✅ Migration yang terstruktur dengan foreign key dan cascade delete
- ✅ Model relationships yang jelas
- ✅ Middleware untuk authorization (web & API)
- ✅ Data scoping untuk multi-tenancy
- ✅ Service layer untuk business logic (MidtransSnapService)
- ✅ API Resources untuk consistent response format
- ✅ Policy untuk authorization logic
- ✅ Validation yang ketat (web & API)
- ✅ Error handling dan logging
- ✅ Rate limiting untuk API protection
- ✅ API versioning untuk maintainability
- ✅ Security: signature validation, CSRF protection, hash comparison, token authentication

### Teknologi yang Digunakan:

**Backend:**
- Laravel 12
- PHP 8.2+
- SQLite/MySQL
- Laravel Breeze (Web Auth)
- Laravel Sanctum (API Auth)
- Midtrans Snap

**Frontend:**
- Blade Templates
- Tailwind CSS
- Alpine.js (via Breeze)

**Testing:**
- PHPUnit
- Feature Tests
- API Tests

### Architecture Patterns:

- **MVC Pattern**: Model-View-Controller
- **Repository Pattern**: (dapat diterapkan untuk data access layer)
- **Service Pattern**: Business logic di service classes (MidtransSnapService)
- **Policy Pattern**: Authorization logic
- **Resource Pattern**: API response transformation
- **Middleware Pattern**: Request filtering dan authorization

### API Endpoints Summary:

| Category | Endpoints | Count |
|----------|-----------|-------|
| Authentication | register, login, logout, user | 4 |
| Notes | index, store, show, update, destroy | 5 |
| **Total** | | **9** |

### Next Steps (Opsional):

1. **Email Notifications**: Kirim email saat organisasi aktif dengan Laravel Notifications
2. **Advanced Permissions**: Granular permissions dengan Spatie Permission package
3. **File Upload**: Upload attachment untuk notes dengan Laravel Storage
4. **Activity Log**: Track semua aktivitas user dengan Spatie Activity Log
5. **Better UI**: Gunakan Vue.js atau React dengan Inertia.js untuk SPA
6. **Real-time Features**: WebSocket dengan Laravel Reverb untuk real-time notifications
7. **Advanced API Features**:
   - GraphQL dengan Lighthouse
   - Webhook subscriptions
   - API key management
8. **Performance Optimization**:
   - Redis caching
   - Query optimization
   - Eager loading
   - Database indexing
9. **Monitoring & Analytics**:
   - Laravel Telescope untuk debugging
   - Laravel Horizon untuk queue monitoring
   - Sentry untuk error tracking
10. **DevOps**:
    - Docker containerization
    - CI/CD dengan GitHub Actions
    - Automated testing pipeline

### Struktur Aplikasi Final:

```
laravel_notes/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   └── NoteController.php
│   │   │   ├── Auth/
│   │   │   ├── Admin/
│   │   │   ├── SuperAdmin/
│   │   │   ├── BillingController.php
│   │   │   ├── MidtransWebhookController.php
│   │   │   └── NoteController.php
│   │   ├── Middleware/
│   │   │   ├── RoleMiddleware.php
│   │   │   └── EnsureOrganizationIsActive.php
│   │   └── Resources/
│   │       └── NoteResource.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Organization.php
│   │   ├── Category.php
│   │   ├── Note.php
│   │   └── Payment.php
│   ├── Policies/
│   │   └── NotePolicy.php
│   └── Services/
│       └── MidtransSnapService.php
├── database/
│   └── migrations/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── auth.php
├── resources/
│   └── views/
├── tests/
│   └── Feature/
│       └── Api/
└── config/
    ├── midtrans.php
    └── billing.php
```

### Resources untuk Belajar Lebih Lanjut:

**Official Documentation:**
- Laravel Documentation: https://laravel.com/docs
- Laravel Sanctum: https://laravel.com/docs/sanctum
- Midtrans API: https://docs.midtrans.com

**Tutorials & Courses:**
- Laracasts: https://laracasts.com
- Laravel Daily: https://laraveldaily.com
- Laravel News: https://laravel-news.com

**Community:**
- Laravel Reddit: https://reddit.com/r/laravel
- Laravel Discord: https://discord.gg/laravel
- Larachat: https://larachat.co

---

**Selamat belajar dan happy coding! 🚀**
