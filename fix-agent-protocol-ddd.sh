#!/bin/bash

echo "==================================================="
echo "Agent Protocol DDD Migration Script"
echo "==================================================="

# Step 1: Update all references from App\Models\Agent to App\Domain\AgentProtocol\Models\Agent
echo "Step 1: Updating Agent model references..."
find app -name "*.php" -exec grep -l "App\\\Models\\\Agent" {} \; | while read file; do
    if [[ "$file" != *"Models/Agent"* ]]; then
        echo "  Updating: $file"
        sed -i 's/use App\\Models\\Agent;/use App\\Domain\\AgentProtocol\\Models\\Agent;/g' "$file"
        sed -i 's/App\\Models\\Agent::/App\\Domain\\AgentProtocol\\Models\\Agent::/g' "$file"
    fi
done

# Step 2: Update AgentMessage references
echo "Step 2: Updating AgentMessage references..."
find app -name "*.php" -exec grep -l "App\\\Models\\\AgentMessage" {} \; | while read file; do
    echo "  Updating: $file"
    sed -i 's/use App\\Models\\AgentMessage;/use App\\Domain\\AgentProtocol\\Models\\AgentMessage;/g' "$file"
    sed -i 's/App\\Models\\AgentMessage::/App\\Domain\\AgentProtocol\\Models\\AgentMessage::/g' "$file"
done

# Step 3: Create Repository Interface
echo "Step 3: Creating Repository Interface..."
mkdir -p app/Domain/AgentProtocol/Repositories

cat > app/Domain/AgentProtocol/Repositories/AgentRepositoryInterface.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Support\Collection;

interface AgentRepositoryInterface
{
    public function findByAgentId(string $agentId): ?Agent;

    public function findByDid(string $did): ?Agent;

    public function findActive(): Collection;

    public function findByNetwork(string $networkId): Collection;

    public function findByOrganization(string $organization): Collection;

    public function findWithCapability(string $capability): Collection;

    public function create(array $data): Agent;

    public function update(string $agentId, array $data): bool;

    public function delete(string $agentId): bool;
}
EOF

# Step 4: Create Eloquent Repository Implementation
cat > app/Domain/AgentProtocol/Repositories/EloquentAgentRepository.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Support\Collection;

class EloquentAgentRepository implements AgentRepositoryInterface
{
    public function findByAgentId(string $agentId): ?Agent
    {
        return Agent::where('agent_id', $agentId)->first();
    }

    public function findByDid(string $did): ?Agent
    {
        return Agent::where('did', $did)->first();
    }

    public function findActive(): Collection
    {
        return Agent::active()->get();
    }

    public function findByNetwork(string $networkId): Collection
    {
        return Agent::inNetwork($networkId)->get();
    }

    public function findByOrganization(string $organization): Collection
    {
        return Agent::inOrganization($organization)->get();
    }

    public function findWithCapability(string $capability): Collection
    {
        return Agent::withCapability($capability)->get();
    }

    public function create(array $data): Agent
    {
        return Agent::create($data);
    }

    public function update(string $agentId, array $data): bool
    {
        $agent = $this->findByAgentId($agentId);
        if (!$agent) {
            return false;
        }
        return $agent->update($data);
    }

    public function delete(string $agentId): bool
    {
        $agent = $this->findByAgentId($agentId);
        if (!$agent) {
            return false;
        }
        return $agent->delete();
    }
}
EOF

# Step 5: Create Event Sourcing Repositories
echo "Step 5: Creating Event Sourcing Repositories..."

cat > app/Domain/AgentProtocol/Repositories/AgentEventRepository.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;
use App\Domain\AgentProtocol\Models\AgentProtocolEvent;

class AgentEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel = AgentProtocolEvent::class;
}
EOF

cat > app/Domain/AgentProtocol/Repositories/AgentSnapshotRepository.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;
use App\Domain\AgentProtocol\Models\AgentProtocolSnapshot;

class AgentSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = AgentProtocolSnapshot::class;
}
EOF

# Step 6: Register Service Provider
echo "Step 6: Registering Service Provider in config/app.php..."
if ! grep -q "AgentProtocolServiceProvider" config/app.php; then
    sed -i "/App\\\Providers\\\RouteServiceProvider::class,/a\\        App\\\Providers\\\AgentProtocolServiceProvider::class," config/app.php
    echo "  ✓ AgentProtocolServiceProvider registered"
else
    echo "  ✓ AgentProtocolServiceProvider already registered"
fi

# Step 7: Move AgentMessage to domain if it's in app/Models
echo "Step 7: Checking AgentMessage location..."
if [ -f "app/Models/AgentMessage.php" ]; then
    if [ ! -f "app/Domain/AgentProtocol/Models/AgentMessage.php" ]; then
        echo "  Moving AgentMessage to domain..."
        sed 's/namespace App\\Models;/namespace App\\Domain\\AgentProtocol\\Models;/' app/Models/AgentMessage.php > app/Domain/AgentProtocol/Models/AgentMessage.php
        echo "  ✓ AgentMessage moved to domain"
    fi
fi

# Step 8: Fix AgentReputation location
echo "Step 8: Moving AgentReputation to domain..."
if [ -f "app/Models/AgentReputation.php" ]; then
    sed 's/namespace App\\Models;/namespace App\\Domain\\AgentProtocol\\Models;/' app/Models/AgentReputation.php > app/Domain/AgentProtocol/Models/AgentReputation.php
    rm app/Models/AgentReputation.php
    echo "  ✓ AgentReputation moved to domain"
fi

# Step 9: Update all AgentReputation references
echo "Step 9: Updating AgentReputation references..."
find app tests -name "*.php" -exec grep -l "App\\\Models\\\AgentReputation" {} \; | while read file; do
    echo "  Updating: $file"
    sed -i 's/use App\\Models\\AgentReputation;/use App\\Domain\\AgentProtocol\\Models\\AgentReputation;/g' "$file"
    sed -i 's/App\\Models\\AgentReputation::/App\\Domain\\AgentProtocol\\Models\\AgentReputation::/g' "$file"
done

echo ""
echo "==================================================="
echo "DDD Migration Complete!"
echo "==================================================="
echo ""
echo "Next steps:"
echo "1. Run PHPStan to verify: vendor/bin/phpstan analyse"
echo "2. Run tests to ensure everything works: ./vendor/bin/pest"
echo "3. Clear caches: php artisan cache:clear && php artisan config:clear"
echo ""