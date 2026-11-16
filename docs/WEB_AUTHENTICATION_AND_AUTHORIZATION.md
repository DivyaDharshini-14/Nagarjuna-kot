# Web Authentication and Authorization

## Test login

Local development credentials:

- URL: `http://127.0.0.1:8000/login`
- Email: `admin@nagarjuna.test`
- Password: `Admin@12345`
- Role: `super-admin`

This is a development-only account. Change its password or remove the account
before using a shared, staging, or production database.

Start the application with:

```bash
php artisan serve
```

## Web authentication flow

Web authentication uses Laravel's session guard. No mobile or API token
authentication is included in the current phase.

Routes are defined in `routes/web.php`:

- `GET /login` displays the login form.
- `POST /login` validates credentials and creates a session.
- `GET /dashboard` requires an authenticated, active account.
- `POST /logout` invalidates the session and regenerates the CSRF token.

`AuthenticatedSessionController` handles login and logout. Login requests are
limited to six attempts per minute. The session ID is regenerated after a
successful login to prevent session fixation.

`EnsureAccountIsActive` runs on protected web routes. Access is rejected when:

- The user is inactive or soft-deleted.
- The assigned company is inactive or soft-deleted.
- The assigned branch is inactive or soft-deleted.
- The assigned branch does not belong to the user's assigned company.

Public registration is intentionally disabled. Administrators provision staff
accounts.

## Creating an administrator

Use the interactive command so that a password is not stored in source code:

```bash
php artisan auth:create-admin
```

The command creates an active user and assigns the `super-admin` role.

## Database structure

Authorization uses the project's custom role and permission models:

- `users`: staff login and company/branch assignment.
- `roles`: role name, unique slug, description, and active status.
- `permissions`: module/action permissions represented by a unique slug.
- `user_roles`: many-to-many assignment between users and roles.
- `role_permissions`: many-to-many assignment between roles and permissions.

Permission slugs follow the `module.action` format, for example:

```text
company.view
branch.edit
kitchen.add
user.delete
```

## Seeded roles

Run the idempotent seeders with:

```bash
php artisan db:seed
```

The following roles are created:

- `super-admin`
- `company-admin`
- `branch-manager`
- `restaurant-manager`
- `captain-waiter`
- `kitchen-manager`
- `kitchen-staff`

Current permission assignments:

- Super Admin receives every permission and also has a code-level bypass.
- Company Admin receives all actions for company, branch, kitchen, and user.
- Branch Manager can view/add/edit branches, kitchens, and users.
- Restaurant Manager can view branches, kitchens, and users.
- Captain/Waiter, Kitchen Manager, and Kitchen Staff currently have no
  permissions. Their permissions will be added with the Order and KOT modules.

The current modules are company, branch, kitchen, user, role, and permission.
Each module is seeded with view, add, edit, delete, approve, cancel, export, and
stock-adjustment actions.

## Checking roles and permissions

The `User` model provides:

```php
$user->hasRole('branch-manager');
$user->hasPermission('kitchen.edit');
```

Inactive roles do not grant access. A user may have multiple roles, and access
is granted when any active role contains the requested permission.

Protect a route with the permission middleware:

```php
Route::get('/users', [UserController::class, 'index'])
    ->middleware(['auth', 'account.active', 'permission:user.view']);
```

Check access in a Blade view:

```blade
@if (auth()->user()->hasPermission('user.add'))
    <a href="/users/create">Add user</a>
@endif
```

Do not rely only on hiding links. Every protected controller route must also
use permission middleware or a policy.

## Assigning roles and permissions

Assign a role to a user:

```php
$user->roles()->syncWithoutDetaching([$role->id]);
```

Replace all roles assigned to a user:

```php
$user->roles()->sync([$roleId]);
```

Replace all permissions assigned to a role:

```php
$role->permissions()->sync($permissionIds);
```

## Multi-company safety

Authentication validates the user's company and branch assignment, but
permission checks alone do not filter database records. Each future company,
branch, kitchen, user, order, and stock query must also be scoped to the
authenticated user's allowed company and branch. Only Super Admin should be
allowed to operate without tenant scoping.
