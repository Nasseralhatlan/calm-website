@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'إضافة مدينة' : 'Add city')
@section('heading', $isRtl ? 'إضافة مدينة' : 'Add city')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.cities.store') }}">
            @csrf
            @include('admin.cities._form', ['submitLabel' => $isRtl ? 'إضافة' : 'Create'])
        </form>
    </div>
@endsection
