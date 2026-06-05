@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'تعديل الدولة' : 'Edit country')
@section('heading', $isRtl ? 'تعديل الدولة' : 'Edit country')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.countries.update', $country) }}">
            @csrf
            @method('PUT')
            @include('admin.countries._form', ['submitLabel' => $isRtl ? 'تحديث' : 'Update'])
        </form>
    </div>
@endsection
