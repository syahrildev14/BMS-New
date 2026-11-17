@extends('errors::layout')

@section('code', '503')
@section('title', 'Sedang Dalam Pemeliharaan')
@section('message', 'Sistem sedang diperbarui atau dalam pemeliharaan. Silakan coba lagi beberapa menit lagi.')
@section('action')
    <button type="button" class="primary" onclick="location.reload()">Muat Ulang</button>
@endsection
