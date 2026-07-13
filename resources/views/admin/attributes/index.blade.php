@extends('layouts.admin')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; $fa = $isRtl ? 'font-arabic' : ''; @endphp

@section('title', $isRtl ? 'الخصائص' : 'Attributes')
@section('heading', $isRtl ? 'الخصائص' : 'Attributes')

@section('main')
<div x-data="attributesManager(@js($init), {{ $isRtl ? 'true' : 'false' }})">

    {{-- Top bar --}}
    <div class="flex items-center justify-between flex-wrap" style="gap: 10px; margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171] {{ $fa }}">
            {{ $isRtl ? 'اسحب لإعادة الترتيب · اضغط على خاصية لتعديلها · ⭐ للتمييز' : 'Drag to reorder · click an attribute to edit · ⭐ to highlight' }}
        </p>
        <div class="flex items-center" style="gap: 10px;">
            <button type="button" @click="openCreateGroup()"
                    class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7]"
                    style="padding: 10px 16px; gap: 6px; border-radius: 12px; font-size: 14px; border: 1px solid #ebebeb;">
                <span>+</span><span>{{ $isRtl ? 'مجموعة' : 'Group' }}</span>
            </button>
            <button type="button" @click="openCreateAttribute()"
                    class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
                    style="padding: 10px 16px; gap: 6px; border-radius: 12px; font-size: 14px;">
                <span>+</span><span>{{ $isRtl ? 'خاصية' : 'Attribute' }}</span>
            </button>
        </div>
    </div>

    {{-- Groups (drag to reorder) --}}
    <div x-sort="moveGroup($item, $position)" class="space-y-4">
        <template x-for="group in groups" :key="group.id">
            <div x-sort:item="group.id" class="bg-white" style="border-radius: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05); overflow: hidden;">
                {{-- Group header (drag handle) --}}
                <div class="flex items-center bg-[#fafafa] border-b border-[#ebebeb]" style="padding: 12px 16px; gap: 10px;">
                    <span x-sort:handle class="text-[#bbb] text-[18px] leading-none select-none cursor-grab">⠿</span>
                    <span class="font-semibold text-[#222] {{ $fa }}" x-text="label(group)"></span>
                    <span class="text-[12px] text-[#717171]" x-text="'· ' + group.attributes.length"></span>
                    {{-- Standalone star — same quick-toggle pattern as the attribute highlight star. --}}
                    <button type="button" @click.stop="toggleStandalone(group)" class="text-[14px] leading-none" :title="group.is_standalone ? '{{ $isRtl ? 'قسم مستقل' : 'Standalone section' }}' : '{{ $isRtl ? 'اجعله قسمًا مستقلًا' : 'Make standalone section' }}'" x-text="group.is_standalone ? '⭐' : '☆'"></button>
                    <div class="flex items-center" style="margin-inline-start: auto; gap: 14px;">
                        <button type="button" @click="openCreateAttribute(group.id)" class="text-[13px] font-semibold text-[#222] hover:underline {{ $fa }}">{{ $isRtl ? '+ خاصية' : '+ Attribute' }}</button>
                        <button type="button" @click="openEditGroup(group)" class="text-[13px] font-semibold text-[#717171] hover:text-[#222] {{ $fa }}">{{ $isRtl ? 'تعديل' : 'Edit' }}</button>
                        <button type="button" @click="deleteGroup(group)" class="text-[13px] font-semibold text-[#dc2626] hover:underline {{ $fa }}">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                    </div>
                </div>

                {{-- Attribute chips (drag to reorder within the group) --}}
                <div x-sort="moveAttribute(group, $item, $position)" class="flex flex-wrap" style="padding: 14px; gap: 10px; min-height: 56px;">
                    <template x-for="attr in group.attributes" :key="attr.id">
                        <div x-sort:item="attr.id"
                             @click="openEditAttribute(attr)"
                             class="inline-flex items-center bg-white cursor-pointer transition-shadow hover:shadow-md"
                             :style="'padding: 9px 12px; gap: 8px; border-radius: 999px; border: 1px solid ' + (attr.is_highlighted ? '#F88379' : '#dddddd') + (attr.is_highlighted ? '; background-color:#fff8f7' : '')">
                            <span x-sort:handle @click.stop class="text-[#ccc] text-[13px] leading-none select-none cursor-grab">⠿</span>
                            <span class="text-[16px] leading-none" x-text="attr.icon || '·'"></span>
                            <span class="text-[14px] font-medium text-[#222] whitespace-nowrap {{ $fa }}" x-text="label(attr)"></span>
                            <button type="button" @click.stop="toggleStar(attr)" class="text-[14px] leading-none" :title="attr.is_highlighted ? 'Highlighted' : 'Mark important'" x-text="attr.is_highlighted ? '⭐' : '☆'"></button>
                            <button type="button" @click.stop="deleteAttribute(group, attr)" class="text-[#bbb] hover:text-[#dc2626] text-[14px] leading-none" title="Delete">✕</button>
                        </div>
                    </template>
                    <p x-show="group.attributes.length === 0" class="text-[13px] text-[#999] {{ $fa }}" style="padding: 6px;">{{ $isRtl ? 'لا توجد خصائص في هذه المجموعة.' : 'No attributes in this group.' }}</p>
                </div>
            </div>
        </template>
    </div>

    <p x-show="groups.length === 0" class="text-[#717171] {{ $fa }}">{{ $isRtl ? 'لا توجد مجموعات بعد.' : 'No groups yet.' }}</p>

    {{-- ── Modal: create/edit attribute or group ── --}}
    <template x-if="modal">
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0,0,0,0.4); padding: 16px;" @click.self="closeModal()">
            <div class="bg-white w-full overflow-y-auto" style="max-width: 560px; max-height: 90vh; border-radius: 20px; padding: 24px;">
                <div class="flex items-center justify-between" style="margin-bottom: 18px;">
                    <h3 class="text-[18px] font-bold text-[#222] {{ $fa }}" x-text="modalTitle()"></h3>
                    <button type="button" @click="closeModal()" class="text-[#999] hover:text-[#222] text-[20px] leading-none">✕</button>
                </div>

                <form @submit.prevent="submitModal()">
                    {{-- Group fields --}}
                    <template x-if="modal.kind === 'group'">
                        <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                            <div>
                                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم (عربي)' : 'Name (AR)' }}</label>
                                <input type="text" x-model="modal.data.name_ar" dir="rtl" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                <template x-if="modal.errors.name_ar"><p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;" x-text="modal.errors.name_ar[0]"></p></template>
                            </div>
                            <div>
                                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم (إنجليزي)' : 'Name (EN)' }}</label>
                                <input type="text" x-model="modal.data.name_en" dir="ltr" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                <template x-if="modal.errors.name_en"><p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;" x-text="modal.errors.name_en[0]"></p></template>
                            </div>
                            <label class="sm:col-span-2">
                                <input type="checkbox" x-model="modal.data.is_standalone">
                                {{ $isRtl ? 'قسم مستقل — يظهر في التطبيق كقسم منفصل عن قائمة المرافق' : 'Standalone section — appears in the app as its own section, outside the amenities list' }}
                            </label>
                        </div>
                    </template>

                    {{-- Attribute fields --}}
                    <template x-if="modal.kind === 'attribute'">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'المجموعة' : 'Group' }}</label>
                                    <select x-model="modal.data.group_id" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                        <template x-for="g in groups" :key="g.id"><option :value="g.id" x-text="label(g)"></option></template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'النوع' : 'Type' }}</label>
                                    <select x-model="modal.data.type" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                        <template x-for="t in typeOptions" :key="t"><option :value="t" x-text="t"></option></template>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم (عربي)' : 'Name (AR)' }}</label>
                                    <input type="text" x-model="modal.data.name_ar" dir="rtl" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                    <template x-if="modal.errors.name_ar"><p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;" x-text="modal.errors.name_ar[0]"></p></template>
                                </div>
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم (إنجليزي)' : 'Name (EN)' }}</label>
                                    <input type="text" x-model="modal.data.name_en" dir="ltr" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                    <template x-if="modal.errors.name_en"><p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;" x-text="modal.errors.name_en[0]"></p></template>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'أيقونة' : 'Icon' }}</label>
                                    <input type="text" x-model="modal.data.icon" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                </div>
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الصورة' : 'Photo' }}</label>
                                    <select x-model="modal.data.photo_rule" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                        <template x-for="r in photoRuleOptions" :key="r"><option :value="r" x-text="r"></option></template>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'سؤال (عربي)' : 'Question (AR)' }}</label>
                                    <input type="text" x-model="modal.data.question_ar" dir="rtl" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                </div>
                                <div>
                                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'سؤال (إنجليزي)' : 'Question (EN)' }}</label>
                                    <input type="text" x-model="modal.data.question_en" dir="ltr" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none" style="padding: 11px 14px; border-radius: 12px;">
                                </div>
                            </div>
                            <div x-show="['select','multi_select'].includes(modal.data.type)">
                                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الخيارات (سطر لكل خيار)' : 'Options (one per line)' }}</label>
                                <textarea x-model="modal.data.options_text" rows="4" class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none font-mono" style="padding: 11px 14px; border-radius: 12px;"></textarea>
                            </div>
                            <label>
                                <input type="checkbox" x-model="modal.data.is_highlighted">
                                {{ $isRtl ? 'مميّزة — تظهر في قسم منفصل' : 'Highlight — show in a separate section' }}
                            </label>
                        </div>
                    </template>

                    <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
                        <button type="submit" :disabled="modal.saving" class="font-semibold text-white bg-[#222] hover:bg-black disabled:opacity-50" style="padding: 11px 24px; border-radius: 12px; font-size: 14px;" x-text="modal.saving ? '…' : '{{ $isRtl ? 'حفظ' : 'Save' }}'"></button>
                        <button type="button" @click="closeModal()" class="text-[14px] text-[#717171] hover:text-[#222] {{ $fa }}">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>

