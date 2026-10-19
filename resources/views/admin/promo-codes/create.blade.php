@extends('layouts.admin')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'كود خصم جديد' : 'New promo code')
@section('heading', $isRtl ? 'كود خصم جديد' : 'New promo code')

@section('main')
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.promo-codes.store') }}">
            @csrf
            @include('admin.promo-codes._form', ['submitLabel' => $isRtl ? 'إنشاء' : 'Create'])
        </form>
    </div>
@endsection
