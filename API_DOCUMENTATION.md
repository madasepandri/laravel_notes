# Notes API Documentation

API untuk aplikasi Notes yang dapat diakses dari aplikasi mobile.

## Base URL
```
http://localhost:8000/api/v1
```

## Authentication
API ini menggunakan Laravel Sanctum untuk autentikasi. Setelah login atau register, Anda akan menerima token yang harus disertakan dalam header setiap request ke endpoint yang dilindungi.

### Headers untuk Request yang Memerlukan Autentikasi
```
Authorization: Bearer {your-token}
Accept: application/json
Content-Type: application/json
```

---

## Endpoints

### 1. Register User Baru
**POST** `/register`

Membuat akun user baru dan mengembalikan token autentikasi.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2026-01-12T03:47:00.000000Z",
    "updated_at": "2026-01-12T03:47:00.000000Z"
  },
  "token": "1|abcdefghijklmnopqrstuvwxyz..."
}
```

**Validasi:**
- `name`: required, string, max 255 karakter
- `email`: required, email valid, max 255 karakter, harus unique
- `password`: required, string, minimal 8 karakter, harus sama dengan password_confirmation

---

### 2. Login
**POST** `/login`

Login dengan email dan password, mengembalikan token autentikasi.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2026-01-12T03:47:00.000000Z",
    "updated_at": "2026-01-12T03:47:00.000000Z"
  },
  "token": "2|abcdefghijklmnopqrstuvwxyz..."
}
```

**Error Response (422):**
```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": [
      "The provided credentials are incorrect."
    ]
  }
}
```

---

### 3. Logout
**POST** `/logout`

🔒 **Memerlukan autentikasi**

Logout dan menghapus token saat ini.

**Headers:**
```
Authorization: Bearer {your-token}
```

**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

### 4. Get Current User
**GET** `/user`

🔒 **Memerlukan autentikasi**

Mendapatkan data user yang sedang login.

**Response (200):**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "email_verified_at": null,
  "created_at": "2026-01-12T03:47:00.000000Z",
  "updated_at": "2026-01-12T03:47:00.000000Z"
}
```

---

### 5. Get All Notes (with Pagination)
**GET** `/notes`

🔒 **Memerlukan autentikasi**

Mendapatkan semua notes milik user yang sedang login, diurutkan dari yang terbaru.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "title": "My First Note",
      "content": "This is the content of my first note",
      "created_at": "2026-01-12T03:50:00.000000Z",
      "updated_at": "2026-01-12T03:50:00.000000Z"
    },
    {
      "id": 2,
      "title": "Shopping List",
      "content": "- Milk\n- Bread\n- Eggs",
      "created_at": "2026-01-12T04:00:00.000000Z",
      "updated_at": "2026-01-12T04:00:00.000000Z"
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
    "to": 2,
    "total": 2
  }
}
```

---

### 6. Create New Note
**POST** `/notes`

🔒 **Memerlukan autentikasi**

Membuat note baru.

**Request Body:**
```json
{
  "title": "My New Note",
  "content": "This is the content of my new note"
}
```

**Response (200):**
```json
{
  "data": {
    "id": 3,
    "title": "My New Note",
    "content": "This is the content of my new note",
    "created_at": "2026-01-12T05:00:00.000000Z",
    "updated_at": "2026-01-12T05:00:00.000000Z"
  }
}
```

**Validasi:**
- `title`: required, string, max 255 karakter
- `content`: optional, string

---

### 7. Get Single Note
**GET** `/notes/{id}`

🔒 **Memerlukan autentikasi**

Mendapatkan detail note berdasarkan ID. User hanya bisa melihat note miliknya sendiri.

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "title": "My First Note",
    "content": "This is the content of my first note",
    "created_at": "2026-01-12T03:50:00.000000Z",
    "updated_at": "2026-01-12T03:50:00.000000Z"
  }
}
```

**Error Response (403):**
```json
{
  "message": "This action is unauthorized."
}
```

---

### 8. Update Note
**PUT/PATCH** `/notes/{id}`

🔒 **Memerlukan autentikasi**

Mengupdate note. User hanya bisa mengupdate note miliknya sendiri.

**Request Body:**
```json
{
  "title": "Updated Title",
  "content": "Updated content"
}
```

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "title": "Updated Title",
    "content": "Updated content",
    "created_at": "2026-01-12T03:50:00.000000Z",
    "updated_at": "2026-01-12T05:30:00.000000Z"
  }
}
```

**Validasi:**
- `title`: sometimes required, string, max 255 karakter
- `content`: optional, string

**Error Response (403):**
```json
{
  "message": "This action is unauthorized."
}
```

---

### 9. Delete Note
**DELETE** `/notes/{id}`

🔒 **Memerlukan autentikasi**

Menghapus note. User hanya bisa menghapus note miliknya sendiri.

**Response (200):**
```json
{
  "message": "Note deleted successfully"
}
```

**Error Response (403):**
```json
{
  "message": "This action is unauthorized."
}
```

---

## Error Responses

### 401 Unauthorized
Ketika token tidak valid atau tidak disertakan:
```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found
Ketika resource tidak ditemukan:
```json
{
  "message": "No query results for model [App\\Models\\Note] {id}"
}
```

### 422 Validation Error
Ketika validasi gagal:
```json
{
  "message": "The title field is required.",
  "errors": {
    "title": [
      "The title field is required."
    ]
  }
}
```

---

## Contoh Penggunaan dengan cURL

### Register
```bash
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### Login
```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### Create Note
```bash
curl -X POST http://localhost:8000/api/v1/notes \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "title": "My Note",
    "content": "Note content here"
  }'
```

### Get All Notes
```bash
curl -X GET http://localhost:8000/api/v1/notes \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Update Note
```bash
curl -X PUT http://localhost:8000/api/v1/notes/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "title": "Updated Title",
    "content": "Updated content"
  }'
```

### Delete Note
```bash
curl -X DELETE http://localhost:8000/api/v1/notes/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Menjalankan Server

Untuk menjalankan server development:

```bash
php artisan serve
```

Server akan berjalan di `http://localhost:8000`

---

## Database

Aplikasi menggunakan SQLite sebagai database default. File database terletak di `database/database.sqlite`.

Untuk menjalankan migration:
```bash
php artisan migrate
```

Untuk reset database dan jalankan ulang migration:
```bash
php artisan migrate:fresh
```

---

## Testing dengan Postman

1. Import collection atau buat request manual
2. Untuk endpoint yang memerlukan autentikasi:
   - Masuk ke tab "Authorization"
   - Pilih "Bearer Token"
   - Paste token yang didapat dari login/register
3. Pastikan header `Accept: application/json` selalu disertakan

---

## Notes

- Semua response menggunakan format JSON
- Token Sanctum tidak memiliki expiry secara default
- User hanya bisa mengakses notes miliknya sendiri (authorization otomatis)
- Pagination default: 15 items per page
