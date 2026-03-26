<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

readonly class A2AAgentCard
{
    /**
     * @param array<int, array<string, string>> $skills
     * @param array<int, string>                $defaultInputModes
     * @param array<int, string>                $defaultOutputModes
     * @param array<string, mixed>              $authentication
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $url,
        public string $version,
        public array $skills,
        public array $defaultInputModes,
        public array $defaultOutputModes,
        public array $authentication,
        public bool $supportsStreaming,
        public bool $supportsPushNotifications,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'url'         => $this->url,
            'provider'    => [
                'organization' => 'FinAegis',
            ],
            'version'      => $this->version,
            'capabilities' => [
                'streaming'         => $this->supportsStreaming,
                'pushNotifications' => $this->supportsPushNotifications,
            ],
            'authentication'     => $this->authentication,
            'defaultInputModes'  => $this->defaultInputModes,
            'defaultOutputModes' => $this->defaultOutputModes,
            'skills'             => $this->skills,
        ];
    }
}
