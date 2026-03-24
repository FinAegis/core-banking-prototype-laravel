<!-- Navigation -->
<nav id="main-nav" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 border-b border-white/[0.04]" style="color:#fff;background:rgba(12,18,34,0.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-0.5 group">
                    <span class="text-xl font-display font-bold text-white tracking-tight">Fin</span>
                    <span class="text-xl font-display font-bold text-gradient tracking-tight">Aegis</span>
                </a>

                <!-- Main Navigation -->
                <div class="hidden md:ml-10 md:flex md:items-center md:space-x-1">
                    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'nav-link-active' : '' }}">Home</a>

                    <!-- Products Dropdown -->
                    <div class="relative group" data-dropdown>
                        <button class="nav-link inline-flex items-center {{ request()->routeIs(['platform*', 'features*', 'ai-framework*']) ? 'nav-link-active' : '' }}" aria-haspopup="true" aria-expanded="false">
                            Products
                            <svg class="ml-1 h-3.5 w-3.5 opacity-50 transition-transform duration-200 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute left-0 mt-1 w-56 rounded-lg bg-fa-navy-light/95 backdrop-blur-xl border border-white/[0.06] shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 origin-top-left scale-95 group-hover:scale-100" role="menu">
                            <div class="p-1.5">
                                <a href="{{ route('platform') }}" class="dropdown-link {{ request()->routeIs('platform*') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">Core Banking</div>
                                        <div class="text-xs text-slate-500">Platform overview</div>
                                    </div>
                                </a>
                                <a href="{{ route('features') }}" class="dropdown-link {{ request()->routeIs('features*') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-teal-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">All Features</div>
                                        <div class="text-xs text-slate-500">49 domain modules</div>
                                    </div>
                                </a>
                                <a href="{{ route('gcu') }}" class="dropdown-link {{ request()->routeIs('gcu*') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm font-bold text-amber-400">&#x01A4;</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">Global Currency Unit</div>
                                        <div class="text-xs text-slate-500">Democratic basket currency</div>
                                    </div>
                                </a>
                                <a href="{{ route('ai-framework') }}" class="dropdown-link {{ request()->routeIs('ai-framework*') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-purple-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">AI Framework</div>
                                        <div class="text-xs text-slate-500">MCP, A2A, ML analytics</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Resources Dropdown -->
                    <div class="relative group" data-dropdown>
                        <button class="nav-link inline-flex items-center {{ request()->routeIs(['developers*', 'support*', 'about']) ? 'nav-link-active' : '' }}" aria-haspopup="true" aria-expanded="false">
                            Resources
                            <svg class="ml-1 h-3.5 w-3.5 opacity-50 transition-transform duration-200 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute left-0 mt-1 w-56 rounded-lg bg-fa-navy-light/95 backdrop-blur-xl border border-white/[0.06] shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 origin-top-left scale-95 group-hover:scale-100" role="menu">
                            <div class="p-1.5">
                                <a href="{{ route('developers') }}" class="dropdown-link {{ request()->routeIs('developers*') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-green-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">Developers</div>
                                        <div class="text-xs text-slate-500">Guides, SDKs, integration</div>
                                    </div>
                                </a>
                                <a href="/api/documentation" class="dropdown-link">
                                    <div class="w-8 h-8 rounded-md bg-sky-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">API Docs</div>
                                        <div class="text-xs text-slate-500">OpenAPI reference</div>
                                    </div>
                                </a>
                                <a href="{{ route('support') }}" class="dropdown-link {{ request()->routeIs('support*') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-orange-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">Support</div>
                                        <div class="text-xs text-slate-500">Help center & guides</div>
                                    </div>
                                </a>
                                <a href="{{ route('about') }}" class="dropdown-link {{ request()->routeIs('about') ? 'dropdown-link-active' : '' }}">
                                    <div class="w-8 h-8 rounded-md bg-slate-500/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-200">About</div>
                                        <div class="text-xs text-slate-500">Our mission</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('pricing') }}" class="nav-link {{ request()->routeIs('pricing') ? 'nav-link-active' : '' }}">Pricing</a>
                    <a href="{{ route('cgo') }}" class="nav-link {{ request()->routeIs('cgo*') ? 'nav-link-active' : '' }}">Invest</a>
                </div>
            </div>

            <!-- Right Navigation -->
            <div class="hidden md:flex items-center space-x-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="nav-link">Logout</button>
                    </form>
                @endauth
                @guest
                    <a href="{{ route('login') }}" class="nav-link">Sign in</a>
                    <a href="{{ route('register') }}" class="btn-primary !py-2 !px-5 !text-sm !rounded-md">Get Started</a>
                @endguest
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button id="mobile-menu-button" class="text-slate-400 hover:text-white p-2 rounded-md transition-colors" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div id="mobile-menu" class="hidden md:hidden bg-fa-navy-light/95 backdrop-blur-xl border-t border-white/[0.04]">
        <div class="px-3 pt-3 pb-4 space-y-1">
            <a href="{{ route('home') }}" class="mobile-nav-link {{ request()->routeIs('home') ? 'mobile-nav-link-active' : '' }}">Home</a>

            <div class="border-t border-white/[0.04] pt-2 mt-2">
                <div class="px-3 py-1.5 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Products</div>
                <a href="{{ route('platform') }}" class="mobile-nav-link">Core Banking</a>
                <a href="{{ route('features') }}" class="mobile-nav-link">All Features</a>
                <a href="{{ route('gcu') }}" class="mobile-nav-link">Global Currency Unit</a>
                <a href="{{ route('ai-framework') }}" class="mobile-nav-link">AI Framework</a>
            </div>

            <div class="border-t border-white/[0.04] pt-2 mt-2">
                <div class="px-3 py-1.5 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Resources</div>
                <a href="{{ route('developers') }}" class="mobile-nav-link">Developers</a>
                <a href="/api/documentation" class="mobile-nav-link">API Docs</a>
                <a href="{{ route('support') }}" class="mobile-nav-link">Support</a>
                <a href="{{ route('about') }}" class="mobile-nav-link">About</a>
            </div>

            <a href="{{ route('pricing') }}" class="mobile-nav-link">Pricing</a>
            <a href="{{ route('cgo') }}" class="mobile-nav-link">Invest</a>

            <div class="border-t border-white/[0.04] pt-3 mt-2 space-y-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="mobile-nav-link">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="mobile-nav-link w-full text-left">Logout</button>
                    </form>
                @endauth
                @guest
                    <a href="{{ route('login') }}" class="mobile-nav-link">Sign in</a>
                    <a href="{{ route('register') }}" class="block mx-3 text-center btn-primary !py-2.5 !text-sm !rounded-md">Get Started</a>
                @endguest
            </div>
        </div>
    </div>
