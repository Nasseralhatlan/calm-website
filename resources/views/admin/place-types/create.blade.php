@extends('layouts.admin')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'إضافة نوع' : 'Add type')
@section('heading', $isRtl ? 'إضافة نوع' : 'Add type')

@section('main')
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.place-types.store') }}">
            @csrf
            @include('admin.place-types._form', ['submitLabel' => $isRtl ? 'إضافة' : 'Create'])
        </form>
    </div>
@endsection
