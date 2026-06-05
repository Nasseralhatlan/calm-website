@extends('layouts.admin')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'إضافة خاصية' : 'Add attribute')
@section('heading', $isRtl ? 'إضافة خاصية' : 'Add attribute')

@section('main')
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.attributes.store') }}">
            @csrf
            @include('admin.attributes._form', ['submitLabel' => $isRtl ? 'إضافة' : 'Create'])
        </form>
    </div>
@endsection
