<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * @OA\Tag(
 *     name="Team Members",
 *     description="Team member management and role assignment"
 * )
 */
class TeamMemberController extends Controller
{
    /**
     * @OA\Get(
     *     path="/team/members",
     *     operationId="teamMembersIndex",
     *     tags={"Team Members"},
     *     summary="List team members",
     *     description="Returns the team members management page",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Team $team)
    {
        $this->authorize('update', $team);

        // Only business organizations can manage members
        if (! $team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }

        $members = $team->users()->with(['roles'])->get();
        $teamRoles = $team->teamUserRoles;
        $availableRoles = $team->getAvailableRoles();

        return view('teams.members.index', compact('team', 'members', 'teamRoles', 'availableRoles'));
    }

    /**
     * @OA\Get(
     *     path="/team/members/create",
     *     operationId="teamMembersCreate",
     *     tags={"Team Members"},
     *     summary="Show invite member form",
     *     description="Shows the form to invite a new team member",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function create(Team $team)
    {
        $this->authorize('update', $team);

        if (! $team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }

        if ($team->hasReachedUserLimit()) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Your team has reached the maximum number of users.');
        }

        $availableRoles = $team->getAvailableRoles();

        return view('teams.members.create', compact('team', 'availableRoles'));
    }

    /**
     * @OA\Post(
     *     path="/team/members",
     *     operationId="teamMembersStore",
     *     tags={"Team Members"},
     *     summary="Invite team member",
     *     description="Invites a new member to the team",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        if (! $team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }

        if ($team->hasReachedUserLimit()) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Your team has reached the maximum number of users.');
        }

        $validated = $request->validate(
            [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', Password::defaults()],
                'role'     => ['required', 'string', 'in:' . implode(',', $team->getAvailableRoles())],
            ]
        );

        // Create the user
        $user = User::create(
            [
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]
        );

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

    /**
     * @OA\Get(
     *     path="/team/members/{id}/edit",
     *     operationId="teamMembersEdit",
     *     tags={"Team Members"},
     *     summary="Show edit member form",
     *     description="Shows the form to edit a team member",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function edit(Team $team, User $user)
    {
        $this->authorize('update', $team);

        if (! $team->is_business_organization) {
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

    /**
     * @OA\Put(
     *     path="/team/members/{id}",
     *     operationId="teamMembersUpdate",
     *     tags={"Team Members"},
     *     summary="Update team member",
     *     description="Updates a team member role or details",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, Team $team, User $user)
    {
        $this->authorize('update', $team);

        if (! $team->is_business_organization) {
            abort(403, 'This feature is only available for business organizations.');
        }

        if ($team->user_id === $user->id) {
            return redirect()->route('teams.members.index', $team)
                ->with('error', 'Cannot edit the team owner\'s role.');
        }

        $validated = $request->validate(
            [
                'role' => ['required', 'string', 'in:' . implode(',', $team->getAvailableRoles())],
            ]
        );

        // Update global role
        $this->assignGlobalRole($user, $validated['role']);

        // Update team role
        $team->assignUserRole($user, $validated['role']);

        return redirect()->route('teams.members.index', $team)
            ->with('success', 'Team member role updated successfully.');
    }

    /**
     * @OA\Delete(
     *     path="/team/members/{id}",
     *     operationId="teamMembersDestroy",
     *     tags={"Team Members"},
     *     summary="Remove team member",
     *     description="Removes a member from the team",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy(Team $team, User $user)
    {
        $this->authorize('update', $team);

        if (! $team->is_business_organization) {
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
     * Assign appropriate global role based on team role.
     */
    private function assignGlobalRole(User $user, string $teamRole)
    {
        // Remove existing roles
        $user->roles()->detach();

        // Assign new role
        $user->assignRole($teamRole);
    }
}
