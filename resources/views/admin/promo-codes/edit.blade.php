@extends('layouts.admin')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'تعديل كود الخصم' : 'Edit promo code')
@section('heading', ($isRtl ? 'تعديل كود الخصم' : 'Edit promo code').' · '.$promo->code)

@section('main')
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <p class="text-[13px] text-[#717171] {{ $isRtl ? 'font-arabic' : '' }}" style="margin-bottom: 16px;">
            {{ $isRtl ? 'التعديل يسري على الحجوزات الجديدة فقط — الحجوزات السابقة تحتفظ بخصمها.' : 'Edits apply to new bookings only — existing bookings keep the discount they got.' }}
        </p>
        <form method="POST" action="{{ route('admin.promo-codes.update', $promo) }}">
            @csrf
            @method('PUT')
            @include('admin.promo-codes._form', ['submitLabel' => $isRtl ? 'حفظ' : 'Save'])
        </form>
    </div>
@endsection
