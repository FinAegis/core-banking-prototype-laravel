# Business Team Management Documentation

## Overview

Business team management lets a business customer add people to its organisation and give each person a role.
It builds on the teams feature of Laravel Jetstream.
Accounts and fraud cases are filtered by the person's current team, so one organisation does not see another organisation's records.

## Architecture

### Team design

The feature extends Jetstream teams with business fields.

1. The `teams` table has extra columns for business organisations.
2. Each member's team role is stored in `team_user_roles`.
3. Team roles are labels inside the organisation. They are never platform staff roles and never become global roles.
4. Each organisation has a configurable member limit.

### Data isolation

The `App\Traits\BelongsToTeam` trait adds the isolation at the model level.
Two models use it, `App\Domain\Account\Models\Account` and `App\Domain\Fraud\Models\FraudCase`.

```php
use App\Traits\BelongsToTeam;

class Account extends Model
{
    use BelongsToTeam;

    // Automatically filters by current team
    // Automatically sets team_id on creation
}
```

## Features

### 1. Business organisation registration

A person who registers as a business customer gets the global `customer_business` role.
Their personal team becomes a business organisation with `is_business_organization = true` and `organization_type = business`.
The team gets a member limit of 10 and the allowed roles `accountant`, `operations_manager` and `customer_service`.
The owner gets the team role `owner`.

### 2. Team member management

Only the team owner can manage members. `TeamPolicy::update()` checks this.
The owner can add members up to the organisation's limit and give each one a team role.
Adding members only works for business organisations. Other teams get HTTP 403.
The owner can change a member's role and remove a member.
The owner's own role cannot be edited, and the owner cannot be removed.
A request about a user who is not in the team gets HTTP 404.

### 3. Available team roles

A business team offers the roles in its `allowed_roles` list, or the three defaults when no list is stored.
A team that is not a business organisation offers no roles.
`Team::getAvailableRoles()` always removes platform staff role names, such as `admin`, `compliance_officer` or `risk_manager`, even when an older team row still lists them.

| Role | Description |
|------|-------------|
| **Accountant** | A member who works on the organisation's financial records |
| **Operations Manager** | A member who runs daily operations |
| **Customer Service** | A member who helps the organisation's customers |

A team role is a label inside the organisation.
It does not grant platform permissions such as staff compliance or admin access.
Every member added by an owner gets the global `customer_business` role.

### 4. Data isolation mechanisms

#### Global scope

Every model that uses `BelongsToTeam` filters queries by the signed-in person's current team.

```php
// Only shows accounts for the current team
$accounts = Account::all();

// Code that must see every team bypasses the scope explicitly:
$allAccounts = Account::allTeams()->get();

// Or reads one specific team:
$teamAccounts = Account::forTeam($teamId)->get();
```

The scope is skipped for console commands in the `testing` environment.

#### Automatic team association

When a record is created, the trait sets `team_id` to the current team.

```php
// Automatically sets team_id to current team
$account = Account::create([
    'name' => 'Business Account',
    'type' => 'business',
]);
```

## Implementation Details

### Database schema

#### Business columns on the teams table

```sql
-- Added fields to teams table
is_business_organization BOOLEAN DEFAULT FALSE
organization_type VARCHAR(255) NULLABLE
business_registration_number VARCHAR(255) NULLABLE
tax_id VARCHAR(255) NULLABLE
business_details JSON NULLABLE
max_users INTEGER DEFAULT 5
allowed_roles JSON NULLABLE
```

#### Team user roles table

```sql
CREATE TABLE team_user_roles (
    id BIGINT PRIMARY KEY,
    team_id BIGINT FOREIGN KEY,
    user_id BIGINT FOREIGN KEY,
    role VARCHAR(255),
    permissions JSON NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

#### Tables with a team column

The migration adds `team_id` to these tables:
- accounts
- transactions
- fraud_cases
- regulatory_reports

Only `accounts` and `fraud_cases` are filtered automatically, because only their models use `BelongsToTeam`.

### Routes and controller

`App\Http\Controllers\TeamMemberController` serves these web routes:

| Method | Route | Action |
|--------|-------|--------|
| GET | `/teams/{team}/members` | `index()` lists the members |
| GET | `/teams/{team}/members/create` | `create()` shows the add form |
| POST | `/teams/{team}/members` | `store()` creates a member |
| GET | `/teams/{team}/members/{user}/edit` | `edit()` shows the edit form |
| PUT | `/teams/{team}/members/{user}` | `update()` changes the member's role |
| DELETE | `/teams/{team}/members/{user}` | `destroy()` removes the member |

### Team model

`App\Models\Team` adds these methods:

```php
public function hasReachedUserLimit(): bool
{
    return $this->users()->count() >= $this->max_users;
}

