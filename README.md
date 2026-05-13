# Medical Appointment System - Backend API

## Overview

Laravel 12 PHP backend for a medical appointment system with role-based access control (patients, doctors, admins) and AI-powered chat assistant.

| Property | Value |
|----------|-------|
| Framework | Laravel 12 |
| PHP Version | 8.2+ |
| Database | SQLite (default), MySQL, PostgreSQL |
| Auth | Laravel Sanctum |
| API Format | JSON REST |

---

## Quick Start

### Docker (Recommended)

```bash
cd backend
docker-compose up -d
```

API available at: **http://localhost:8000**

### Manual Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

---

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/           # API Controllers
│   │   │       ├── AuthController.php
│   │   │       ├── AppointmentController.php
│   │   │       ├── DoctorController.php
│   │   │       ├── AdminController.php
│   │   │       ├── PatientController.php
│   │   │       ├── MedicalRecordController.php
│   │   │       └── ChatController.php          # AI Chat Assistant
│   │   └── Middleware/
│   │       └── EnsureRole.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Appointment.php
│   │   ├── MedicalRecord.php
│   │   ├── DoctorReport.php
│   │   ├── Prescription.php
│   │   ├── PasswordResetOtp.php
│   │   ├── DoctorPatientStatus.php
│   │   ├── Conversation.php                   # Chat conversations
│   │   ├── Message.php                        # Chat messages with RAG
│   │   ├── AuditLog.php                       # AI query audit trail
│   │   └── DocumentSource.php                  # RAG document metadata
│   └── Mail/
│       └── PasswordResetOtpMail.php
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── Dockerfile
├── docker-compose.yml
├── docker-entrypoint.sh
└── composer.json
```

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Medical Appointment System                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐  │
│  │   React Frontend │    │  Laravel Backend │    │   External AI   │  │
│  │   (Port 5173)    │◄──►│   (Port 8000)    │◄──►│   (OpenRouter)  │  │
│  └─────────────────┘    └────────┬────────┘    └─────────────────┘  │
│                                  │                                     │
│                         ┌────────▼────────┐                          │
│                         │     Database     │                          │
│                         │  (SQLite/MySQL)  │                          │
│                         └──────────────────┘                          │
│                                                                      │
├─────────────────────────────────────────────────────────────────────┤
│                         AI Assistant (RAG System)                    │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────────────────┐  │
│  │ Chat API    │───►│ Vector DB   │───►│ Medical Knowledge Base  │  │
│  │ /chat/*     │    │ (Pinecone)  │    │ (Document Sources)       │  │
│  └─────────────┘    └─────────────┘    └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

**Base URL:** `http://localhost:8000/api`

### Authentication

All protected routes require:
```
Authorization: Bearer <token>
```

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/auth/register` | Register new user | No |
| POST | `/auth/login` | Login | No |
| POST | `/auth/verify-otp` | Verify OTP | No |
| POST | `/auth/forgot-password` | Request password reset | No |
| POST | `/auth/reset-password` | Reset password | No |
| GET | `/auth/me` | Get current user | Yes |
| POST | `/auth/logout` | Logout | Yes |
| PUT | `/auth/change-password` | Change password | Yes |

---

### Patient Routes

Requires `role:patient` middleware.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/doctors/search` | Search doctors by specialty/location |
| GET | `/doctors/{doctor}/slots` | Get available appointment slots |
| GET | `/appointments` | List user's appointments |
| POST | `/appointments` | Book new appointment |
| PATCH | `/appointments/{id}` | Update appointment |
| DELETE | `/appointments/{id}` | Cancel appointment |
| GET | `/medical-records` | Get patient's medical records |
| PUT | `/medical-records` | Update medical records |
| POST | `/patient/profile` | Update patient profile |
| GET | `/invoices` | List patient invoices |
| POST | `/invoices/{ref}/pay` | Pay invoice |

---

### Doctor Routes

