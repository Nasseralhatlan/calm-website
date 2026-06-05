@extends('layouts.admin')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'مراجعة مكان' : 'Review place')
@section('heading', $isRtl ? 'مراجعة مكان' : 'Review place')

@section('main')
    <div x-data="{ rejecting: false }">

        {{-- Status strip --}}
        <div class="flex items-center flex-wrap" style="gap: 10px; margin-bottom: 18px;">
            <span class="inline-flex items-center text-[11px] font-bold text-white {{ $fa }}"
                  style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: #f59e0b;">
                <span style="width: 6px; height: 6px; border-radius: 999px; background-color: #fde68a;"></span>
                {{ $isRtl ? 'بانتظار المراجعة' : 'Pending review' }}
            </span>

            <span class="text-[12px] text-[#717171] {{ $fa }}">
                {{ $isRtl ? 'المضيف:' : 'Host:' }}
                <code class="text-[12px]" dir="ltr">
                    {{ $place->host?->phone ? '+966 '.$place->host->phone : ($place->host?->email ?? '—') }}
                </code>
            </span>

            <span class="text-[12px] text-[#bababa] {{ $fa }}">·</span>
            <span class="text-[12px] text-[#717171] {{ $fa }}">
                {{ $isRtl ? 'المعرف:' : 'ID:' }}
                <code class="text-[11px] text-[#bababa]" dir="ltr">{{ $place->id }}</code>
            </span>
        </div>

        {{-- Customer-style preview --}}
        <div class="bg-white" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            @include('places._card', ['place' => $place, 'preview' => true])
        </div>

        {{-- Action toolbar — sticks 16px from the viewport bottom (not flush
             against it) so it reads as a floating panel, not a system bar. --}}
        <div class="sticky bg-white/95 backdrop-blur"
             style="bottom: 16px; margin-top: 24px; margin-bottom: 16px; padding: 16px; border-radius: 24px; box-shadow: 0 -8px 30px rgba(0,0,0,0.08); border: 1px solid #ebebeb;">

            {{-- Rejection inline form --}}
            <div x-show="rejecting" x-transition style="margin-bottom: 16px;" x-cloak>
                <form method="POST" action="{{ route('admin.places.review.reject', $place) }}">
                    @csrf
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">
                        {{ $isRtl ? 'سبب الرفض (سيظهر للمضيف)' : 'Rejection reason (visible to the host)' }}
                    </label>
                    <textarea name="rejection_reason" required rows="3" maxlength="2000"
                              placeholder="{{ $isRtl ? 'اكتب ملاحظاتك للمضيف...' : 'Write your feedback for the host...' }}"
                              class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[14px] focus:outline-none {{ $fa }}"
                              style="padding: 11px 14px; border-radius: 12px;"></textarea>
                    <div class="flex items-center" style="gap: 10px; margin-top: 10px;">
                        <button type="submit" class="font-semibold text-white bg-[#ef4444] hover:bg-[#dc2626] {{ $fa }}"
                                style="padding: 10px 18px; border-radius: 12px; font-size: 14px;">
                            {{ $isRtl ? 'تأكيد الرفض' : 'Confirm reject' }}
                        </button>
                        <button type="button" @click="rejecting = false"
                                class="text-[14px] text-[#717171] hover:text-[#222] {{ $fa }}">
                            {{ $isRtl ? 'إلغاء' : 'Cancel' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="flex items-center flex-wrap" style="gap: 10px;" x-show="!rejecting">
                {{-- Approve --}}
                <form method="POST" action="{{ route('admin.places.review.approve', $place) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center font-bold text-white bg-[#10b981] hover:bg-[#059669] {{ $fa }}"
                            style="padding: 12px 22px; gap: 8px; border-radius: 14px; font-size: 14px; box-shadow: 0 6px 14px rgba(16,185,129,0.3);">
                        <span>✓</span> {{ $isRtl ? 'موافقة' : 'Approve' }}
                    </button>
                </form>

                {{-- Reject --}}
                <button type="button" @click="rejecting = true"
                        class="inline-flex items-center font-bold text-white bg-[#ef4444] hover:bg-[#dc2626] {{ $fa }}"
                        style="padding: 12px 22px; gap: 8px; border-radius: 14px; font-size: 14px;">
                    <span>✕</span> {{ $isRtl ? 'رفض' : 'Reject' }}
                </button>

                {{-- Skip --}}
                <form method="POST" action="{{ route('admin.places.review.skip', $place) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center font-bold text-[#222] bg-[#f4f6f8] hover:bg-[#ebeef1] {{ $fa }}"
                            style="padding: 12px 22px; gap: 8px; border-radius: 14px; font-size: 14px;">
                        <span class="rtl:scale-x-[-1] inline-block">→</span> {{ $isRtl ? 'تخطي' : 'Skip' }}
                    </button>
                </form>

                <a href="{{ route('admin.places.index') }}"
                   class="text-[14px] text-[#717171] hover:text-[#222] {{ $fa }}" style="margin-inline-start: auto;">
                    {{ $isRtl ? '← العودة للقائمة' : '← Back to list' }}
                </a>
            </div>
        </div>

        {{-- Next-up hint --}}
        @if($next)
            <p class="text-center text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 16px;">
                {{ $isRtl ? 'التالي بعد هذا الإجراء:' : 'Next after this action:' }}
                <strong class="text-[#222]">{{ $next->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}</strong>
            </p>
        @endif
    </div>
@endsection
