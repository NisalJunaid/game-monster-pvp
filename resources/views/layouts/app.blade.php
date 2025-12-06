<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Monster Tamer') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
<nav class="bg-gray-900 text-white shadow">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="{{ route('home') }}" class="font-bold">Monster Tamer</a>
            @auth
                <a href="{{ route('dashboard') }}" class="hover:text-teal-200">Dashboard</a>
                <a href="{{ route('encounters.index') }}" class="hover:text-teal-200">Encounters</a>
                <a href="{{ route('pvp.index') }}" class="hover:text-teal-200">PvP</a>
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.index') }}" class="hover:text-teal-200">Admin</a>
                @endif
            @endauth
        </div>
        <div class="flex items-center space-x-3">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="bg-red-600 hover:bg-red-500 text-white px-3 py-1 rounded">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="px-3 py-1 rounded bg-teal-600 hover:bg-teal-500">Login</a>
                <a href="{{ route('register') }}" class="px-3 py-1 rounded bg-teal-500 hover:bg-teal-400">Register</a>
            @endauth
        </div>
    </div>
</nav>

<main class="max-w-6xl mx-auto px-4 py-6">
    @if(session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 border border-green-200 rounded">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 border border-red-200 rounded">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
