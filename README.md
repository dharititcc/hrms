# Sample Project

Production-oriented SaaS foundation with a Laravel API backend and a Next.js frontend.

## Stack

- Laravel API with Sanctum token authentication
- PHP 8.3+
- MySQL or SQLite
- Next.js App Router
- React, TypeScript, Tailwind CSS
- React Aria Components
- TanStack Query
- Zustand
- React Hook Form and Zod
- Axios

## Project structure

```text
sample-project/
├── backend/       Laravel API
├── next-monorepo/ Next.js application and shared UI package
└── frontend/      Legacy Vite starter
```

The active frontend is `next-monorepo/apps/web`. The `frontend` directory is not used by the current Next.js application.

## Local setup

### Backend

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Configure these values in `backend/.env`:

```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:3000
```

Run the queue worker in a second terminal. Verification and password-reset notifications are queued.

```powershell
cd backend
php artisan queue:work
```

When using the default local log mailer, inspect email links in:

```text
backend/storage/logs/laravel.log
```

### Frontend

Create `next-monorepo/apps/web/.env.local`:

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
```

Install dependencies and start Next.js:

```powershell
cd next-monorepo
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).

## Authentication

Available pages:

```text
/login
/register
/forgot-password
/reset-password
/email-verification
```

Authentication uses Laravel Sanctum API tokens. Tokens are attached automatically by the Axios client and persisted by the Zustand auth store.

## Staff management

Open:

```text
/dashboard/staff
```

Staff management includes:

- Searchable staff listing
- Active/inactive filtering
- Add, edit, and delete operations
- Roles: Admin, Manager, Member
- Accessible searchable role combobox
- Interactive active/inactive status switch
- Owner-scoped authorization

API endpoints:

```text
GET    /api/auth/staff
POST   /api/auth/staff
GET    /api/auth/staff/{staff}
PUT    /api/auth/staff/{staff}
DELETE /api/auth/staff/{staff}
```

## Phase 2: attendance and leave

Available dashboard modules:

```text
/dashboard/attendance
/dashboard/leave
```

Phase 2 currently includes:

- Staff clock-in and clock-out tracking
- Attendance history
- Leave types with default annual allowances
- Leave request submission
- Leave request approval and rejection
- Staff selection for operational actions

Phase 2 API endpoints:

```text
GET   /api/auth/attendance
POST  /api/auth/attendance/clock-in
POST  /api/auth/attendance/{attendance}/clock-out
GET   /api/auth/leave/types
GET   /api/auth/leave/requests
POST  /api/auth/leave/requests
PATCH /api/auth/leave/requests/{leaveRequest}/status
```

## Frontend routes

```text
/dashboard
/dashboard/staff
/dashboard/settings
```

## Quality checks

Backend:

```powershell
cd backend
php artisan test
php artisan route:list
```

Frontend:

```powershell
cd next-monorepo/apps/web
npm run typecheck
npm run lint
npm run build
```

## Architecture

Backend business logic follows a controller, request, resource, service, repository, model, and policy separation. Frontend API calls live in services, server state is managed with TanStack Query, client authentication state is managed with Zustand, and form validation is defined with Zod.

The UI uses reusable React Aria-based controls and responsive feature modules. Dashboard business modules are isolated under `apps/web/features`.
