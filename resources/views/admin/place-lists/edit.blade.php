@extends('layouts.admin')

@php
    use Illuminate\Support\Facades\Storage;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $coverUrl = function ($place) {
        $cover = $place->coverPhoto;
        if (! $cover) return null;
        if (str_starts_with($cover->path, 'http')) return $cover->path;
        return Storage::disk('s3')->url($cover->path);
    };
@endphp

@section('title', ($isRtl ? 'تعديل قائمة: ' : 'Edit list: ').($isRtl ? $list->name_ar : $list->name_en))
@section('heading', $isRtl ? 'تعديل قائمة' : 'Edit list')

@section('main')
    {{-- ── Edit metadata ── --}}
    <div class="bg-white max-w-3xl" style="padding: 24px; margin-bottom: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.place-lists.update', $list) }}">
            @csrf @method('PUT')
            @include('admin.place-lists._form', ['submitLabel' => $isRtl ? 'تحديث' : 'Update'])
        </form>
    </div>

    {{-- ── Current members of the list ── --}}
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <div class="flex items-end justify-between flex-wrap" style="gap: 12px; margin-bottom: 18px;">
            <h2 class="text-[18px] font-bold text-[#222] {{ $fa }}">
                {{ $isRtl ? 'الأماكن في القائمة' : 'Places in this list' }}
                <span class="text-[#717171] tabular-nums">({{ $list->places->count() }})</span>
            </h2>
            <p class="text-[12px] text-[#717171] {{ $fa }}">
                {{ $isRtl ? 'لإضافة مكان، افتحه من صفحة الأماكن واختر هذه القائمة.' : 'To add a place, open it from the Places page and tick this list.' }}
            </p>
        </div>

        @if($list->places->isEmpty())
            <p class="text-[#717171] text-[14px] {{ $fa }}" style="padding: 16px 0;">
                {{ $isRtl ? 'لا توجد أماكن في هذه القائمة بعد.' : 'No places in this list yet.' }}
            </p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 14px;">
                @foreach($list->places as $place)
                    @php $url = $coverUrl($place); @endphp
                    <div class="flex items-center bg-[#fafafa]" style="gap: 14px; padding: 12px; border-radius: 18px;">
                        @if($url)
                            <img src="{{ $url }}" class="shrink-0 object-cover" style="width: 60px; height: 60px; border-radius: 12px;" alt="">
                        @else
                            <span class="shrink-0 flex items-center justify-center bg-white text-2xl" style="width: 60px; height: 60px; border-radius: 12px;">{{ $place->type?->icon ?: '🏠' }}</span>
                        @endif
                        <div class="flex-1 min-w-0 {{ $fa }}">
                            <a href="{{ route('admin.places.edit', $place) }}" class="font-medium text-[#222] truncate hover:underline block">
                                {{ $place->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}
                            </a>
                            <div class="text-[12px] text-[#717171] truncate">
                                {{ $isRtl ? $place->cityArea?->city?->name_ar : $place->cityArea?->city?->name_en }}
                                · {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.place-lists.detach', ['placeList' => $list, 'place' => $place]) }}" class="shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[#dc2626] text-[18px] font-bold hover:bg-[#fef2f2] inline-flex items-center justify-center"
                                    style="width: 32px; height: 32px; border-radius: 999px;"
                                    title="{{ $isRtl ? 'إزالة' : 'Remove' }}">
                                ✕
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