Requires `role:doctor` middleware.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/doctor/profile` | Update doctor profile |
| GET | `/doctor/schedule` | Get doctor's schedule |
| PUT | `/doctor/schedule` | Update schedule |
| GET | `/doctor/patients` | List doctor's patients |
| GET | `/doctor/patients/{id}` | Get patient details |
| PUT | `/doctor/patients/{id}/care-status` | Update care status |
| GET | `/doctor/reports` | List doctor's reports |
| POST | `/doctor/reports` | Create medical report |
| PUT | `/doctor/reports/{id}` | Update report |
| GET | `/doctor/prescriptions` | List prescriptions |
| POST | `/doctor/prescriptions` | Create prescription |
| PUT | `/doctor/prescriptions/{id}` | Update prescription |
| DELETE | `/doctor/prescriptions/{id}` | Delete prescription |

---

### Admin Routes

Requires `role:admin` middleware.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/doctors` | List all doctors |
| GET | `/admin/doctors/{id}` | Get doctor details |
| POST | `/admin/doctors` | Add new doctor |
| PUT | `/admin/doctors/{id}` | Update doctor |
| DELETE | `/admin/doctors/{id}` | Delete doctor |
| PATCH | `/admin/doctors/{id}/toggle-status` | Toggle active status |
| GET | `/admin/admins` | List all admins |
| POST | `/admin/admins` | Add new admin |
| DELETE | `/admin/admins/{id}` | Delete admin |
| GET | `/admin/patients` | List all patients |
| GET | `/admin/patients/{id}` | Get patient details |
| POST | `/admin/patients` | Add new patient |
| PUT | `/admin/patients/{id}` | Update patient |
| DELETE | `/admin/patients/{id}` | Delete patient |
| GET | `/admin/appointments` | List all appointments |
| GET | `/admin/appointments/{id}` | Get appointment details |
| PATCH | `/admin/appointments/{id}` | Update appointment |
| DELETE | `/admin/appointments/{id}` | Delete appointment |
| GET | `/admin/stats` | Dashboard statistics |
| GET | `/admin/invoices` | Invoice report |
| GET | `/admin/settings` | Get settings |
| PUT | `/admin/settings` | Update settings |

---

### AI Chat Routes (Any Authenticated User)

Provides AI-powered medical assistant with RAG (Retrieval-Augmented Generation).

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/chat/conversations` | Create new conversation | 20/min |
| GET | `/chat/conversations` | List user's conversations | 20/min |
| GET | `/chat/conversations/{id}` | Get conversation with messages | 20/min |
| POST | `/chat/send` | Send message and get AI response | 20/min |

---

## Authentication Flow

### 1. Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "patient@example.com",
  "password": "password",
  "role": "patient"
}
```

**Success Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "Ahmed Ali",
    "email": "patient@example.com",
    "role": "patient"
  },
  "token": "1|abc123xyz..."
}
```

### 2. Using the Token

Include the token in all subsequent requests:

```http
Authorization: Bearer 1|abc123xyz...
```

### 3. Logout

```http
POST /api/auth/logout
Authorization: Bearer <token>
```

---

## AI Chat Assistant

The system includes an AI-powered medical assistant using RAG (Retrieval-Augmented Generation).

### Chat Flow

```
User Message ──► ChatController ──► Vector Search (Pinecone)
                                    │
                                    ▼
                            Retrieve Context Chunks
                                    │
                                    ▼
                            LLM (OpenRouter) ──► AI Response
                                    │
                                    ▼
                            AuditLog (tokens, latency)
```

### Chat Request Example

```http
POST /api/chat/send
Authorization: Bearer <token>
Content-Type: application/json

{
  "conversation_id": "uuid-of-conversation",
  "message": "What are the symptoms of diabetes?"
}
```

**Response:**
```json
{
  "response": "Based on the medical knowledge base, diabetes typically presents with symptoms such as increased thirst, frequent urination, fatigue, and blurred vision...",
  "citations": [
    {"source": "medical_guidelines.pdf", "page": 12}
  ],
  "confidence_score": 0.94,
  "disclaimer_shown": true
}
```

### Creating a New Conversation

```http
POST /api/chat/conversations
Authorization: Bearer <token>
Content-Type: application/json

{
  "session_token": "optional-session-id"
}
```

---

## Request/Response Examples

### Register Patient

```http
POST /api/auth/register
Content-Type: multipart/form-data