</nav>

<!-- Nav spacer (fixed nav needs content push) -->
<div class="h-16"></div>

<style>
    /* Nav links — inline color ensures visibility even before Tailwind loads */
    #main-nav .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(203, 213, 225, 1); /* slate-300 — WCAG AA 4.5:1+ on dark nav */
        border-radius: 0.375rem;
        transition: color 0.2s;
    }
    #main-nav .nav-link:hover { color: #fff; }
    #main-nav .nav-link-active { color: #fff; }

    .dropdown-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.75rem;
        border-radius: 0.375rem;
        color: rgba(148, 163, 184, 1);
        transition: all 0.15s;
    }
    .dropdown-link:hover { color: #fff; background: rgba(255,255,255,0.04); }
    .dropdown-link-active { color: #fff; background: rgba(255,255,255,0.04); }

    .mobile-nav-link {
        display: block;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(148, 163, 184, 1);
        border-radius: 0.375rem;
        transition: color 0.2s;
    }
    .mobile-nav-link:hover { color: #fff; background: rgba(255,255,255,0.04); }
    .mobile-nav-link-active { color: #fff; background: rgba(255,255,255,0.04); }

    /* Scroll state — nav solidifies */
    #main-nav.nav-scrolled {
        background: rgba(12, 18, 34, 0.92);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom-color: rgba(255,255,255,0.04);
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
</style>

<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        const button = this;
        const menu = document.getElementById('mobile-menu');
        const isOpen = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', !isOpen);
        menu.classList.toggle('hidden');
    });

    // Keyboard-accessible dropdowns
    document.querySelectorAll('[data-dropdown]').forEach(function(dropdown) {
        const btn = dropdown.querySelector('button[aria-haspopup]');
        const menu = dropdown.querySelector('[role="menu"]');
        const links = menu ? menu.querySelectorAll('a') : [];

        function open() {
            btn.setAttribute('aria-expanded', 'true');
            dropdown.classList.add('dropdown-open');
            menu.classList.add('!opacity-100', '!visible', '!scale-100');
        }
        function close() {
            btn.setAttribute('aria-expanded', 'false');
            dropdown.classList.remove('dropdown-open');
            menu.classList.remove('!opacity-100', '!visible', '!scale-100');
        }

        btn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                open();
                if (links.length) links[0].focus();
            }
        });

        menu.addEventListener('keydown', function(e) {
            var idx = Array.from(links).indexOf(document.activeElement);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (idx < links.length - 1) links[idx + 1].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (idx > 0) links[idx - 1].focus();
                else { close(); btn.focus(); }
            } else if (e.key === 'Escape') {
                close();
                btn.focus();
            }
        });

        dropdown.addEventListener('focusout', function(e) {
            setTimeout(function() {
                if (!dropdown.contains(document.activeElement)) close();
            }, 0);
        });
    });

    // Nav scroll effect
    (function() {
        const nav = document.getElementById('main-nav');
        let ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    if (window.scrollY > 20) {
                        nav.classList.add('nav-scrolled');
                    } else {
                        nav.classList.remove('nav-scrolled');
                    }
                    ticking = false;
                });
                ticking = true;
            }
        });
    })();
</script>
