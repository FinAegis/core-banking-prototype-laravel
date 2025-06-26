<?php

namespace Tests\Traits;

use App\Models\User;
use Filament\Facades\Filament;

trait InteractsWithFilament
{
    protected ?User $adminUser = null;

    /**
     * Set up Filament for testing with proper authentication
     */
    protected function setUpFilamentWithAuth(): void
    {
        // Create and authenticate as admin user
        $this->adminUser = User::factory()->withAdminRole()->create();
        $this->actingAs($this->adminUser);
        
        // Ensure Filament panel is properly initialized
        $panel = Filament::getPanel('admin');
        
        if ($panel) {
            Filament::setCurrentPanel($panel);
            Filament::setServingStatus(true);
            
            // Boot the panel to ensure all services are initialized
            $panel->boot();
        }
    }
    
    /**
     * Get the authenticated admin user
     */
    protected function getAdminUser(): User
    {
        if (!$this->adminUser) {
            $this->setUpFilamentWithAuth();
        }
        
        return $this->adminUser;
    }
}