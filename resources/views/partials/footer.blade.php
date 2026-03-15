<!-- Footer -->
<footer class="bg-fa-navy border-t border-white/[0.04]">
    <!-- Main Footer -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-2 md:grid-cols-12 gap-8 lg:gap-12">
            <!-- Brand Column -->
            <div class="col-span-2 md:col-span-4 lg:col-span-5">
                <a href="/" class="flex items-center gap-2 mb-5">
                    <span class="text-xl font-display font-bold text-white tracking-tight">{{ config('brand.name', 'Zelta') }}</span>
                </a>
                <p class="text-slate-500 text-sm leading-relaxed max-w-xs mb-6">
                    Open-source core banking infrastructure for the next generation of financial services. MIT licensed.
                </p>
                <div class="flex items-center space-x-3">
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="w-9 h-9 rounded-lg bg-white/[0.04] border border-white/[0.06] flex items-center justify-center text-slate-500 hover:text-white hover:border-white/[0.12] transition-all" aria-label="GitHub">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-lg bg-white/[0.04] border border-white/[0.06] flex items-center justify-center text-slate-500 hover:text-white hover:border-white/[0.12] transition-all" aria-label="Twitter">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-lg bg-white/[0.04] border border-white/[0.06] flex items-center justify-center text-slate-500 hover:text-white hover:border-white/[0.12] transition-all" aria-label="LinkedIn">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                </div>
            </div>

            <!-- Product -->
            <div class="col-span-1 md:col-span-2">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Product</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('platform') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Platform</a></li>
                    <li><a href="{{ route('features') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Features</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Pricing</a></li>
                    <li><a href="{{ route('security') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Security</a></li>
                    <li><a href="{{ route('compliance') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Compliance</a></li>
                    <li><a href="{{ route('status') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Status</a></li>
                </ul>
            </div>

            <!-- Developers -->
            <div class="col-span-1 md:col-span-2">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Developers</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('developers') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Developer Hub</a></li>
                    <li><a href="/api/documentation" class="text-sm text-slate-500 hover:text-white transition-colors">API Reference</a></li>
                    <li><a href="{{ route('developers.show', 'sdks') }}" class="text-sm text-slate-500 hover:text-white transition-colors">SDKs</a></li>
                    <li><a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="text-sm text-slate-500 hover:text-white transition-colors">GitHub</a></li>
                    <li><a href="{{ route('developers.show', 'webhooks') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Webhooks</a></li>
                </ul>
            </div>

            <!-- Company -->
            <div class="col-span-1 md:col-span-2">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Company</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('about') }}" class="text-sm text-slate-500 hover:text-white transition-colors">About</a></li>
                    <li><a href="{{ route('blog') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Blog</a></li>
                    <li><a href="{{ route('cgo') }}" class="text-sm text-slate-500 hover:text-white transition-colors">CGO Concept</a></li>
                    <li><a href="{{ route('support.contact') }}" class="text-sm text-slate-500 hover:text-white transition-colors">Contact</a></li>
                    <li><a href="{{ route('support.faq') }}" class="text-sm text-slate-500 hover:text-white transition-colors">FAQ</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="border-t border-white/[0.04]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-xs text-slate-600">&copy; {{ date('Y') }} {{ config('brand.legal_entity', config('brand.name', 'Zelta')) }}. All rights reserved.</p>
                <div class="flex items-center gap-6">
                    <a href="{{ route('legal.terms') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Privacy</a>
                    <a href="{{ route('legal.cookies') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Cookies</a>
                </div>
            </div>
        </div>
    </div>
</footer>
