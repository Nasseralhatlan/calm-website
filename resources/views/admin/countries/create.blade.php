@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'إضافة دولة' : 'Add country')
@section('heading', $isRtl ? 'إضافة دولة' : 'Add country')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.countries.store') }}">
            @csrf
            @include('admin.countries._form', ['submitLabel' => $isRtl ? 'إضافة' : 'Create'])
        </form>
    </div>
@endsection
