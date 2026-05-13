# Backend Staff Documentation

## Medical Appointment System - Laravel Backend

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Technical Stack](#technical-stack)
3. [Project Structure](#project-structure)
4. [System Architecture](#system-architecture)
5. [Dependencies](#dependencies)
6. [Installation Guide](#installation-guide)
7. [Configuration](#configuration)
8. [Database Schema](#database-schema)
9. [API Endpoints](#api-endpoints)
10. [Authentication](#authentication)
11. [Role-Based Access Control](#role-based-access-control)
12. [AI Chat Assistant (RAG System)](#ai-chat-assistant-rag-system)
13. [Models & Relationships](#models--relationships)
14. [Middleware](#middleware)
15. [Seed Data](#seed-data)
16. [Testing](#testing)
17. [Docker Deployment](#docker-deployment)
18. [Common Tasks & Commands](#common-tasks--commands)
19. [Troubleshooting](#troubleshooting)
20. [Security Considerations](#security-considerations)
21. [Environment Variables](#environment-variables)

---

## System Overview

This is a **Laravel 12 PHP backend** for a medical appointment system with three user roles and an AI-powered chat assistant.

| Role | Description | Capabilities |
|------|-------------|--------------|
| **Patient** | End user who books appointments | Search doctors, book/cancel appointments, view medical records, pay invoices, AI chat |
| **Doctor** | Medical professional | Manage schedule, view patients, create reports/prescriptions, AI chat |
| **Admin** | System administrator | Full CRUD on all users, appointments, statistics, system settings |

---

## Technical Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Framework | Laravel | 12.x |
| Language | PHP | 8.2+ |
| Database | SQLite (default), MySQL, PostgreSQL | - |
| Authentication | Laravel Sanctum | 4.x |
| API Format | JSON REST | - |
| AI Integration | OpenRouter API | - |
| Vector Database | Pinecone | - |
| RAG System | Custom Implementation | - |

---

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php           # Authentication
│   │   │       ├── AppointmentController.php    # Appointment management
│   │   │       ├── DoctorController.php          # Doctor operations
│   │   │       ├── AdminController.php           # Admin operations
│   │   │       ├── PatientController.php         # Patient operations
│   │   │       ├── MedicalRecordController.php  # Medical records
│   │   │       └── ChatController.php            # AI Chat Assistant
│   │   └── Middleware/
│   │       └── EnsureRole.php                    # Role validation
│   ├── Models/
│   │   ├── User.php                              # User with roles
│   │   ├── Appointment.php                       # Appointments
│   │   ├── MedicalRecord.php                     # Medical history
│   │   ├── DoctorReport.php                      # Doctor reports
│   │   ├── Prescription.php                      # Prescriptions
│   │   ├── PasswordResetOtp.php                  # OTP reset
│   │   ├── DoctorPatientStatus.php               # Doctor-patient relationship
│   │   ├── Conversation.php                      # Chat conversations (UUID)
│   │   ├── Message.php                           # Chat messages with RAG
│   │   ├── AuditLog.php                          # AI query audit trail
│   │   ├── DocumentSource.php                    # RAG document metadata
│   │   └── Concerns/
│   │       └── HasUuidPrimaryKey.php             # UUID trait
│   └── Mail/
│       └── PasswordResetOtpMail.php               # OTP email
├── config/
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2026_04_15_181643_create_personal_access_tokens_table.php
│   │   ├── 2026_04_16_113637_create_appointments_table.php
│   │   ├── 2026_04_16_113638_create_medical_records_table.php
│   │   ├── 2026_04_16_114433_create_password_reset_otps_table.php
│   │   ├── 2026_04_16_154453_create_doctor_reports_table.php
│   │   ├── 2026_04_16_154453_create_prescriptions_table.php
│   │   ├── 2026_04_17_000001_add_height_to_users_table.php
│   │   ├── 2026_04_17_000002_add_patient_demographics_to_users_table.php
│   │   ├── 2026_04_20_000001_create_doctor_patient_statuses_table.php
│   │   └── 2026_04_24_000003_rag_system_tables.php
│   └── seeders/
├── routes/
│   └── api.php                                    # API routes
├── tests/
│   ├── Feature/
│   │   ├── AuthApiTest.php
│   │   ├── AppointmentApiTest.php
│   │   ├── DoctorApiTest.php
│   │   ├── MedicalRecordApiTest.php
│   │   ├── AdminApiTest.php
│   │   ├── RoleAccessTest.php
│   │   ├── PatientInvoicesApiTest.php
│   │   ├── DoctorSearchApiTest.php
│   │   └── ChatApiTest.php
│   └── Unit/
├── Dockerfile
├── docker-compose.yml
├── docker-entrypoint.sh
└── composer.json
```

---

## System Architecture

### Overall System Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MEDICAL APPOINTMENT SYSTEM                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────────────────────────────────────────────────────────────┐    │
│   │                         EXTERNAL SERVICES                           │    │
│   │  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────┐  │    │
│   │  │   OpenRouter AI  │    │   Pinecone DB    │    │  Email (Log) │  │    │
│   │  │  (LLM Provider)  │    │  (Vector Store)  │    │              │  │    │
│   │  └────────┬─────────┘    └────────┬─────────┘    └──────────────┘  │    │
│   │           │                        │                                 │    │
│   └───────────┼────────────────────────┼─────────────────────────────────┘    │
│               │                        │                                      │
│   ┌───────────▼────────────────────────▼──────────────────────────────────┐   │
│   │                          LARAVEL BACKEND                               │   │
│   │                              Port 8000                                 │   │
│   │  ┌─────────────────────────────────────────────────────────────────┐  │   │
│   │  │                      API LAYER                                   │  │   │
│   │  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │  │   │
│   │  │  │   Auth   │ │Appoint-  │ │  Doctor  │ │  Admin   │ │  Chat  │  │   │
│   │  │  │          │ │  ments   │ │          │ │          │ │   AI   │  │   │
│   │  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘          │  │   │
│   │  └─────────────────────────────────────────────────────────────────┘  │   │
│   │  ┌─────────────────────────────────────────────────────────────────┐  │   │
│   │  │                    MIDDLEWARE LAYER                              │  │   │
│   │  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │  │   │
│   │  │  │   Sanctum    │  │ EnsureRole   │  │      Throttle        │  │  │   │
│   │  │  │  (Auth)      │  │   (RBAC)     │  │ (Rate Limiting)      │  │  │   │
│   │  │  └──────────────┘  └──────────────┘  └──────────────────────┘  │  │   │
│   │  └─────────────────────────────────────────────────────────────────┘  │   │
│   │  ┌─────────────────────────────────────────────────────────────────┐  │   │
│   │  │                    MODEL LAYER                                   │  │   │
│   │  │  User │ Appointment │ MedicalRecord │ DoctorReport │ Prescription│ │  │
│   │  │  Conversation │ Message │ AuditLog │ DocumentSource            │  │   │
│   │  └─────────────────────────────────────────────────────────────────┘  │   │
│   └───────────────────────────────────────────────────────────────────────┘   │
│                                    │                                          │
│   ┌───────────────────────────────▼───────────────────────────────────────┐   │
│   │                         DATABASE LAYER                                │   │
│   │           SQLite (dev) │ MySQL (prod) │ PostgreSQL (prod)            │   │
│   └───────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

### AI Chat System Architecture (RAG)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           AI CHAT SYSTEM (RAG)                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  User Query                                                                  │
│      │                                                                        │
│      ▼                                                                        │
│  ┌─────────────────────────────────────────────────────────────────────────┐  │
│  │                      1. QUERY PROCESSING                                │  │
│  │  • Extract query text                                                   │  │
│  │  • Check conversation history                                            │  │
│  │  • Validate user authentication                                         │  │
│  │  • Rate limiting (20 req/min)                                           │  │
│  └────────────────────────────────┬────────────────────────────────────────┘  │
│                                   │                                           │
│                                   ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────────┐  │
│  │                      2. EMBEDDING & SEARCH                             │  │
│  │  • Generate query embedding (OpenRouter)                                │  │
│  │  • Search Pinecone vector DB                                            │  │
│  │  • Retrieve top-k relevant chunks                                       │  │
│  │  • Return source document IDs                                            │  │
│  └────────────────────────────────┬────────────────────────────────────────┘  │
│                                   │                                           │
│                                   ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────────┐  │
│  │                      3. RESPONSE GENERATION                             │  │
│  │  • Build prompt with retrieved context                                  │  │
│  │  • Call LLM via OpenRouter (medical guidelines model)                   │  │
│  │  • Calculate confidence score                                           │  │
│  │  • Generate citations from sources                                       │  │
│  │  • Add medical disclaimer                                                │  │
│  └────────────────────────────────┬────────────────────────────────────────┘  │
│                                   │                                           │
│                                   ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────────────┐  │
│  │                      4. AUDIT & STORAGE                                 │  │
│  │  • Store message in database                                             │  │
│  │  • Create audit log (tokens, latency, IP)                              │  │
│  │  • Return response to user                                               │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Data Flow Diagram

```
┌─────────┐      ┌──────────┐      ┌───────────┐      ┌────────────┐
│  User   │─────►│  React   │─────►│ Laravel   │─────►│  Database  │
│         │      │  Frontend│      │  Backend  │      │  (SQLite)  │
└─────────┘      └──────────┘      └───────────┘      └────────────┘
                                            │
                                            ▼
                                     ┌────────────┐
                                     │  OpenRouter│
                                     │  (AI/LLM)  │
                                     └────────────┘
                                            │
                                            ▼
                                     ┌────────────┐
                                     │  Pinecone  │
                                     │  (Vectors) │
                                     └────────────┘
```

### Entity Relationship Diagram

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│      USERS       │       │   APPOINTMENTS    │       │   MEDICAL        │
│                  │       │                  │       │   RECORDS        │
│  id (PK)         │──┐    │  id (PK)         │       │                  │
│  name            │  │    │  patient_id (FK) │◄──┐   │  id (PK)         │
│  email           │  │    │  doctor_id (FK)  │◄──┘   │  patient_id(FK)  │◄─┐
│  phone           │  │    │  appointment_date│       │  chronic_diseases│  │
│  role            │  │    │  status          │       │  surgical_ops    │  │
│  gender          │  │    │  notes           │       │  vital_signs     │  │
│  is_active       │  │    └──────────────────┘       └──────────────────┘  │
│  ...             │  │            │                        │              │
└──────────────────┘  │            │                        │              │
         │            │            ▼                        │              │
         │            │    ┌──────────────────┐                │              │
         │            └───►│  DOCTOR_PATIENT  │                │              │
         │                 │    STATUS        │                │              │
         │                 │                  │                │              │
         │                 │  id (PK)          │                │              │
         │                 │  doctor_id (FK)   │◄───────────────┘              │
         │                 │  patient_id (FK) │◄──────────────────────────────┘
         │                 │  status           │
         │                 └──────────────────┘
         │
         ▼
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│  DOCTOR_REPORTS │       │  PRESCRIPTIONS   │       │  CONVERSATIONS   │
│                  │       │                  │       │  (UUID)          │
│  id (PK)         │       │  id (PK)         │       │                  │
│  doctor_id (FK)  │◄─┐   │  doctor_id (FK)  │◄─┐   │  id (PK)         │
│  patient_id (FK) │  │   │  patient_id (FK) │  │   │  user_id (FK)    │◄─┐
│  diagnosis       │  │   │  medications    │  │   │  metadata        │  │
│  treatment_plan  │  │   │  notes           │  │   └────────┬─────────┘  │
└──────────────────┘  │   └──────────────────┘  │            │            │
         │            │            │            │            ▼            │
         │            │            │            │   ┌──────────────────┐ │
         │            │            │            │   │     MESSAGES      │ │
         │            │            │            │   │     (UUID)        │ │
         │            │            │            │   │                   │ │
         │            │            │            │   │  id (PK)          │ │
         │            │            │            │   │  conversation_id  │◄┘
         │            │            │            │   │  role             │
         │            │            │            │   │  content          │
         │            │            │            │   │  citations (JSON) │
         │            │            │            │   │  confidence_score │
         │            │            │            │   └────────┬─────────┘
         │            │            │            │            │
         │            │            │            │            ▼
         │            │            │            │   ┌──────────────────┐
         │            │            │            │   │    AUDIT_LOGS    │
         │            │            │            │   │    (UUID)        │
         │            │            │            │   │                  │
         │            │            │            │   │  message_id (FK) │
         │            │            │            │   │  prompt_tokens    │
         │            │            │            │   │  completion_tokens
         │            │            │            │   │  latency_ms      │
         │            │            │            │   │  safety_triggered│
         │            │            │            │   └──────────────────┘
         │            │            │            │
         │            │            │            │
         │            │            │            │
         ▼            ▼            ▼            ▼
┌──────────────────────────────────────────────────────────────┐
│                    DOCUMENT_SOURCES (RAG)                     │
│                                                             │
│  id (UUID)        source_type   source_name                 │
│  source_path      content_hash   metadata                   │
│  vector_id        created_at                                   │
└──────────────────────────────────────────────────────────────┘
```

---

## Dependencies

### PHP Dependencies (from composer.json)

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^12.0 | Core Laravel framework |
| `laravel/sanctum` | ^4.0 | API authentication |
| `laravel/tinker` | ^2.10.1 | Interactive PHP console |

### Development Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `fakerphp/faker` | ^1.23 | Fake data generation |
| `laravel/pint` | ^1.24 | Code style fixer |
| `laravel/sail` | ^1.41 | Scaffold generator |
| `mockery/mockery` | ^1.6 | Unit test mocking |
| `nunomaduro/collision` | ^8.6 | Error handling |
| `phpunit/phpunit` | ^11.5.50 | Testing framework |

---

## Installation Guide

### Prerequisites

- **PHP 8.2+** installed
- **Composer** package manager
- **Database**: SQLite, MySQL, or PostgreSQL

### Option 1: Manual Installation

```bash
# 1. Navigate to backend directory
cd backend

# 2. Install PHP dependencies
composer install

# 3. Setup environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Create SQLite database (or configure MySQL/PostgreSQL)
touch database/database.sqlite

# 6. Run migrations and seed data
php artisan migrate --seed

# 7. Start development server
php artisan serve
```

### Option 2: Docker Installation

```bash
# Build and start containers
cd backend
docker-compose up -d

# View logs
docker-compose logs -f

# Stop containers
docker-compose down
```

### Option 3: Using Make Commands

From project root:

```bash
make install    # Install dependencies
make setup      # Setup database
make start      # Start containers
```

---

## Configuration

### Environment Variables (.env)

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | Laravel | Application name |
| `APP_ENV` | local | Environment mode |
| `APP_KEY` | - | Application key |
| `APP_DEBUG` | true | Debug mode |
| `APP_URL` | http://localhost:8000 | Base URL |
| `DB_CONNECTION` | sqlite | Database driver |
| `DB_DATABASE` | database/database.sqlite | Database path |
| `SESSION_DRIVER` | database | Session driver |
| `CACHE_STORE` | database | Cache driver |
| `MAIL_MAILER` | log | Mail driver |
| `OPENROUTER_API_KEY` | - | OpenRouter AI API key |
| `OPENROUTER_SITE_URL` | - | OpenRouter site URL |
| `OPENROUTER_SITE_NAME` | - | OpenRouter site name |
| `PINECONE_API_KEY` | - | Pinecone API key |
| `PINECONE_ENVIRONMENT` | - | Pinecone environment |
| `PINECONE_INDEX` | - | Pinecone index name |

---

## Database Schema

### Users Table

Primary user table with role-based access.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| name | string | required | Full name |
| email | string | unique, required | Email address |
| phone | string | nullable | Phone number |
| password | string | hashed | Bcrypt hashed password |
| role | enum | patient, doctor, admin | User role |
| gender | enum | male, female | Gender |
| date_of_birth | date | nullable | Date of birth |
| height | integer | nullable | Height in cm |
| weight | decimal | nullable | Weight in kg |
| blood_type | string | nullable | Blood type (A+, O-, etc.) |
| governorate | string | nullable | Governorate/State |
| area | string | nullable | City/Area |
| address | text | nullable | Full address |
| avatar | string | nullable | Avatar image path |
| is_active | boolean | default true | Account status |
| remember_token | string | nullable | Remember me token |
| email_verified_at | timestamp | nullable | Email verification |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

### Appointments Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| patient_id | bigint | FK (users.id) | Patient reference |
| doctor_id | bigint | FK (users.id) | Doctor reference |
| appointment_date | datetime | required | Scheduled date/time |
| status | string | confirmed/completed/cancelled | Status |
| notes | text | nullable | Additional notes |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

### Medical Records Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| patient_id | bigint | FK (users.id), unique | Patient reference |
| chronic_diseases | json | nullable | Array of conditions |
| surgical_operations | json | nullable | Array of surgeries |
| vital_signs | json | nullable | Blood pressure, heart rate |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

### Doctor Reports Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| doctor_id | bigint | FK (users.id) | Reporting doctor |
| patient_id | bigint | FK (users.id) | Patient reference |
| diagnosis | text | required | Diagnosis description |
| treatment_plan | text | nullable | Treatment plan |
| notes | text | nullable | Additional notes |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

### Prescriptions Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| doctor_id | bigint | FK (users.id) | Prescribing doctor |
| patient_id | bigint | FK (users.id) | Patient reference |
| medications | json | required | Array of medications |
| notes | text | nullable | Instructions |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

### Conversations Table (AI Chat - UUID)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | uuid | PK, unique | Unique identifier |
| user_id | bigint | FK (users.id) | Owner of conversation |
| session_token | string | nullable | Session grouping |
| metadata | json | nullable | Additional metadata |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |
| deleted_at | timestamp | nullable | Soft delete |

### Messages Table (AI Chat - UUID)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | uuid | PK, unique | Unique identifier |
| conversation_id | uuid | FK (conversations.id) | Parent conversation |
| role | string | user/assistant/system | Message sender |
| content | text | required | Message content |
| citations | json | nullable | RAG source citations |
| retrieved_chunks | json | nullable | RAG context chunks |
| confidence_score | decimal | nullable | AI confidence (0-1) |
| disclaimer_shown | boolean | default false | Medical disclaimer |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

### Audit Logs Table (AI Chat - UUID)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | uuid | PK, unique | Unique identifier |
| message_id | uuid | FK (messages.id) | Related message |
| user_ip | string | nullable | Client IP |
| query_hash | string | nullable | Query vector hash |
| query_vector | json | nullable | Embedded query |
| retrieved_source_ids | json | nullable | Pinecone record IDs |
| llm_model | string | nullable | OpenRouter model |
| prompt_tokens | integer | nullable | Input token count |
| completion_tokens | integer | nullable | Output token count |
| source_count | integer | nullable | Sources retrieved |
| latency_ms | integer | nullable | Response time |
| model_response_status | string | nullable | success/error |
| safety_triggered | boolean | default false | Safety filter |
| created_at | timestamp | auto | Creation timestamp |

### Document Sources Table (RAG - UUID)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | uuid | PK, unique | Unique identifier |
| source_type | string | required | pdf/doc/url |
| source_name | string | required | Document name |
| source_path | string | required | Storage path |
| content_hash | string | nullable | Integrity hash |
| metadata | json | nullable | Document metadata |
| vector_id | string | nullable | Pinecone vector ID |
| created_at | timestamp | auto | Creation timestamp |

### Password Reset OTPs Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| email | string | required, indexed | User email |
| otp | string | required | 6-digit OTP |
| created_at | timestamp | auto | Creation timestamp |

### Doctor Patient Status Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Unique identifier |
| doctor_id | bigint | FK (users.id) | Doctor reference |
| patient_id | bigint | FK (users.id) | Patient reference |
| status | string | active/inactive | Care status |
| notes | text | nullable | Notes |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Update timestamp |

---

## API Endpoints

**Base URL:** `http://localhost:8000/api`

### Authentication Routes (No Auth Required)

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/auth/register` | Register new user | 10/min |
| POST | `/auth/login` | Login with credentials | 15/min |
| POST | `/auth/verify-otp` | Verify OTP code | - |
| POST | `/auth/forgot-password` | Request password reset | - |
| POST | `/auth/reset-password` | Reset password | - |

### Auth Routes (Requires Sanctum Token)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/auth/me` | Get current user |
| POST | `/auth/logout` | Invalidate token |
| PUT | `/auth/change-password` | Change password |

### Patient Routes (Requires `role:patient`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/doctors/search` | Search available doctors |
| GET | `/doctors/{doctor}/slots` | Get doctor's time slots |
| GET | `/appointments` | List user's appointments |
| POST | `/appointments` | Book new appointment |
| PATCH | `/appointments/{id}` | Update appointment |
| DELETE | `/appointments/{id}` | Cancel appointment |
| GET | `/medical-records` | Get medical records |
| PUT | `/medical-records` | Update medical records |
| POST | `/patient/profile` | Update patient profile |
| GET | `/invoices` | List patient invoices |
| POST | `/invoices/{ref}/pay` | Pay invoice |

### Doctor Routes (Requires `role:doctor`)

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

### Admin Routes (Requires `role:admin`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/doctors` | List all doctors |
| GET | `/admin/doctors/{id}` | Get doctor details |
| POST | `/admin/doctors` | Add new doctor |
| PUT | `/admin/doctors/{id}` | Update doctor |
| DELETE | `/admin/doctors/{id}` | Delete doctor |
| PATCH | `/admin/doctors/{id}/toggle-status` | Toggle doctor status |
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
| GET | `/admin/settings` | Get system settings |
| PUT | `/admin/settings` | Update settings |

### AI Chat Routes (Any Authenticated User)

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/chat/conversations` | Create new conversation | 20/min |
| GET | `/chat/conversations` | List user's conversations | 20/min |
| GET | `/chat/conversations/{id}` | Get conversation with messages | 20/min |
| POST | `/chat/send` | Send message, get AI response | 20/min |

---

## Authentication

### Authentication Flow

The system uses **Laravel Sanctum** for token-based API authentication.

### 1. Register

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

**Response (201):**
```json
{
  "user": {
    "id": 1,
    "name": "Ahmed Ali",
    "email": "ahmed@example.com",
    "role": "patient"
  },
  "token": "1|abc123xyz..."
}
```

### 2. Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "patient@example.com",
  "password": "password",
  "role": "patient"
}
```

**Response (200):**
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

### 3. Using the Token

Include the token in the `Authorization` header:

```http
Authorization: Bearer 1|abc123xyz...
```

### 4. Logout

```http
POST /api/auth/logout
Authorization: Bearer <token>
```

---

## Role-Based Access Control

### Role Middleware (`EnsureRole`)

Validates authenticated user has required role.

### Route Protection

```php
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

### Response on Unauthorized

```json
{
  "message": "Unauthorized"
}
```

HTTP Status: `403 Forbidden`

---

## AI Chat Assistant (RAG System)

### Overview

The system includes an AI-powered medical assistant using **Retrieval-Augmented Generation (RAG)**. This provides context-aware responses based on a medical knowledge base.

### Components

| Component | Technology | Purpose |
|-----------|------------|---------|
| LLM Provider | OpenRouter | AI model hosting |
| Vector DB | Pinecone | Semantic search storage |
| Embedding | OpenRouter API | Text vectorization |
| Audit | Database | Token & latency tracking |

### Chat Flow

```
User Message
     │
     ▼
┌────────────────────────────┐
│ ChatController::send()     │
│ - Validate authentication  │
│ - Rate limiting check      │
└────────────────────────────┘
     │
     ▼
┌────────────────────────────┐
│ Generate Query Embedding    │
│ (OpenRouter API)           │
└────────────────────────────┘
     │
     ▼
┌────────────────────────────┐
│ Vector Similarity Search    │
│ (Pinecone)                 │
│ - Top-k relevant chunks    │
│ - Return source IDs        │
└────────────────────────────┘
     │
     ▼
┌────────────────────────────┐
│ Build Prompt with Context   │
│ + Medical Disclaimer        │
└────────────────────────────┘
     │
     ▼
┌────────────────────────────┐
│ Generate Response           │
│ (OpenRouter LLM)           │
│ - Calculate confidence      │
│ - Generate citations        │
└────────────────────────────┘
     │
     ▼
┌────────────────────────────┐
│ Store & Audit               │
│ - Save Message to DB        │
│ - Create AuditLog           │
│ - Return response           │
└────────────────────────────┘
```

### Chat API Usage

#### Create Conversation

```http
POST /api/chat/conversations
Authorization: Bearer <token>
Content-Type: application/json

{
  "session_token": "optional-session-id"
}
```

**Response:**
```json
{
  "id": "uuid-of-conversation",
  "user_id": 1,
  "session_token": "optional-session-id",
  "created_at": "2026-04-24T12:00:00Z"
}
```

#### List Conversations

```http
GET /api/chat/conversations
Authorization: Bearer <token>
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid-1",
      "session_token": "session-1",
      "last_message": "What are diabetes symptoms?",
      "created_at": "2026-04-24T10:00:00Z"
    }
  ]
}
```

#### Get Conversation with Messages

```http
GET /api/chat/conversations/{id}
Authorization: Bearer <token>
```

#### Send Message

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
  "response": "Based on the medical guidelines, diabetes typically presents with increased thirst (polydipsia), frequent urination (polyuria), fatigue, blurred vision, and slow wound healing. Other symptoms may include unexplained weight loss and tingling in hands or feet.",
  "citations": [
    {"source": "diabetes_guidelines.pdf", "page": 15, "relevance": 0.92}
  ],
  "confidence_score": 0.94,
  "disclaimer_shown": true
}
```

### RAG Database Tables

#### Document Sources

Stores metadata about indexed medical documents.

| Field | Description |
|-------|-------------|
| source_type | pdf, doc, url |
| source_name | Document name |
| source_path | Storage path |
| content_hash | Integrity check |
| metadata | Document metadata JSON |
| vector_id | Pinecone record ID |

#### Audit Logs

Tracks all AI queries for compliance and monitoring.

| Field | Description |
|-------|-------------|
| prompt_tokens | Input token count |
| completion_tokens | Output token count |
| latency_ms | Response time |
| llm_model | Model used |
| safety_triggered | Safety filter status |

---

## Models & Relationships

### User Model

**Fillable:** name, email, phone, gender, height, weight, blood_type, governorate, area, address, date_of_birth, avatar, role, is_active, password

**Relationships:**
- `patientAppointments()` - HasMany (as patient)
- `doctorAppointments()` - HasMany (as doctor)
- `medicalRecord()` - HasOne (for patients)
- `conversations()` - HasMany (AI chat)

### Appointment Model

**Relationships:**
- `patient()` - BelongsTo User
- `doctor()` - BelongsTo User

### MedicalRecord Model

One-to-one with User (patient).

### DoctorReport Model

**Relationships:**
- `doctor()` - BelongsTo User
- `patient()` - BelongsTo User

### Prescription Model

**Relationships:**
- `doctor()` - BelongsTo User
- `patient()` - BelongsTo User

### Conversation Model (UUID)

Uses `HasUuidPrimaryKey` trait and SoftDeletes.

**Relationships:**
- `user()` - BelongsTo User
- `messages()` - HasMany (ordered by created_at)

### Message Model (UUID)

**Relationships:**
- `conversation()` - BelongsTo Conversation
- `auditLog()` - HasOne

### AuditLog Model (UUID)

**Relationships:**
- `message()` - BelongsTo Message

### DocumentSource Model (UUID)

Stores RAG document metadata.

---

## Middleware

### EnsureRole Middleware

Located at `app/Http/Middleware/EnsureRole.php`

Validates authenticated user has specified role.

**Usage:**
```php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin routes
});
```

**Implementation:**
1. Checks `auth:sanctum` - user must be authenticated
2. Verifies user role matches expected role
3. Returns 403 if unauthorized

### Throttle Middleware

Rate limiting applied to routes:

| Route | Limit |
|-------|-------|
| `/auth/register` | 10/min |
| `/auth/login` | 15/min |
| `/chat/*` | 20/min |

---

## Seed Data

### Test Accounts

| Role | Email | Password | Description |
|------|-------|----------|-------------|
| Patient | patient@example.com | password | Test patient |
| Doctor | doctor@example.com | password | Test doctor |
| Admin | admin@lesahtak.com | password123 | System admin |

### Running Seeders

```bash
php artisan db:seed                      # All seeders
php artisan db:seed --class=UserSeeder  # Specific seeder
php artisan migrate:fresh --seed        # Fresh install
```

---

## Testing

### Running Tests

```bash
php artisan test                        # All tests
php artisan test tests/Feature/AuthApiTest.php
php artisan test --coverage            # With coverage
php artisan test --testsuite=Unit       # Unit only
php artisan test --testsuite=Feature    # Feature only
php artisan test --filter=Auth          # Filter by name
```

### Test Files

| Test File | Coverage |
|-----------|----------|
| `AuthApiTest.php` | Login, register, logout, password reset |
| `AppointmentApiTest.php` | CRUD appointments |
| `DoctorApiTest.php` | Doctor profile, schedule, patients |
| `MedicalRecordApiTest.php` | Medical records |
| `AdminApiTest.php` | Admin operations |
| `RoleAccessTest.php` | RBAC verification |
| `PatientInvoicesApiTest.php` | Invoice operations |
| `DoctorSearchApiTest.php` | Doctor search |
| `ChatApiTest.php` | AI chat conversations |

---

## Docker Deployment

### Building Image

```bash
cd backend
docker build -t medical-backend .
```

### Running Container

```bash
docker run -p 8000:8000 medical-backend
```

### Docker Compose

```bash
docker-compose up -d           # Start
docker-compose logs -f          # View logs
docker-compose down             # Stop
docker-compose build --no-cache # Rebuild
```

### Container Configuration

- PHP 8.2+ with extensions
- Composer for dependencies
- Automatic migrations/seeding
- Port 8000 exposed

---

## Common Tasks & Commands

### Server Management

```bash
php artisan serve                    # Start server
php artisan serve --port=8080        # Custom port
php artisan serve --host=0.0.0.0    # Exposed server
```

### Database Operations

```bash
php artisan migrate                 # Run migrations
php artisan migrate:rollback       # Rollback last
php artisan migrate:fresh          # Reset database
php artisan migrate:fresh --seed   # Reset with data
php artisan db:seed                # Run seeders
```

### Cache Management

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
php artisan optimize:clear
```

### Route Management

```bash
php artisan route:list             # All routes
php artisan route:list --path=api  # API routes
php artisan route:list -v          # Verbose
```

### Application

```bash
php artisan about                   # App info
php artisan key:generate           # Generate key
```

---

## Troubleshooting

### Migration Fails

```bash
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed
```

### Token Expires

Check `SESSION_DRIVER=database` in `.env`

### CORS Errors

Update `config/cors.php` for production origins.

### Database Locked

```bash
php artisan cache:clear
php artisan config:clear
```

### Permission Issues

```bash
chmod -R 775 storage bootstrap/cache
```

### Port in Use

```bash
lsof -i :8000
kill -9 <PID>
```

---

## Security Considerations

### Default Test Passwords

| Role | Email | Password |
|------|-------|----------|
| Patient | patient@example.com | password |
| Doctor | doctor@example.com | password |
| Admin | admin@lesahtak.com | password123 |

**IMPORTANT:** Change or remove seeders in production.

### Production Checklist

1. Set `APP_DEBUG=false`
2. Use HTTPS
3. Review CORS settings
4. Change default passwords
5. Use strong DB passwords
6. Sanitize inputs
7. Configure rate limiting

### Rate Limiting

| Route | Limit |
|-------|-------|
| Register | 10/min |
| Login | 15/min |
| Chat | 20/min |

---

## Environment Variables

```env
APP_NAME=MedicalApp
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log

OPENROUTER_API_KEY=your-key
OPENROUTER_SITE_URL=http://localhost
OPENROUTER_SITE_NAME=MedicalApp

PINECONE_API_KEY=your-key
PINECONE_ENVIRONMENT=us-east-1
PINECONE_INDEX=medical-index
```

---

## Support & Maintenance

1. Check logs: `storage/logs/laravel.log`
2. Verify routes: `php artisan route:list`
3. Clear caches: `php artisan config:clear`
4. Check database connectivity

---

*Last Updated: April 2026*
