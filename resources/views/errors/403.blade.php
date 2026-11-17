@extends('errors::layout')

@section('code', '403')
@section('title', 'Akses Ditolak')
@section('message', 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Hubungi admin bila Anda merasa memerlukan akses.')
@section('action')
    <a class="primary" href="/dashboard">Ke Dashboard</a>
@endsection
