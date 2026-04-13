<div id="resolve-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-resolve-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Mark Ticket as Resolved</h3>
                <p class="mt-1 text-sm text-slate-600">Confirm the fix and submit the required satisfaction rating before proceeding.</p>
            </div>
            <form action="{{ route('client.tickets.resolve', $ticket) }}" method="POST" class="space-y-4 p-5" data-submit-feedback>
                @csrf
                @if($hasResolveValidationErrors)
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        Please complete the required fields before resolving this ticket.
                    </div>
                @endif
                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <label for="resolve_rating" class="form-label">Rating (1-5 stars)<span class="ml-1 text-rose-500">*</span></label>
                        <select id="resolve_rating" name="rating" class="form-input" required aria-invalid="{{ $errors->has('rating') ? 'true' : 'false' }}">
                            <option value="">Select rating</option>
                            <option value="5" {{ old('rating') == '5' ? 'selected' : '' }}>5 - Excellent</option>
                            <option value="4" {{ old('rating') == '4' ? 'selected' : '' }}>4 - Good</option>
                            <option value="3" {{ old('rating') == '3' ? 'selected' : '' }}>3 - Average</option>
                            <option value="2" {{ old('rating') == '2' ? 'selected' : '' }}>2 - Poor</option>
                            <option value="1" {{ old('rating') == '1' ? 'selected' : '' }}>1 - Very Poor</option>
                        </select>
                        @error('rating')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="resolve_comment" class="form-label">Comment / Suggestion / Complaint<span class="ml-1 text-rose-500">*</span></label>
                        <textarea id="resolve_comment" name="comment" rows="4" class="form-input" placeholder="Share your comment, suggestion, or complaint..." required aria-invalid="{{ $errors->has('comment') ? 'true' : 'false' }}">{{ old('comment') }}</textarea>
                        @error('comment')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                    <input id="resolve_confirm_checkbox" name="resolve_confirmation" type="checkbox" value="1" required {{ old('resolve_confirmation') ? 'checked' : '' }} class="ticket-checkbox mt-0.5">
                    <span class="leading-5">I confirm this ticket has been resolved and can be marked as closed for support follow-up.</span>
                </label>
                @error('resolve_confirmation')
                    <p class="-mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" id="resolve-ticket-cancel" class="btn-secondary sm:min-w-[110px]">Cancel</button>
                    <button id="resolve-confirm-submit" type="submit" class="btn-success sm:min-w-[220px] disabled:cursor-not-allowed disabled:opacity-60" data-loading-text="Resolving..." disabled>Confirm Resolve and Submit Rating</button>
                </div>
            </form>
        </div>
    </div>
</div>
