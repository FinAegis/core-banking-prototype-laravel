<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = [
            // Platform Updates
            [
                'title'   => 'Event Sourcing: How FinAegis Keeps a Full History of Every Change',
                'slug'    => 'event-sourcing-architecture-financial-platform',
                'excerpt' => 'FinAegis stores every change to an account as an event. This article explains how that works and what it gives you.',
                'content' => <<<'EOT'
## Every Change Is an Event

FinAegis is built on event sourcing. The platform does not overwrite the current state of an account. It stores every change as an event that is never edited afterwards.

### Why Event Sourcing?

A normal database keeps only the latest state. You can see the balance, but not every step that led to it. Event sourcing keeps every step.

This gives you four things:

- You can rebuild the state of an account at any point in time.
- You have a complete audit trail of what happened and when.
- You can test compliance rules against real history.
- You can build reports from the full history.

### How It Works in the Code

FinAegis uses the Spatie event sourcing package for Laravel.

**Aggregates**: An aggregate is an object such as an account or a transfer. It receives a command, checks the rules and records events.

**Event tables**: Each domain writes its events to its own table. Event Store v2 routes events to the right table and can upgrade old event formats.

**Projectors**: A projector reads events and builds the tables that the application queries. These read models can be rebuilt from the events at any time.

**Sagas and workflows**: Some operations have several steps. A saga or workflow runs the steps in order and undoes the finished steps when a later step fails.

### What This Gives You

1. **Audits**: An auditor can follow every change to an account in the order it happened.
2. **Disputes**: You can replay the events of a transaction to see exactly what happened.
3. **Recovery**: You can rebuild the read models from the event log.
4. **Reporting**: Historical analysis is simpler because every change is recorded.

### Read the Code

The platform is open source under the Apache 2.0 licence. You can read the aggregates, projectors and sagas in the repository on GitHub.
EOT,
                'category'        => 'platform',
                'author_name'     => 'Tech Team',
                'author_role'     => 'Engineering',
                'author_initials' => 'TT',
                'reading_time'    => 2,
                'gradient_from'   => 'green-500',
                'gradient_to'     => 'blue-600',
                'is_published'    => true,
                'published_at'    => now()->subDays(7),
            ],

            [
                'title'   => 'Batch Processing: Running Many Transactions as One Job',
                'slug'    => 'batch-processing-efficient-transactions',
                'excerpt' => 'The batch module runs many transfers, payments or conversions as one job. It validates the job first and undoes finished items when the job fails.',
                'content' => <<<'EOT'
## What the Batch Module Does

The batch module lets you submit many transactions as one job. A job can contain transfers, payments and currency conversions.

### How a Batch Job Runs

A batch job runs as a workflow with three stages.

1. **Validation**: The workflow checks the whole job before anything moves.
2. **Processing**: The workflow processes each item in turn and records the result of each one.
3. **Completion**: The workflow marks the job as complete and records a summary.

If a step fails, the workflow runs compensation. Compensation undoes the items that were already processed.

### Events and History

The batch module is event-sourced like the rest of the platform. It records an event when a job is created, started, completed or cancelled. It also records an event for every processed item. You can follow the history of any job from these events.

### Using the API

The API has endpoints to list batch operations, start a batch, check its status and cancel it.

```http
POST /api/batch-operations/execute
GET  /api/batch-operations/{batchId}/status
POST /api/batch-operations/{batchId}/cancel
```

### Things to Know

The batch module is a reference implementation. Payment items are currently processed in the same way as transfers. Conversions use a simplified rate calculation. Test the module with your own data and rules before you rely on it.
EOT,
                'category'        => 'platform',
                'author_name'     => 'Product Manager',
                'author_role'     => 'Product',
                'author_initials' => 'PM',
                'reading_time'    => 2,
                'gradient_from'   => 'red-400',
                'gradient_to'     => 'pink-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(10),
            ],

            // Security
            [
                'title'   => 'Post-Quantum Encryption in FinAegis',
                'slug'    => 'quantum-resistant-cryptography-implementation',
                'excerpt' => 'The security module includes encryption and signatures that combine classical algorithms with a post-quantum layer. This article explains what is there today.',
                'content' => <<<'EOT'
## Why Post-Quantum Encryption Matters

Large quantum computers could one day break the public key algorithms that most systems use today. Data that is stolen now could be decrypted later. Post-quantum algorithms are designed to resist that attack.

### What FinAegis Includes

The security module has three services.

**Key encapsulation**: This service is modelled on ML-KEM-768. It combines X25519 key agreement with a post-quantum key derivation layer.

**Signatures**: This service is modelled on ML-DSA-65. It combines Ed25519 signatures with a hash-based signature layer. A signature is accepted only when both parts verify.

**Hybrid encryption and key rotation**: This service encrypts data with the combined keys. A separate service rotates the keys.

### Why a Hybrid Construction?

A hybrid construction uses a classical algorithm and a post-quantum layer together. The data stays protected if one of the two layers is broken.

### What Is Not There Yet

The post-quantum layer is not yet a certified implementation of the standards from the US National Institute of Standards and Technology. Those standards are FIPS 203 for ML-KEM and FIPS 204 for ML-DSA. The code says that the layer will move to the standard algorithms when PHP supports them natively. Do not treat the current layer as a certified implementation.

### Handling Key Material

The services clear sensitive key material from memory after use. They use the libsodium library for the classical algorithms.

### Read the Code

You can read the services in the repository on GitHub. They live in the security domain under the post-quantum services.
EOT,
                'category'        => 'security',
                'author_name'     => 'Security Lead',
                'author_role'     => 'Security Team',
                'author_initials' => 'SL',
                'reading_time'    => 2,
                'gradient_from'   => 'green-400',
                'gradient_to'     => 'blue-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(5),
            ],

            // Developer
            [
                'title'   => 'The FinAegis API: REST, GraphQL, Webhooks and Development Kits',
                'slug'    => 'api-v2-launch-developer-tools',
                'excerpt' => 'FinAegis has a REST API with an OpenAPI reference, a GraphQL API, signed webhooks and software development kits for JavaScript, Python and PHP. This article explains how they fit together.',
                'content' => <<<'EOT'
## How You Talk to FinAegis

FinAegis offers several ways to work with the platform. You can use the REST API, the GraphQL API, webhooks and the official software development kits.

### REST API

The REST API has versioned endpoints under `/api/v1` and `/api/v2`. The OpenAPI reference documents every endpoint. You can open it at `/api/documentation`.

### GraphQL API

The GraphQL API uses Lighthouse for Laravel. It has 45 schema files that cover 43 domains. It also supports subscriptions for real-time updates.

### Webhooks

Webhooks tell your system when something happens. Each delivery is signed so that you can check it came from the platform. A failed delivery is retried with growing delays for up to 24 hours. You can also replay a delivery.

### Software Development Kits

There are official software development kits for JavaScript, Python and PHP. There is also a payment kit for PHP and a command-line tool.

### Idempotency

Some write endpoints accept an `Idempotency-Key` header. If you send the same request twice with the same key, the platform does not run it twice.

```http
POST /api/v1/...
Idempotency-Key: <a new UUID for each request>
```

### Access Tokens

API tokens carry abilities. A read request needs the read ability, and a write request needs the write ability. The platform also limits how many requests a client can send.

### Sandbox

The public sandbox at finaegis.org uses test data only. You can create an account there and try the API without real money.

### Getting Started

1. Create a sandbox account.
2. Create an API token.
3. Install the software development kit for your language.
4. Make your first request.
EOT,
                'category'        => 'developer',
                'author_name'     => 'DevRel Team',
                'author_role'     => 'Developer Relations',
                'author_initials' => 'DR',
                'reading_time'    => 2,
                'gradient_from'   => 'purple-400',
                'gradient_to'     => 'pink-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(8),
            ],

            // Industry
            [
                'title'   => 'Open Banking and Open Finance: What FinAegis Models Today',
                'slug'    => 'future-european-banking-open-finance',
                'excerpt' => 'European rules are opening financial data beyond payment accounts. This article explains what that means and which parts FinAegis models in software.',
                'content' => <<<'EOT'
## From Open Banking to Open Finance

The EU's second Payment Services Directive opened payment accounts to licensed third parties. A customer can let another provider read account data or start a payment. Open Finance is the idea of extending this to other financial products.

### What Open Finance Would Cover

Open Finance would reach beyond payment accounts. It could include:

- investment accounts,
- pensions,
- insurance,
- loans and mortgages.

The European Commission has proposed a regulation on financial data access for this. It is a proposal, and its final form may change.

### What FinAegis Models Today

The open banking module models the consent lifecycle of the Payment Services Directive. It includes account information and payment initiation flows. It has adapters for the Berlin Group NextGenPSD2 standard and the UK Open Banking standard. It also checks the eIDAS certificates of third-party providers.

The module is a reference implementation at the message and logic layer. It runs in demo mode and is switched off on the production site. It has no live bank connections.

### What This Gives You

You can study how consent, account access and payment initiation work in code. You can also use the module as a starting point for your own integration.

### Before You Build on It

A real Open Banking service needs a licence, agreements with banks and a security review. FinAegis does not provide any of these.

FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
EOT,
                'category'        => 'industry',
                'author_name'     => 'Research Team',
                'author_role'     => 'Market Analysis',
                'author_initials' => 'RT',
                'reading_time'    => 2,
                'gradient_from'   => 'yellow-400',
                'gradient_to'     => 'orange-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(12),
            ],

            // Compliance
            [
                'title'   => 'Crypto-Asset Regulation Tooling in FinAegis',
                'slug'    => 'mica-compliance-crypto-asset-services',
                'excerpt' => 'FinAegis has open-source tooling modelled on the EU Markets in Crypto-Assets Regulation. This article explains what the tooling does and what it does not do.',
                'content' => <<<'EOT'
## What the Regulation Covers

The EU Markets in Crypto-Assets Regulation sets rules for crypto-assets in the EU. It covers three kinds of token.

1. **Asset-referenced tokens**: These tokens refer to a basket of assets.
2. **E-money tokens**: These tokens refer to one official currency.
3. **Other crypto-assets**: This group includes utility tokens and other cryptocurrencies.

It also sets rules for crypto-asset service providers.

### What the FinAegis Tooling Does

The regulatory technology module has a service modelled on the regulation.

- It checks that a token white paper contains the required sections.
- It reports the status of reserves recorded by the stablecoin module.
- It checks whether a transaction needs Travel Rule data.

The stablecoin module models minting, redemption, collateral and reserves. The compliance module provides identity checks and anti-money laundering screening through integration adapters.

### What the Tooling Does Not Do

The tooling does not connect to any regulator. It does not file reports for you. It does not make a deployment authorised or compliant.

FinAegis does not hold any authorisation under the regulation. The Global Currency Unit in the demo is a software demonstration and is not offered to anyone.

### Before You Build on It

Read the regulation with your legal advisers. Use the tooling as a starting point and test it against your own obligations.

FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
EOT,
                'category'        => 'compliance',
                'author_name'     => 'Compliance Team',
                'author_role'     => 'Legal & Compliance',
                'author_initials' => 'CT',
                'reading_time'    => 2,
                'gradient_from'   => 'red-500',
                'gradient_to'     => 'yellow-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(15),
            ],

            // Additional Platform Features
            [
                'title'   => 'Workflows and Sagas: Running Multi-Step Operations Safely',
                'slug'    => 'finaegis-workflows-automation',
                'excerpt' => 'FinAegis runs multi-step financial operations as workflows. Each workflow can undo its finished steps when a later step fails.',
                'content' => <<<'EOT'
## Why Workflows?

Many financial operations have several steps. A transfer might check the sender, check the limits, move the money and send a notification. If one step fails, the earlier steps must be undone.

### How FinAegis Runs Them

FinAegis uses Laravel Workflow for this. A workflow is a class that runs activities in order. An activity is one step, such as validating a batch item or moving money.

The workflow engine stores the state of each workflow. A workflow can continue after a restart.

### Compensation

A workflow can register a compensation for each step. A compensation is the action that undoes the step. When a later step fails, the workflow runs the compensations of the finished steps in reverse order.

### Examples in the Code

- The batch module runs a batch job as a workflow.
- The lending module runs a loan application as a workflow.
- The treasury module uses workflows for portfolio rebalancing with approval steps.
- Cross-chain swaps run as a saga.

### Monitoring

Administrators can follow running workflows in the Waterline dashboard. The dashboard requires an administrator account.

### Starting a Workflow

A workflow can start from an API call, from a domain event or from a scheduled job.
EOT,
                'category'        => 'platform',
                'author_name'     => 'Engineering Team',
                'author_role'     => 'Platform Engineering',
                'author_initials' => 'ET',
                'reading_time'    => 2,
                'gradient_from'   => 'indigo-400',
                'gradient_to'     => 'purple-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(18),
            ],

            // Security Update
            [
                'title'   => 'How FinAegis Checks Every Request',
                'slug'    => 'zero-trust-architecture-implementation',
                'excerpt' => 'FinAegis checks the identity, the permissions and the request rate of every call. This article explains the checks that the platform runs.',
                'content' => <<<'EOT'
## Check Every Request

FinAegis does not trust a request because it comes from inside the network. It checks every request on its own.

### Who Is Calling?

**Sign-in**: Users sign in with a password, an email code or a passkey. Two-factor authentication is available. The administrator API routes require it.

**API tokens**: Every API token carries abilities. A read request needs the read ability. A write request needs the write ability. A delete request needs the delete ability.

**Agents**: AI agents connect to the Model Context Protocol server through OAuth. Each token carries scopes and can have its own spending limit.

### What Are They Allowed to Do?

Roles decide what a user can see and change. Administrator pages and the operations dashboards require an administrator role.

### How Often Are They Calling?

Rate limits restrict how many requests a client can send. Separate limits apply to transactions and to GraphQL. The platform can also block an IP address.

### Checking Data From Outside

Incoming webhooks from providers are checked with their signatures before they are processed. Events from providers such as Bridge, Apple and Google are recorded once, so a repeated delivery does not run twice.

### Protecting Stored Data

Sensitive fields are stored encrypted. Integrity checks use keyed hashes. Key material is cleared from memory after use.

### No Certification

These controls support good security practice. They are not a certification. FinAegis holds no SOC 2, PCI DSS or ISO 27001 certification.
EOT,
                'category'        => 'security',
                'author_name'     => 'Security Team',
                'author_role'     => 'Information Security',
                'author_initials' => 'ST',
                'reading_time'    => 2,
                'gradient_from'   => 'green-500',
                'gradient_to'     => 'teal-500',
                'is_published'    => true,
                'published_at'    => now()->subDays(22),
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::create($post);
        }
    }
}
