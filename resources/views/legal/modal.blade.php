@php
    $effectiveDate = (string) config('legal.effective_date');
    $termsVersion = (string) config('legal.terms_version');
    $privacyVersion = (string) config('legal.privacy_version');
    $ticketConsentVersion = (string) config('legal.ticket_consent_version');
@endphp

<div
    x-cloak
    x-show="legalModalOpen"
    x-transition.opacity
    @keydown.escape.window="closeLegalModal()"
    class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6"
    aria-modal="true"
    role="dialog"
    aria-label="Legal documents"
>
    <div class="absolute inset-0 bg-slate-900/70" @click="closeLegalModal()"></div>

    <div class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-200 px-4 py-3 sm:px-6">
            <div>
                <h2 class="font-display text-lg font-semibold text-slate-900">Legal Documents</h2>
                <p class="text-xs text-slate-500">
                    Effective {{ $effectiveDate }} | Terms v{{ $termsVersion }} | Privacy v{{ $privacyVersion }} | Ticket Consent v{{ $ticketConsentVersion }}
                </p>
            </div>
            <button type="button" @click="closeLegalModal()" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Close legal modal">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="border-b border-slate-200 px-4 py-3 sm:px-6">
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="legalModalTab = 'terms'" class="rounded-lg border px-3 py-1.5 text-sm font-semibold"
                    :class="legalModalTab === 'terms' ? 'border-[#033b3d] bg-[#033b3d] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'">
                    Terms
                </button>
                <button type="button" @click="legalModalTab = 'privacy'" class="rounded-lg border px-3 py-1.5 text-sm font-semibold"
                    :class="legalModalTab === 'privacy' ? 'border-[#033b3d] bg-[#033b3d] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'">
                    Privacy
                </button>
                <button type="button" @click="legalModalTab = 'ticket-consent'" class="rounded-lg border px-3 py-1.5 text-sm font-semibold"
                    :class="legalModalTab === 'ticket-consent' ? 'border-[#033b3d] bg-[#033b3d] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'">
                    Ticket Consent
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            <div x-show="legalModalTab === 'terms'" x-transition.opacity>
                @include('legal.partials.terms-content')
            </div>
            <div x-show="legalModalTab === 'privacy'" x-transition.opacity>
                @include('legal.partials.privacy-content')
            </div>
            <div x-show="legalModalTab === 'ticket-consent'" x-transition.opacity>
                @include('legal.partials.ticket-consent-content')
            </div>
        </div>

        <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
            <div class="flex justify-end">
                <button type="button" class="btn-secondary" @click="closeLegalModal()">Close</button>
            </div>
        </div>
    </div>
</div>
