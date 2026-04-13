<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Actions</h3>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:px-6 space-y-4">
        @if($ticket->status === 'resolved' && !$ticket->satisfaction_rating)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-slate-900">Rate Our Support</h4>
                    <p class="mt-1 text-sm text-slate-600">This feedback is now required to complete the client resolution flow.</p>
                </div>
                <form action="{{ route('client.tickets.rate', $ticket) }}" method="POST">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="form-label">Rating (1-5 stars)<span class="ml-1 text-rose-500">*</span></label>
                            <select name="rating" class="form-input" required>
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
                            <label class="form-label">Comment / Suggestion / Complaint<span class="ml-1 text-rose-500">*</span></label>
                            <textarea name="comment" rows="3" class="form-input" placeholder="Share your comment, suggestion, or complaint..." required>{{ old('comment') }}</textarea>
                            @error('comment')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn-success w-full">Submit Rating</button>
                    </div>
                </form>
            </div>
        @elseif($ticket->satisfaction_rating)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h4 class="text-sm font-semibold text-slate-900 mb-2">Your Rating</h4>
                <div class="flex items-center mb-2">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 {{ $i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    @endfor
                </div>
                @if($ticket->satisfaction_comment)
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Comment / Suggestion / Complaint</p>
                    <p class="text-sm text-slate-600">{{ $ticket->satisfaction_comment }}</p>
                @endif
            </div>
        @endif

        @if(!in_array($ticket->status, ['resolved', 'closed']))
            <button type="button" id="open-resolve-ticket-modal" class="btn-success w-full">
                Mark as Resolved
            </button>
        @endif
    </div>
</div>
