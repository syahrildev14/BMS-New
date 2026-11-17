@extends('errors::layout')

@section('code', '404')
@section('title', 'Halaman Tidak Ditemukan')
@section('message', 'Halaman yang Anda cari tidak ada, sudah dipindahkan, atau alamatnya salah ketik.')
@section('action')
    <a class="primary" href="/dashboard">Ke Dashboard</a>
@endsection
