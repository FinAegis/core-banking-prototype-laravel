<?php

use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function () {
    $this->policy = new TeamPolicy();
    $this->user = Mockery::mock(User::class);
    $this->team = Mockery::mock(Team::class);
});

it('can instantiate team policy', function () {
    expect($this->policy)->toBeInstanceOf(TeamPolicy::class);
});

it('allows any user to view any teams', function () {
    $result = $this->policy->viewAny($this->user);
    
    expect($result)->toBeTrue();
});

it('allows user to view team if they belong to it', function () {
    // Mock the belongsToTeam method to return true
    $user = Mockery::mock(User::class);
    $user->shouldReceive('belongsToTeam')->with($this->team)->andReturn(true);
    
    $result = $this->policy->view($user, $this->team);
    
    expect($result)->toBeTrue();
});

it('denies user to view team if they do not belong to it', function () {
    // Mock the belongsToTeam method to return false
    $user = Mockery::mock(User::class);
    $user->shouldReceive('belongsToTeam')->with($this->team)->andReturn(false);
    
    $result = $this->policy->view($user, $this->team);
    
    expect($result)->toBeFalse();
});

it('allows any user to create teams', function () {
    $result = $this->policy->create($this->user);
    
    expect($result)->toBeTrue();
});

it('allows team owner to update team', function () {
    // Mock the ownsTeam method to return true
    $user = Mockery::mock(User::class);
    $user->shouldReceive('ownsTeam')->with($this->team)->andReturn(true);
    
    $result = $this->policy->update($user, $this->team);
    
    expect($result)->toBeTrue();
});

it('denies non-owner to update team', function () {
    // Mock the ownsTeam method to return false
    $user = Mockery::mock(User::class);
    $user->shouldReceive('ownsTeam')->with($this->team)->andReturn(false);
    
    $result = $this->policy->update($user, $this->team);
    
    expect($result)->toBeFalse();
});

it('allows team owner to add team members', function () {
    // Mock the ownsTeam method to return true
    $user = Mockery::mock(User::class);
    $user->shouldReceive('ownsTeam')->with($this->team)->andReturn(true);
    
    $result = $this->policy->addTeamMember($user, $this->team);
    
    expect($result)->toBeTrue();
});

it('allows team owner to update team member permissions', function () {
    // Mock the ownsTeam method to return true
    $user = Mockery::mock(User::class);
    $user->shouldReceive('ownsTeam')->with($this->team)->andReturn(true);
    
    $result = $this->policy->updateTeamMember($user, $this->team);
    
    expect($result)->toBeTrue();
});

it('allows team owner to remove team members', function () {
    // Mock the ownsTeam method to return true
    $user = Mockery::mock(User::class);
    $user->shouldReceive('ownsTeam')->with($this->team)->andReturn(true);
    
    $result = $this->policy->removeTeamMember($user, $this->team);
    
    expect($result)->toBeTrue();
});

it('allows team owner to delete team', function () {
    // Mock the ownsTeam method to return true
    $user = Mockery::mock(User::class);
    $user->shouldReceive('ownsTeam')->with($this->team)->andReturn(true);
    
    $result = $this->policy->delete($user, $this->team);
    
    expect($result)->toBeTrue();
});