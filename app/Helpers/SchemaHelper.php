<?php

namespace App\Helpers;

class SchemaHelper
{
    /**
     * Generate Organization schema.
     */
    public static function organization(): string
    {
        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'Organization',
            'name'          => 'FinAegis',
            'alternateName' => 'FinAegis Core Banking Platform',
            'url'           => config('app.url'),
            'logo'          => config('app.url') . '/images/logo.png',
            'sameAs'        => [
                'https://github.com/FinAegis',
                'https://twitter.com/FinAegis',
                'https://linkedin.com/company/finaegis',
            ],
            'contactPoint' => [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer support',
                'email'       => 'info@finaegis.org',
                'url'         => config('app.url') . '/support/contact',
            ],
            'description'  => 'FinAegis is an enterprise financial platform powering the future of banking with democratic governance and real bank integration.',
            'foundingDate' => '2024',
            'slogan'       => 'Powering the Future of Banking',
            'knowsAbout'   => [
                'Core Banking',
                'Financial Technology',
                'Global Currency Unit (GCU)',
                'Democratic Banking',
                'Open Banking API',
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
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => 'FinAegis',
            'url'             => config('app.url'),
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => config('app.url') . '/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate SoftwareApplication schema.
     */
    public static function softwareApplication(): string
    {
        $schema = [
            '@context'            => 'https://schema.org',
            '@type'               => 'SoftwareApplication',
            'name'                => 'FinAegis Core Banking Platform',
            'operatingSystem'     => 'Linux, macOS, Windows',
            'applicationCategory' => 'FinanceApplication',
            'offers'              => [
                [
                    '@type'         => 'Offer',
                    'price'         => '0',
                    'priceCurrency' => 'USD',
                    'name'          => 'Community Edition',
                ],
                [
                    '@type' => 'Offer',
                    'price' => 'Contact for pricing',
                    'name'  => 'Enterprise Edition',
                ],
            ],
            'softwareVersion'      => '1.0',
            'softwareRequirements' => 'PHP 8.2+, MySQL 8.0+, Redis',
            'permissions'          => 'MIT License',
            'developer'            => [
                '@type' => 'Organization',
                'name'  => 'FinAegis',
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
                'name'  => 'FinAegis',
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
                'priceValidUntil' => 'Dynamic',
            ],
            'aggregateRating' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => '4.8',
                'reviewCount' => '150',
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
                'name'  => 'FinAegis',
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
                'name'  => 'FinAegis',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => 'FinAegis',
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => config('app.url') . '/images/logo.png',
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
