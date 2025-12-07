@extends('layouts.app')

@section('content')
    @include('battles.partials.battle_interface', ['battle' => $battle, 'state' => $state, 'viewerId' => auth()->id()])
@endsection
