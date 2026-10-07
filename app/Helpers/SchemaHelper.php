<?php

namespace App\Helpers;

class SchemaHelper
{
    /**
     * Generate Organization schema.
     *
     * Brand-aware like softwareApplication(): the wallet description, slogan
     * and support address describe Zelta specifically. Under any other brand
     * (e.g. the FinAegis demo) a generic open-source description is used and
     * no Zelta slogan or Zelta support address is emitted.
     */
    public static function organization(): string
    {
        $brand = config('brand.name', 'Zelta');
        $isZelta = self::isZeltaBrand();

        $supportEmail = (string) config('brand.support_email', 'info@finaegis.org');
        if (! $isZelta && str_ends_with(strtolower($supportEmail), '@zelta.app')) {
            $supportEmail = 'info@finaegis.org';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $brand,
            'url'      => config('app.url'),
            'logo'     => self::logoUrl(),
            'sameAs'   => array_values(array_filter([
                config('brand.github_url', 'https://github.com/FinAegis'),
                config('brand.twitter_url'),
                config('brand.linkedin_url'),
            ])),
            'contactPoint' => [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer support',
                'email'       => $supportEmail,
                'url'         => config('app.url') . '/support/contact',
            ],
            'description' => $isZelta
                ? $brand . ' — Non-custodial stablecoin wallet software with passkey sign-in and an agent-callable MCP API. Six networks: Solana, Tron, Polygon, Base, Arbitrum, Ethereum.'
                : $brand . ' — open-source core banking software with multi-asset accounts, payment rails, and developer APIs.',
            'foundingDate' => '2024',
        ];

        if ($isZelta) {
            $schema['slogan'] = config('brand.tagline', 'No seed phrase. Truly yours.');
        }

        $schema['knowsAbout'] = [
            'Non-Custodial Wallets',
            'Passkey Authentication',
            'Agent-Callable Payment API',
            'Financial Technology',
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
     *
     * Brand-aware: the Play Store installUrl + Android-wallet description
     * describe the Zelta app specifically. Under any other brand (e.g. the
     * FinAegis demo) the installUrl is omitted and a generic platform
     * description is used, so demo pages never point at the Zelta listing.
     */
    public static function softwareApplication(): string
    {
        $brand = config('brand.name', 'Zelta');
        $isZelta = self::isZeltaBrand();

        $schema = [
            '@context'            => 'https://schema.org',
            '@type'               => 'MobileApplication',
            'name'                => $brand,
            'operatingSystem'     => 'Android',
            'applicationCategory' => 'FinanceApplication',
        ];

        if ($isZelta) {
            $schema['installUrl'] = 'https://play.google.com/store/apps/details?id=com.zelta.wallet';
        }

        $schema['offers'] = [
            [
                '@type'         => 'Offer',
                'price'         => '0',
                'priceCurrency' => 'USD',
            ],
        ];
        $schema['description'] = $isZelta
            ? 'Non-custodial stablecoin wallet software with passkey sign-in and an agent-callable MCP API. Six networks (Solana, Tron, Polygon, Base, Arbitrum, Ethereum). In open testing on Android.'
            : $brand . ' — open-source core banking software with multi-asset accounts, payment rails, and developer APIs.';
        $schema['developer'] = [
            '@type' => 'Organization',
            'name'  => $brand,
        ];

        return self::generateScript($schema);
    }

    /**
     * Generate schema for the GCU demo page.
     *
     * GCU is a software demonstration only (see docs/REGULATORY-CLAIMS.md):
     * it is typed as a CreativeWork, not a Product, and this markup must never
     * advertise an offer, price, availability, backing or insurance.
     */
    public static function gcuProduct(): string
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'CreativeWork',
            'name'        => 'Global Currency Unit (GCU)',
            'description' => 'GCU demo — a reference implementation of a basket-referenced unit built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value.',
            'genre'       => 'Software demonstration',
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
            'serviceType' => $category,
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
                    'url'   => self::logoUrl(),
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
     * Live APP_BRAND is lowercase 'zelta' — compare case-insensitively.
     */
    private static function isZeltaBrand(): bool
    {
        return strtolower((string) config('brand.name', 'Zelta')) === 'zelta';
    }

    /**
     * Neutral brand icon used as the schema logo.
     *
     * TODO(regulatory): stopgap — images/og-default.png shows card artwork;
     * new OG artwork needed (see docs/REGULATORY-CLAIMS.md).
     */
    private static function logoUrl(): string
    {
        return config('app.url') . (self::isZeltaBrand()
            ? '/brand/zelta/android-chrome-512x512.png'
            : '/brand/finaegis/android-chrome-512x512.png');
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