name: Ahmed Ali
email: ahmed@example.com
password: password123
confirmPassword: password123
role: patient
phone: 01234567890
gender: male
governorate: Cairo
area: Maadi
date_of_birth: 1990-05-15
```

### Book Appointment

```http
POST /api/appointments
Authorization: Bearer <token>
Content-Type: application/json

{
  "doctor_id": 2,
  "appointment_date": "2026-04-25 10:00:00",
  "notes": "First consultation"
}
```

### Search Doctors

```http
GET /api/doctors/search?specialty=cardiology&governorate=Cairo
Authorization: Bearer <token>
```

### Get Medical Records

```http
GET /api/medical-records
Authorization: Bearer <token>
```

**Response:**
```json
{
  "records": [
    {
      "id": 1,
      "diagnosis": "Hypertension",
      "treatment": "Medication A",
      "date": "2026-04-10"
    }
  ],
  "vitals": {
    "blood_pressure": "120/80",
    "heart_rate": 72
  },
  "chronic_diseases": ["Diabetes", "Hypertension"],
  "surgical_operations": ["Appendectomy 2018"]
}
```

---

## Database Schema

### Users Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK, auto-increment |
| name | string | required |
| email | string | unique, required |
| phone | string | nullable |
| password | string | hashed |
| role | enum | patient, doctor, admin |
| gender | enum | male, female |
| date_of_birth | date | nullable |
| height | integer | nullable (cm) |
| weight | decimal | nullable (kg) |
| blood_type | string | nullable |
| governorate | string | nullable |
| area | string | nullable |
| address | text | nullable |
| avatar | string | nullable |
| is_active | boolean | default true |
| remember_token | string | nullable |
| email_verified_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Appointments Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| patient_id | bigint | FK (users.id) |
| doctor_id | bigint | FK (users.id) |
| appointment_date | datetime | required |
| status | string | confirmed/completed/cancelled |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Medical Records Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| patient_id | bigint | FK (users.id), unique |
| chronic_diseases | json | nullable |
| surgical_operations | json | nullable |
| vital_signs | json | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Doctor Reports Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| doctor_id | bigint | FK (users.id) |
| patient_id | bigint | FK (users.id) |
| diagnosis | text | required |
| treatment_plan | text | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Prescriptions Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| doctor_id | bigint | FK (users.id) |
| patient_id | bigint | FK (users.id) |
| medications | json | required |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Conversations Table (AI Chat)

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK, unique identifier |
| user_id | bigint | FK (users.id) |
| session_token | string | nullable |
| metadata | json | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | nullable (soft deletes) |

### Messages Table (AI Chat)

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK, unique identifier |
| conversation_id | uuid | FK (conversations.id) |
| role | string | user/assistant/system |
| content | text | message content |
| citations | json | RAG source citations |
| retrieved_chunks | json | RAG context chunks |
| confidence_score | decimal | AI confidence (0-1) |
| disclaimer_shown | boolean | medical disclaimer shown |
| created_at | timestamp | |
| updated_at | timestamp | |

### Audit Logs Table (AI Chat)

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK, unique identifier |
| message_id | uuid | FK (messages.id) |
| user_ip | string | client IP address |
| query_hash | string | hash of query vector |
| query_vector | json | embedded query vector |
| retrieved_source_ids | json | Pinecone record IDs |
| llm_model | string | OpenRouter model used |
| prompt_tokens | integer | input token count |
| completion_tokens | integer | output token count |
| source_count | integer | number of sources retrieved |
| latency_ms | integer | response latency |
| model_response_status | string | success/error status |
| safety_triggered | boolean | safety filter triggered |
| created_at | timestamp | |

### Document Sources Table (RAG)

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK, unique identifier |
| source_type | string | pdf/doc/url |
| source_name | string | document name |
| source_path | string | storage path |
| content_hash | string | content integrity hash |
| metadata | json | document metadata |
| vector_id | string | Pinecone vector ID |
| created_at | timestamp | |

### Password Reset OTPs Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| email | string | required, indexed |
| otp | string | 6-digit OTP code |
| created_at | timestamp | |

### Doctor Patient Status Table

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| doctor_id | bigint | FK (users.id) |
| patient_id | bigint | FK (users.id) |
| status | string | active/inactive |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Role Middleware

The `EnsureRole` middleware validates user roles:

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    // Patient-only routes
});

Route::middleware(['auth:sanctum', 'role:doctor'])->group(function () {
    // Doctor-only routes
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin-only routes
});
```

