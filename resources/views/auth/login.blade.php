<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ config('app.name', 'Laravel') }}</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] font-sans antialiased flex items-center justify-center min-h-screen p-6">
    <div class="w-full max-w-sm">
        <div class="bg-white dark:bg-[#161615] rounded-lg shadow-sm border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Please enter your details to sign in.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-4 p-3 rounded bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1.5">Email address</label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            value="{{ old('email') }}" 
                            required 
                            autofocus
                            class="w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-200 dark:border-[#3E3E3A] rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-black dark:focus:ring-white focus:border-transparent transition-all"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-medium">Password</label>
                            <a href="#" class="text-xs font-medium text-blue-600 hover:text-blue-500">Forgot password?</a>
                        </div>
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            required
                            class="w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-200 dark:border-[#3E3E3A] rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-black dark:focus:ring-white focus:border-transparent transition-all"
                        >
                    </div>

                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-600 dark:text-gray-400">Remember me</label>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-black dark:bg-white text-white dark:text-black py-2 rounded-md text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-200 transition-colors duration-200 shadow-sm"
                    >
                        Sign in
                    </button>
                </form>
            </div>
            
            <div class="px-8 py-4 bg-gray-50 dark:bg-[#1C1C1A] border-t border-gray-200 dark:border-[#3E3E3A] text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Don't have an account? <a href="#" class="font-medium text-black dark:text-white hover:underline">Request access</a>
                </p>
            </div>
        </div>
        
        <div class="mt-8 text-center">
            <a href="{{ url('/') }}" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                &larr; Back to website
            </a>
        </div>
    </div>
</body>
</html>