<style>
    .sort-ghost { opacity: 0.4; }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('attributesManager', (init, isRtl) => ({
            groups: init.groups,
            typeOptions: init.typeOptions,
            photoRuleOptions: init.photoRuleOptions,
            isRtl,
            modal: null,
            csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),

            label(o) { return this.isRtl ? o.name_ar : o.name_en; },
            modalTitle() {
                const create = this.modal.mode === 'create';
                if (this.modal.kind === 'group') return isRtl ? (create ? 'إضافة مجموعة' : 'تعديل المجموعة') : (create ? 'Add group' : 'Edit group');
                return isRtl ? (create ? 'إضافة خاصية' : 'تعديل الخاصية') : (create ? 'Add attribute' : 'Edit attribute');
            },

            // ── modals ──
            openCreateAttribute(groupId = null) {
                this.modal = { kind: 'attribute', mode: 'create', errors: {}, saving: false, data: {
                    id: null, group_id: groupId || (this.groups[0] && this.groups[0].id) || '',
                    name_ar: '', name_en: '', question_ar: '', question_en: '', icon: '',
                    type: this.typeOptions[0], photo_rule: 'none', options_text: '', is_highlighted: false,
                } };
            },
            openEditAttribute(attr) {
                this.modal = { kind: 'attribute', mode: 'edit', errors: {}, saving: false, data: {
                    id: attr.id, group_id: attr.group_id, name_ar: attr.name_ar, name_en: attr.name_en,
                    question_ar: attr.question_ar || '', question_en: attr.question_en || '', icon: attr.icon || '',
                    type: attr.type, photo_rule: attr.photo_rule, options_text: (attr.options || []).join('\n'),
                    is_highlighted: attr.is_highlighted,
                } };
            },
            openCreateGroup() { this.modal = { kind: 'group', mode: 'create', errors: {}, saving: false, data: { id: null, name_ar: '', name_en: '', is_standalone: false } }; },
            openEditGroup(group) { this.modal = { kind: 'group', mode: 'edit', errors: {}, saving: false, data: { id: group.id, name_ar: group.name_ar, name_en: group.name_en, is_standalone: !!group.is_standalone } }; },
            closeModal() { this.modal = null; },

            submitModal() { return this.modal.kind === 'attribute' ? this.saveAttribute() : this.saveGroup(); },

            async saveAttribute() {
                const m = this.modal; m.saving = true; m.errors = {};
                const isEdit = m.mode === 'edit';
                const res = await this.req(isEdit ? 'PUT' : 'POST', isEdit ? `/admin/attributes/${m.data.id}` : '/admin/attributes', m.data);
                if (res.status === 422) { m.errors = ((await res.json()).data || {}).errors || {}; m.saving = false; return; }
                if (!res.ok) { m.saving = false; alert('Save failed'); return; }
                const attr = (await res.json()).attribute;
                for (const g of this.groups) g.attributes = g.attributes.filter(a => a.id !== attr.id);
                const g = this.groups.find(x => x.id === attr.group_id);
                if (g) g.attributes.push(attr);
                this.closeModal();
                this.persistOrder();
            },

            async saveGroup() {
                const m = this.modal; m.saving = true; m.errors = {};
                const isEdit = m.mode === 'edit';
                const res = await this.req(isEdit ? 'PUT' : 'POST', isEdit ? `/admin/attribute-groups/${m.data.id}` : '/admin/attribute-groups', m.data);
                if (res.status === 422) { m.errors = ((await res.json()).data || {}).errors || {}; m.saving = false; return; }
                if (!res.ok) { m.saving = false; alert('Save failed'); return; }
                const group = (await res.json()).group;
                if (isEdit) { const g = this.groups.find(x => x.id === group.id); if (g) { g.name_ar = group.name_ar; g.name_en = group.name_en; g.is_standalone = group.is_standalone; } }
                else { this.groups.push({ ...group, attributes: [] }); }
                this.closeModal();
                this.persistOrder();
            },

            async toggleStar(attr) {
                const res = await this.req('POST', `/admin/attributes/${attr.id}/highlight`);
                if (res.ok) attr.is_highlighted = (await res.json()).is_highlighted;
            },
            async toggleStandalone(group) {
                const res = await this.req('POST', `/admin/attribute-groups/${group.id}/standalone`);
                if (res.ok) group.is_standalone = (await res.json()).is_standalone;
            },
            async deleteAttribute(group, attr) {
                // Deleting cascades: the amenity disappears from every place using
                // it, and its section photos become general photos — warn with the
                // blast radius when it's actually in use.
                const n = attr.places_count || 0;
                const msg = n > 0
                    ? (isRtl
                        ? `حذف «${this.label(attr)}»؟ مستخدمة في ${n} ${n === 1 ? 'مكان' : 'أماكن'} — ستُحذف منها وستتحول صورها إلى صور عامة.`
                        : `Delete "${this.label(attr)}"? Used by ${n} place${n === 1 ? '' : 's'} — it will be removed from them and its section photos become general photos.`)
                    : (isRtl ? 'حذف هذه الخاصية؟' : 'Delete this attribute?');
                if (!confirm(msg)) return;
                const res = await this.req('DELETE', `/admin/attributes/${attr.id}`);
                if (res.ok) group.attributes = group.attributes.filter(a => a.id !== attr.id);
            },
            async deleteGroup(group) {
                const m = group.attributes.length;
                const n = group.attributes.reduce((sum, a) => sum + (a.places_count || 0), 0);
                const msg = n > 0
                    ? (isRtl
                        ? `حذف «${this.label(group)}» وخصائصها الـ${m}؟ مستخدمة في ${n} ${n === 1 ? 'مكان' : 'أماكن'} إجمالًا — ستُحذف منها وستتحول صورها إلى صور عامة.`
                        : `Delete "${this.label(group)}" and its ${m} attribute${m === 1 ? '' : 's'}? Used by ${n} place${n === 1 ? '' : 's'} in total — they will be removed from them and their section photos become general photos.`)
                    : (isRtl ? 'حذف هذه المجموعة وكل خصائصها؟' : 'Delete this group and all its attributes?');
                if (!confirm(msg)) return;
                const res = await this.req('DELETE', `/admin/attribute-groups/${group.id}`);
                if (res.ok) this.groups = this.groups.filter(g => g.id !== group.id);
            },

            // ── sorting ──
            moveGroup(id, position) {
                const from = this.groups.findIndex(g => g.id === id);
                if (from === -1) return;
                const [g] = this.groups.splice(from, 1);
                this.groups.splice(position, 0, g);
                this.persistOrder();
            },
            moveAttribute(group, id, position) {
                const from = group.attributes.findIndex(a => a.id === id);
                if (from === -1) return;
                const [a] = group.attributes.splice(from, 1);
                group.attributes.splice(position, 0, a);
                this.persistOrder();
            },
            persistOrder() {
                const payload = this.groups.map(g => ({ id: g.id, attributes: g.attributes.map(a => a.id) }));
                this.req('POST', '/admin/attributes/reorder', { payload: JSON.stringify(payload) });
            },

            req(method, url, body) {
                return fetch(url, {
                    method,
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: body === undefined ? undefined : JSON.stringify(body),
                });
            },
        }));
    });
</script>
@endsection
