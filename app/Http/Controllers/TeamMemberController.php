<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class TeamMemberController extends Controller
{
    public function index(Team $team)
    {
        $this->authorize('update', $team);
        
        // Only business organizations can manage members
        if (!$team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }
        
        $members = $team->users()->with(['roles'])->get();
        $teamRoles = $team->teamUserRoles;
        $availableRoles = $team->getAvailableRoles();
        
        return view('teams.members.index', compact('team', 'members', 'teamRoles', 'availableRoles'));
    }
    
    public function create(Team $team)
    {
        $this->authorize('update', $team);
        
        if (!$team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }
        
        if ($team->hasReachedUserLimit()) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Your team has reached the maximum number of users.');
        }
        
        $availableRoles = $team->getAvailableRoles();
        
        return view('teams.members.create', compact('team', 'availableRoles'));
    }
    
    public function store(Request $request, Team $team)
    {
        $this->authorize('update', $team);
        
        if (!$team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }
        
        if ($team->hasReachedUserLimit()) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Your team has reached the maximum number of users.');
        }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', 'in:' . implode(',', $team->getAvailableRoles())],
        ]);
        
        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        
        // Add to team
        $team->users()->attach($user);
        
        // Set as current team for the new user
        $user->current_team_id = $team->id;
        $user->save();
        
        // Assign global role based on team role
        $this->assignGlobalRole($user, $validated['role']);
        
        // Assign team-specific role
        $team->assignUserRole($user, $validated['role']);
        
        return redirect()->route('teams.members.index', $team)
            ->with('success', 'Team member added successfully.');
    }
    
    public function edit(Team $team, User $user)
    {
        $this->authorize('update', $team);
        
        if (!$team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }
        
        // Prevent editing the team owner
        if ($team->user_id === $user->id) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Cannot edit the team owner\'s role.');
        }
        
        $teamRole = $team->getUserTeamRole($user);
        $availableRoles = $team->getAvailableRoles();
        
        return view('teams.members.edit', compact('team', 'user', 'teamRole', 'availableRoles'));
    }
    
    public function update(Request $request, Team $team, User $user)
    {
        $this->authorize('update', $team);
        
        if (!$team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }
        
        if ($team->user_id === $user->id) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Cannot edit the team owner\'s role.');
        }
        
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', $team->getAvailableRoles())],
        ]);
        
        // Update global role
        $this->assignGlobalRole($user, $validated['role']);
        
        // Update team role
        $team->assignUserRole($user, $validated['role']);
        
        return redirect()->route('teams.members.index', $team)
            ->with('success', 'Team member role updated successfully.');
    }
    
    public function destroy(Team $team, User $user)
    {
        $this->authorize('update', $team);
        
        if (!$team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }
        
        // Prevent removing the team owner
        if ($team->user_id === $user->id) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Cannot remove the team owner.');
        }
        
        // Remove from team
        $team->users()->detach($user);
        
        // Remove team role
        $team->teamUserRoles()->where('user_id', $user->id)->delete();
        
        // If this was their current team, clear it
        if ($user->current_team_id === $team->id) {
            $user->current_team_id = null;
            $user->save();
        }
        
        return redirect()->route('teams.members.index', $team)
            ->with('success', 'Team member removed successfully.');
    }
    
    /**
     * Assign appropriate global role based on team role
     */
    private function assignGlobalRole(User $user, string $teamRole)
    {
        // Remove existing roles
        $user->roles()->detach();
        
        // Assign new role
        $user->assignRole($teamRole);
    }
}