<?php

namespace App\Helpers;

class SchemaHelper
{
    /**
     * Generate Organization schema.
     */
    public static function organization(): string
    {
        $brand = config('brand.name', 'Zelta');
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $brand,
            'url'      => config('app.url'),
            'logo'     => config('app.url') . '/images/og-default.png',
            'sameAs'   => array_values(array_filter([
                config('brand.github_url', 'https://github.com/FinAegis'),
                config('brand.twitter_url'),
                config('brand.linkedin_url'),
            ])),
            'contactPoint' => [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer support',
                'email'       => config('brand.support_email', 'info@finaegis.org'),
                'url'         => config('app.url') . '/support/contact',
            ],
            'description'  => $brand . ' — Non-custodial stablecoin wallet with passkey sign-in, virtual Visa & Mastercard cards, bank-rail deposits, and an agent-callable MCP API. Six networks: Solana, Tron, Polygon, Base, Arbitrum, Ethereum.',
            'foundingDate' => '2024',
            'slogan'       => config('brand.tagline', 'No seed phrase. Tap to pay. Truly yours.'),
            'knowsAbout'   => [
                'Non-Custodial Wallets',
                'Stablecoin Cards',
                'Virtual Visa & Mastercard',
                'Passkey Authentication',
                'Agent-Callable Payment API',
                'Financial Technology',
            ],
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate WebSite schema with search action.
     */
    public static function website(): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => config('brand.name', 'Zelta'),
            'url'      => config('app.url'),
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate SoftwareApplication schema.
     */
    public static function softwareApplication(): string
    {
        $brand = config('brand.name', 'Zelta');
        $schema = [
            '@context'            => 'https://schema.org',
            '@type'               => 'MobileApplication',
            'name'                => $brand,
            'operatingSystem'     => 'Android',
            'applicationCategory' => 'FinanceApplication',
            'installUrl'          => 'https://play.google.com/store/apps/details?id=com.zelta.wallet',
            'offers'              => [
                [
                    '@type'         => 'Offer',
                    'price'         => '0',
                    'priceCurrency' => 'USD',
                ],
            ],
            'description' => 'Non-custodial stablecoin wallet with passkey sign-in, virtual Visa & Mastercard cards, bank-rail deposits, and an agent-callable MCP API. Six networks (Solana, Tron, Polygon, Base, Arbitrum, Ethereum). In open testing on Android.',
            'developer'   => [
                '@type' => 'Organization',
                'name'  => $brand,
            ],
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate Product schema for GCU.
     */
    public static function gcuProduct(): string
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => 'Global Currency Unit (GCU)',
            'description' => 'The world\'s first democratically governed basket currency with real bank backing and government insurance.',
            'brand'       => [
                '@type' => 'Brand',
                'name'  => config('brand.name', 'Zelta'),
            ],
            'category'    => 'Digital Currency',
            'isRelatedTo' => [
                '@type' => 'FinancialProduct',
                'name'  => 'Stable Digital Currency',
            ],
            'offers' => [
                '@type'           => 'Offer',
                'availability'    => 'https://schema.org/InStock',
                'price'           => '1.00',
                'priceCurrency'   => 'USD',
                'priceValidUntil' => date('Y-12-31'),
            ],
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate FAQ schema.
     */
    public static function faq(array $faqs): string
    {
        $faqItems = [];

        foreach ($faqs as $faq) {
            $faqItems[] = [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['answer'],
                ],
            ];
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqItems,
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate BreadcrumbList schema.
     */
    public static function breadcrumb(array $items): string
    {
        $breadcrumbItems = [];

        foreach ($items as $position => $item) {
            $breadcrumbItems[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate Service schema for sub-products.
     */
    public static function service(string $name, string $description, string $category): string
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => $name,
            'description' => $description,
            'provider'    => [
                '@type' => 'Organization',
                'name'  => config('brand.name', 'Zelta'),
            ],
            'serviceType'     => $category,
            'areaServed'      => 'Global',
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name'  => $name . ' Services',
            ],
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate Article schema for blog posts.
     */
    public static function article(array $data): string
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Article',
            'headline'    => $data['title'],
            'description' => $data['description'],
            'author'      => [
                '@type' => 'Organization',
                'name'  => config('brand.name', 'Zelta'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => config('brand.name', 'Zelta'),
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => config('app.url') . '/images/og-default.png',
                ],
            ],
            'datePublished'    => $data['published_at'] ?? now()->toIso8601String(),
            'dateModified'     => $data['updated_at'] ?? now()->toIso8601String(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $data['url'],
            ],
        ];

        if (isset($data['image'])) {
            $schema['image'] = $data['image'];
        }

        return self::generateScript($schema);
    }

    /**
     * Generate the script tag with JSON-LD.
     */
    private static function generateScript(array $schema): string
    {
        $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }
}