public function assignUserRole($user, $role, $permissions = null)
{
    return $this->teamUserRoles()->updateOrCreate(
        ['user_id' => $user->id],
        ['role' => $role, 'permissions' => $permissions]
    );
}

public function getUserTeamRole($user);   // the member's team_user_roles row
public function getAvailableRoles(): array; // allowed_roles minus staff roles
```

### BelongsToTeam trait

The trait adds the `team` global scope for filtering.
It sets `team_id` when a model is created.
It adds the `allTeams()` and `forTeam($teamId)` scopes and a `team()` relation.

## Usage Examples

### Creating a team member

```php
// TeamMemberController::store() (simplified)
$this->authorize('update', $team);

if (! $team->is_business_organization) {
    abort(403, 'This feature is only available for business organizations.');
}

if ($team->hasReachedUserLimit()) {
    return redirect()->route('teams.members.index', $team)
        ->with('error', 'Your team has reached the maximum number of users.');
}

$validated = $request->validate([
    'name'     => ['required', 'string', 'max:255'],
    'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
    'password' => ['required', 'string', Password::defaults()],
    'role'     => ['required', 'string', 'in:' . implode(',', $team->getAvailableRoles())],
]);

DB::transaction(function () use ($team, $validated): void {
    $user = User::create([
        'name'     => $validated['name'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
    ]);

    $team->users()->attach($user);
    $user->current_team_id = $team->id;
    $user->save();

    // The team role stays in team_user_roles; it never becomes a global role.
    $user->assignRole('customer_business');
    $team->assignUserRole($user, $validated['role']);
});
```

### Checking a team role

```php
$teamRole = $team->getUserTeamRole($user);
if ($teamRole && $teamRole->role === 'accountant') {
    // Is an accountant in this organisation
}
```

## Security Considerations

### Data isolation

The global scope filters accounts and fraud cases at the query level.
Code that needs every team must call `allTeams()` or `forTeam()` on purpose.

### Role assignment

Only the team owner can manage members.
A role must be one of the team's available roles.
Team roles are never copied into global roles. Platform staff roles are assigned separately and cannot be offered as team roles.

### Authentication and authorisation

The routes use standard Laravel web authentication.
The controller checks team ownership through `TeamPolicy` on every action.
Platform permissions come from the global roles managed by Spatie Permission.

## Testing

`tests/Feature/Security/Ghsa385wTeamRolesTest.php` covers the business member rules.
It checks that staff role names are refused as team roles, that team roles never become global roles, and that only the owner can manage members.

The Jetstream team tests cover the standard team flows:
- `tests/Feature/InviteTeamMemberTest.php`
- `tests/Feature/UpdateTeamMemberRoleTest.php`
- `tests/Feature/RemoveTeamMemberTest.php`
- `tests/Feature/Actions/Jetstream/AddTeamMemberTest.php`

## Future Enhancements

1. More specific permissions for each team role
2. Custom roles defined by each business
3. An audit log of team member actions
4. Departments or sub-teams inside one organisation
5. Single sign-on for business organisations

## Migration Guide

To enable business team management on an existing install:

1. Run the migrations:
```bash
php artisan migrate
```

2. Mark existing business teams:
```php
Team::where('personal_team', false)->update([
    'is_business_organization' => true,
    'max_users' => 10,
    'allowed_roles' => ['accountant', 'operations_manager', 'customer_service'],
]);
```

3. Add `team_id` to existing accounts:
```php
Account::whereNull('team_id')->each(function ($account) {
    $user = User::where('uuid', $account->user_uuid)->first();
    if ($user && $user->currentTeam) {
        $account->update(['team_id' => $user->currentTeam->id]);
    }
});
```
