@extends('layouts.admin')

@php
    use App\Enums\UserRole;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';

    // Same vivid pill palette as cities/countries. Admin = coral (brand),
    // regular User = neutral gray. New roles slot in naturally.
    $rolePill = fn (?UserRole $r): array => match ($r) {
        UserRole::Admin => ['bg' => '#F88379', 'dot' => '#fecaca'],
        default          => ['bg' => '#9ca3af', 'dot' => '#e5e7eb'],
    };
@endphp

@section('title', $isRtl ? 'المستخدمون' : 'Users')
@section('heading', $isRtl ? 'المستخدمون' : 'Users')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">
            {{ $users->total() }} {{ $isRtl ? 'مستخدم' : 'users' }}
        </p>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] text-[#717171] tracking-wider">
                <tr>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الاسم' : 'Name' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الجوال' : 'Phone' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'البريد' : 'Email' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الأماكن' : 'Places' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الدور' : 'Role' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($users as $user)
                    @php
                        $rp = $rolePill($user->role);
                        $initial = strtoupper(mb_substr($user->name ?: ($user->phone ?: ($user->email ?: '?')), 0, 1));
                    @endphp
                    <tr class="border-t border-[#ebebeb] hover:bg-[#fafafa] transition-colors">
                        <td style="padding: 14px 20px;">
                            <span class="inline-flex items-center" style="gap: 12px;">
                                <span class="inline-flex items-center justify-center font-bold text-[#717171]"
                                      style="width: 32px; height: 32px; border-radius: 999px; background-color: #f4f6f8; font-size: 13px;">
                                    {{ $initial }}
                                </span>
                                <span class="font-medium text-[#222]">{{ $user->name ?: ($isRtl ? '— بدون اسم —' : '— No name —') }}</span>
                            </span>
                        </td>
                        <td class="text-[#717171] tabular-nums" style="padding: 14px 20px;" dir="ltr">
                            {{ $user->phone ? '+966 '.$user->phone : '—' }}
                        </td>
                        <td class="text-[#717171]" style="padding: 14px 20px;" dir="ltr">{{ $user->email ?: '—' }}</td>
                        <td class="text-[#717171] tabular-nums" style="padding: 14px 20px;">{{ $user->places_count }}</td>
                        <td style="padding: 14px 20px;">
                            <span class="inline-flex items-center text-[11px] font-bold text-white"
                                  style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $rp['bg'] }};">
                                <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $rp['dot'] }};"></span>
                                {{ $user->role?->value ?? 'user' }}
                            </span>
                        </td>
                        <td class="text-end whitespace-nowrap" style="padding: 14px 20px;">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-[#717171]" style="padding: 32px 20px;">{{ $isRtl ? 'لا يوجد مستخدمون بعد.' : 'No users yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())<div style="margin-top: 20px;">{{ $users->links() }}</div>@endif
@endsection
