<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - {{ config('app.name', 'Laravel') }}</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] font-sans antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside 
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-[#161615] shadow-sm transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 border-r border-gray-200 dark:border-[#3E3E3A]"
        >
            <div class="flex items-center justify-between h-16 px-6 border-b border-gray-200 dark:border-[#3E3E3A]">
                <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Admin Panel</span>
                <button @click="sidebarOpen = false" class="lg:hidden p-2 text-gray-500 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <nav class="p-4 space-y-2 overflow-y-auto h-[calc(100vh-4rem)]">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.dashboard') ? 'bg-gray-100 dark:bg-[#2A2A28] text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-[#1C1C1A]' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                
                <div class="pt-4 pb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Resources</div>
                
                <!-- Example links for existing models -->
                <a href="#" class="flex items-center px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-md hover:bg-gray-50 dark:hover:bg-[#1C1C1A]">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Clients
                </a>
                <a href="#" class="flex items-center px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-md hover:bg-gray-50 dark:hover:bg-[#1C1C1A]">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Products
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Header -->
            <header class="flex items-center justify-between h-16 px-6 bg-white dark:bg-[#161615] border-b border-gray-200 dark:border-[#3E3E3A]">
                <div class="flex items-center">
                    <button @click="sidebarOpen = true" class="p-2 text-gray-500 lg:hidden hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="ml-4 lg:ml-0 font-medium text-gray-700 dark:text-gray-300">
                        @yield('header_title', 'Dashboard')
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <button class="p-2 text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </button>
                    
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-800 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&color=7F9CF5&background=EBF4FF" class="w-8 h-8 rounded-full" alt="User avatar">
                            <span class="ml-2 hidden md:block">{{ auth()->user()->name ?? 'Admin' }}</span>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" class="absolute right-0 w-48 mt-2 origin-top-right bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-md shadow-lg py-1 z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-[#1C1C1A]">Profile</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-[#1C1C1A]">Settings</a>
                            <form action="{{ route('logout') }}" method="POST" class="w-full">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-[#1C1C1A]">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 dark:bg-[#0a0a0a] p-6">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
