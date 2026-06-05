@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'تعديل الحي' : 'Edit area')
@section('heading', $isRtl ? 'تعديل الحي' : 'Edit area')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.city-areas.update', $cityArea) }}">
            @csrf
            @method('PUT')
            @include('admin.city-areas._form', ['submitLabel' => $isRtl ? 'تحديث' : 'Update'])
        </form>
    </div>
@endsection
