@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white shadow rounded p-6">
        <h1 class="text-2xl font-bold mb-2">Choose Your Starter Type</h1>
        <p class="text-gray-700">Pick the elemental path you want to begin with. We'll assign you a random stage-one species from the type you select.</p>
    </div>

    <form method="POST" action="{{ route('starter.store') }}" class="bg-white shadow rounded p-6 space-y-4">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
            @foreach($types as $type)
                <label class="border rounded p-4 flex items-start space-x-3 cursor-pointer hover:border-teal-400 transition">
                    <input type="radio" name="type_id" value="{{ $type->id }}" class="mt-1" required>
                    <div>
                        <p class="font-semibold">{{ $type->name }}</p>
                        @if($type->description)
                            <p class="text-sm text-gray-600">{{ $type->description }}</p>
                        @else
                            <p class="text-sm text-gray-500">Mystery awaits with this element.</p>
                        @endif
                    </div>
                </label>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white px-4 py-2 rounded font-semibold">Confirm Starter</button>
        </div>
    </form>
</div>
@endsection
