# Business Team Management Documentation

## Overview

The Business Team Management feature enables business organizations to create and manage team members with specific roles and permissions. This multi-tenant architecture ensures complete data isolation between organizations while allowing flexible role-based access control within each business.

## Architecture

### Multi-Tenant Design

The system uses Laravel Jetstream's team functionality extended with business-specific features:

1. **Enhanced Teams Table**: Added business organization fields
2. **Team-Specific Roles**: Separate from global system roles
3. **Automatic Data Isolation**: Using global scopes at the model level
4. **Team User Limits**: Configurable per organization

### Data Isolation

Data isolation is implemented at the model level using the `BelongsToTeam` trait:

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

### 1. Business Organization Registration

When a user registers as a business owner:
- A team is automatically created with `is_business_organization = true`
- The owner is assigned the `customer_business` role
- Default team settings are applied

### 2. Team Member Management

Business owners can:
- Add team members up to their organization's limit
- Assign specific roles to team members
- Update team member roles
- Remove team members from the organization

### 3. Available Team Roles

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| **Compliance Officer** | Manages KYC and regulatory compliance | View/manage KYC, Generate reports, View fraud alerts |
| **Risk Manager** | Monitors and manages risk | View/manage fraud cases, Configure risk rules |
| **Accountant** | Handles financial reporting | View financial reports, View all transactions |
| **Operations Manager** | Manages daily operations | Process withdrawals, Reverse transactions |
| **Customer Service** | Assists customers | View/edit customer accounts, View transactions |

### 4. Data Isolation Mechanisms

#### Global Scopes
All models using `BelongsToTeam` automatically filter by the current team:

```php
// Only shows accounts for the current team
$accounts = Account::all();

// Super admins can bypass with:
$allAccounts = Account::allTeams()->get();
```

#### Automatic Team Association
When creating new records, the team_id is automatically set:

```php
// Automatically sets team_id to current team
$account = Account::create([
    'name' => 'Business Account',
    'type' => 'business',
]);
```

## Implementation Details

### Database Schema

#### Enhanced Teams Table
```sql
-- Added fields to teams table
is_business_organization BOOLEAN DEFAULT FALSE
organization_type VARCHAR(255) NULLABLE
business_registration_number VARCHAR(255) NULLABLE
tax_id VARCHAR(255) NULLABLE
max_users INTEGER DEFAULT 5
allowed_roles JSON NULLABLE
```

#### Team User Roles Table
```sql
CREATE TABLE team_user_roles (
    id BIGINT PRIMARY KEY,
    team_id BIGINT FOREIGN KEY,
    user_id BIGINT FOREIGN KEY,
    role VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

#### Team-Aware Tables
The following tables include `team_id` for data isolation:
- accounts
- transactions
- fraud_cases
- regulatory_reports

### Controllers

#### TeamMemberController
Handles all team member CRUD operations:
- `index()` - List team members
- `create()` - Show add member form
- `store()` - Create new team member
- `edit()` - Show edit member form
- `update()` - Update member role
- `destroy()` - Remove team member

### Models

#### Team Model Extensions
```php
class Team extends JetstreamTeam
{
    public function hasReachedUserLimit(): bool
    {
        return $this->users()->count() >= $this->max_users;
    }
    
    public function assignUserRole(User $user, string $role): void
    {
        DB::table('team_user_roles')->updateOrInsert(
            ['team_id' => $this->id, 'user_id' => $user->id],
            ['role' => $role]
        );
    }
}
```

### Traits

#### BelongsToTeam Trait
Provides automatic team filtering and association:
- Adds global scope for team filtering
- Sets team_id on model creation
- Provides `allTeams()` scope for admins

## Usage Examples

### Creating a Team Member

```php
// In controller
public function store(Request $request, Team $team)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:8',
        'role' => 'required|in:' . implode(',', $team->allowed_roles ?? []),
    ]);
    
    // Create user
    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
    ]);
    
    // Add to team
    $team->users()->attach($user);
    $team->assignUserRole($user, $validated['role']);
    
    // Assign global role
    $user->assignRole($validated['role']);
    
    return redirect()->route('teams.members.index', $team);
}
```

### Accessing Team-Filtered Data

```php
// As team member - automatically filtered
$accounts = Account::all(); // Only team accounts

// As super admin - see all data
$allAccounts = Account::allTeams()->get();

// Check specific team data
$teamAccounts = Account::where('team_id', $teamId)->get();
```

### Role-Based Access

```php
// In controllers or policies
if ($user->can('view_fraud_alerts')) {
    // Has permission through their role
}

// Check team-specific role
$teamRole = $team->getUserTeamRole($user);
if ($teamRole && $teamRole->role === 'compliance_officer') {
    // Is compliance officer for this team
}
```

## Security Considerations

### 1. Data Isolation Enforcement
- Global scopes prevent accidental data leaks
- Team filtering happens at the query level
- Super admins must explicitly bypass filtering

### 2. Role Assignment
- Only team owners can manage members
- Roles are restricted to allowed_roles for the team
- Global roles mirror team roles for consistency

### 3. Authentication & Authorization
- Standard Laravel authentication
- Team membership verified on each request
- Role permissions checked via Spatie permissions

## Testing

The feature includes comprehensive tests in `tests/Feature/BusinessTeamManagementTest.php`:

```php
// Test data isolation
test('data_isolation_for_accounts', function () {
    $account1 = Account::factory()->create(['team_id' => $team1->id]);
    $account2 = Account::factory()->create(['team_id' => $team2->id]);
    
    $this->actingAs($team1Owner);
    $visibleAccounts = Account::all();
    
    expect($visibleAccounts)->toContain($account1);
    expect($visibleAccounts)->not->toContain($account2);
});
```

## Future Enhancements

1. **Granular Permissions**: More specific permissions per role
2. **Custom Roles**: Allow businesses to define custom roles
3. **Audit Logging**: Track all team member actions
4. **Team Hierarchies**: Support for departments/sub-teams
5. **SSO Integration**: Enterprise single sign-on support

## Migration Guide

To enable business team management:

1. Run the migration:
```bash
php artisan migrate
```

2. Update existing businesses:
```php
Team::where('personal_team', false)->update([
    'is_business_organization' => true,
    'max_users' => 10,
    'allowed_roles' => ['compliance_officer', 'accountant'],
]);
```

3. Add team_id to existing models:
```php
Account::whereNull('team_id')->each(function ($account) {
    $user = User::where('uuid', $account->user_uuid)->first();
    if ($user && $user->currentTeam) {
        $account->update(['team_id' => $user->currentTeam->id]);
    }
});
```