---

## Error Handling

Standard error response format:

```json
{
  "message": "Error description in Arabic or English"
}
```

HTTP Status Codes:
- `200` - Success
- `201` - Created
- `204` - No Content (deleted)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/AuthApiTest.php

# Run tests with coverage
php artisan test --coverage

# Run unit tests only
php artisan test --testsuite=Unit

# Run feature tests only
php artisan test --testsuite=Feature
```

### Test Files

| File | Tests |
|------|-------|
| `AuthApiTest.php` | Login, register, logout, password reset |
| `AppointmentApiTest.php` | CRUD appointments |
| `DoctorApiTest.php` | Doctor profile, schedule, patients |
| `MedicalRecordApiTest.php` | Medical records CRUD |
| `AdminApiTest.php` | Admin operations |
| `RoleAccessTest.php` | Role-based access control |
| `PatientInvoicesApiTest.php` | Invoice operations |
| `DoctorSearchApiTest.php` | Doctor search functionality |
| `ChatApiTest.php` | AI chat conversations |

---

## Seeded Test Data

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| patient | patient@example.com | password | Test patient account |
| doctor | doctor@example.com | password | Test doctor account |
| admin | admin@lesahtak.com | password123 | Admin account |

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| APP_NAME | Laravel | Application name |
| APP_ENV | local | Environment mode |
| APP_KEY | | Application key (auto-generated) |
| APP_DEBUG | true | Debug mode |
| APP_URL | http://localhost | Base URL |
| DB_CONNECTION | sqlite | Database driver |
| DB_DATABASE | database/database.sqlite | Database path |
| SESSION_DRIVER | database | Session storage |
| CACHE_STORE | database | Cache driver |
| MAIL_MAILER | log | Mail driver |
| OPENROUTER_API_KEY | | OpenRouter AI API key |
| PINECONE_API_KEY | | Pinecone vector DB key |
| PINECONE_ENVIRONMENT | | Pinecone environment |
| PINECONE_INDEX | | Pinecone index name |

---

## Useful Commands

```bash
# Server
php artisan serve                           # Start development server
php artisan serve --port=8080               # Custom port

# Database
php artisan migrate                        # Run migrations
php artisan migrate:fresh                  # Reset and migrate
php artisan migrate:fresh --seed           # Reset with seed data
php artisan db:seed                         # Run seeders
php artisan db:seed --class=UserSeeder     # Run specific seeder

# Cache
php artisan config:clear                    # Clear config cache
php artisan route:clear                     # Clear route cache
php artisan cache:clear                    # Clear app cache
php artisan view:clear                     # Clear view cache

# Routes
php artisan route:list                      # List all routes
php artisan route:list --path=api          # API routes only

# Testing
php artisan test                           # Run tests
php artisan test --filter=Auth             # Filter tests

# Other
php artisan key:generate                   # Generate app key
php artisan about                         # Show app info
```

---

## Docker Commands

```bash
# Build image
docker build -t medical-backend ./backend

# Run container
docker run -p 8000:8000 medical-backend

# With docker-compose
docker-compose -f backend/docker-compose.yml up -d

# View logs
docker-compose -f backend/docker-compose.yml logs -f

# Stop
docker-compose -f backend/docker-compose.yml down
```

---

## Troubleshooting

### Migration fails
```bash
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed
```

### Token expires immediately
Check that `SESSION_DRIVER` is set correctly in `.env`

### CORS errors
The backend is configured to allow CORS from any origin in development. For production, update `config/cors.php`.

### Database locked
```bash
php artisan cache:clear
php artisan config:clear
```

---

## Security Notes

- Change default seeded passwords in production
- Set `APP_DEBUG=false` in production
- Use HTTPS in production
- Review CORS settings before deployment
- Sanitize all user inputs
- Rate limiting is applied to auth routes (10/min for register, 15/min for login, 20/min for chat)

---

*Last Updated: April 2026*
