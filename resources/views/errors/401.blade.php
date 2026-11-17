@extends('errors::layout')

@section('code', '401')
@section('title', 'Perlu Masuk')
@section('message', 'Sesi Anda sudah berakhir atau Anda belum masuk. Silakan masuk terlebih dahulu untuk melanjutkan.')
@section('action')
    <a class="primary" href="/login">Masuk</a>
@endsection
