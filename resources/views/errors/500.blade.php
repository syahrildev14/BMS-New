@extends('errors::layout')

@section('code', '500')
@section('title', 'Terjadi Kesalahan Server')
@section('message', 'Maaf, ada masalah di sistem kami. Coba lagi beberapa saat lagi. Bila terus berulang, hubungi admin.')
@section('action')
    <button type="button" class="primary" onclick="location.reload()">Muat Ulang</button>
@endsection
