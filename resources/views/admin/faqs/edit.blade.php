@extends('layouts.admin')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'تعديل سؤال' : 'Edit FAQ')
@section('heading', $isRtl ? 'تعديل سؤال' : 'Edit FAQ')

@section('main')
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.faqs.update', $faq) }}">
            @csrf
            @method('PUT')
            @include('admin.faqs._form', ['submitLabel' => $isRtl ? 'حفظ' : 'Save'])
        </form>
    </div>
@endsection
