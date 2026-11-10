<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Aplikasi Stock Barang' }} - PABWE 2026</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="description" content="{{ $description ?? 'Aplikasi Stock Barang untuk mengelola inventaris, memantau stok menipis, serta impor dan ekspor data barang dengan mudah.' }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        {{-- Tailwind hasil build Vite (npm run build): tanpa render-blocking CDN --}}
        @vite('resources/css/app.css')
    @else
        {{-- Fallback jika aset belum di-build --}}
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>
        [x-cloak] { display: none !important; }
        html:has(.modal-scroll-lock),
        body:has(.modal-scroll-lock) {
            overflow: hidden !important;
            overscroll-behavior: none;
        }
    </style>
    @livewireStyles
</head>
<body class="h-full font-sans text-slate-800 antialiased" x-data="{ sidebarOpen: false }">
    @auth
        <!-- Mobile Sidebar Backdrop -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-slate-900/60 z-40 md:hidden" 
             x-cloak></div>

        <!-- Sidebar (Left Fixed) -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 flex flex-col transition-transform duration-300 ease-in-out md:translate-x-0 shadow-sm">
            
            <!-- Brand / Logo -->
            <div class="h-16 flex items-center px-6 border-b border-slate-100 justify-between">
                <a href="{{ auth()->user()->isAdmin() ? route('admin.users') : route('stocks.index') }}" class="flex items-center space-x-2.5 font-bold text-lg text-blue-700 hover:text-blue-900 transition">
                    <span class="p-2 bg-gradient-to-tr bg-linear-to-tr from-blue-600 via-indigo-600 to-purple-600 text-white rounded-xl shadow-md shadow-blue-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <span class="text-slate-900 tracking-tight">StokBarang <span class="bg-gradient-to-r bg-linear-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent font-extrabold">Pro</span></span>
                </a>
                <button type="button" aria-label="Tutup menu navigasi" @click="sidebarOpen = false" class="md:hidden text-slate-600 hover:text-slate-900">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- User Profile Summary in Sidebar -->
            <div class="p-4 mx-3 my-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <div class="flex items-center space-x-3">
                    @if (auth()->user()->profile_photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists(auth()->user()->profile_photo_path))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->profile_photo_path) }}" alt="{{ auth()->user()->name }}" class="w-10 h-10 rounded-full object-cover shadow-sm shrink-0 border border-slate-200">
                    @else
                        <div class="w-10 h-10 rounded-full {{ auth()->user()->isAdmin() ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white' }} flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-600 font-mono truncate">&#64;{{ auth()->user()->username }}</p>
                    </div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-xs text-slate-600">Peran Akun:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ auth()->user()->isAdmin() ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                        {{ ucfirst(auth()->user()->role) }}
                    </span>
                </div>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 px-3 space-y-1 overflow-y-auto">
                <div class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Menu Utama
                </div>

                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.users') }}" 
                       class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.*') ? 'text-blue-700' : 'text-slate-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>User Management</span>
                    </a>
                @endif

                <a href="{{ route('stocks.index') }}" 
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('stocks.*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('stocks.*') ? 'text-blue-700' : 'text-slate-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    <span>Stok Barang Saya</span>
                </a>

                <a href="{{ route('profile') }}" 
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('profile') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('profile') ? 'text-blue-700' : 'text-slate-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Profil Saya</span>
                </a>
            </div>

            <!-- Bottom Logout Area -->
            <div class="p-3 border-t border-slate-200">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium text-rose-700 hover:bg-rose-50 hover:text-rose-700 transition">
                        <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Keluar (Logout)</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area with Left Padding for Sidebar -->
        <div class="md:pl-64 flex flex-col min-h-screen">
            <!-- Top Mobile Bar -->
            <header class="md:hidden h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sticky top-0 z-30">
                <button type="button" aria-label="Buka menu navigasi" @click="sidebarOpen = true" class="p-2 rounded-lg text-slate-600 hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="flex items-center space-x-2">
                    <span class="p-1.5 bg-gradient-to-tr bg-linear-to-tr from-blue-600 via-indigo-600 to-purple-600 text-white rounded-lg shadow-sm flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <span class="font-bold text-slate-900 tracking-tight">StokBarang <span class="bg-gradient-to-r bg-linear-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent font-extrabold">Pro</span></span>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ auth()->user()->isAdmin() ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' }}">
                    {{ ucfirst(auth()->user()->role) }}
                </span>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                @if (session()->has('success'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-emerald-700 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm font-medium">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-rose-700 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm font-medium">{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-600 mt-auto">
                &copy; 2026 Delcom. All rights reserved.
            </footer>
        </div>
    @else
        <!-- Guest Layout (Login & Register) -->
        <div class="min-h-screen flex flex-col justify-between">
            <header class="bg-white border-b border-slate-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <a href="{{ route('login') }}" class="flex items-center space-x-2.5 font-bold text-lg text-blue-700 hover:text-blue-900 transition">
                        <span class="p-2 bg-gradient-to-tr bg-linear-to-tr from-blue-600 via-indigo-600 to-purple-600 text-white rounded-xl shadow-md shadow-blue-500/20 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <span class="text-slate-900 tracking-tight">StokBarang <span class="bg-gradient-to-r bg-linear-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent font-extrabold">Pro</span></span>
                    </a>
                    <div class="space-x-2">
                        <a href="{{ route('login') }}" class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-slate-900">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="px-3.5 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition">
                            Register
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex-1 flex items-center justify-center p-4">
                {{ $slot }}
            </main>

            <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-600">
                &copy; 2026 Delcom. All rights reserved.
            </footer>
        </div>
    @endauth

    @livewireScripts
</body>
</html>
