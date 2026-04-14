<div class="space-y-6 lg:col-span-4">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">User Statistics</h3>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <dl class="space-y-4">
                <div>
                    <a href="{{ $statisticsLinks['total_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                        <dt class="text-sm font-medium text-gray-500">Total Tickets</dt>
                        <dd class="text-sm text-gray-900">{{ $statistics['total_tickets'] }}</dd>
                    </a>
                </div>
                <div>
                    <a href="{{ $statisticsLinks['open_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                        <dt class="text-sm font-medium text-gray-500">Open Tickets</dt>
                        <dd class="text-sm text-gray-900">{{ $statistics['open_tickets'] }}</dd>
                    </a>
                </div>
                <div>
                    <a href="{{ $statisticsLinks['closed_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                        <dt class="text-sm font-medium text-gray-500">Closed Tickets</dt>
                        <dd class="text-sm text-gray-900">{{ $statistics['closed_tickets'] }}</dd>
                    </a>
                </div>
                @if($statistics['show_assigned'])
                    <div>
                        <a href="{{ $statisticsLinks['assigned_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                            <dt class="text-sm font-medium text-gray-500">Assigned Tickets</dt>
                            <dd class="text-sm text-gray-900">{{ $statistics['assigned_tickets'] }}</dd>
                        </a>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    @if($canRevealManagedPassword ?? false)
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Password Access</h3>
                <p class="mt-1 text-sm text-gray-500">Shadow-only credential tools for this account.</p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6 space-y-3">
                @if(!empty($revealedManagedPassword))
                    <div>
                        <label for="managedUserPassword" class="text-sm font-medium text-gray-700">Temporary Login Password (One-Time Reveal)</label>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                id="managedUserPassword"
                                type="password"
                                readonly
                                value="{{ $revealedManagedPassword }}"
                                class="block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900"
                            >
                            <button
                                type="button"
                                id="toggleManagedUserPassword"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                aria-pressed="false"
                            >
                                Show
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-rose-700">Copy this temporary password now. It will not be shown again.</p>
                    </div>
                @elseif($activeCredentialHandoff)
                    <p class="text-xs text-sky-700">
                        A temporary password is ready and expires at {{ optional($activeCredentialHandoff->expires_at)->format('M j, Y g:i A') }}.
                    </p>
                    <form method="POST" action="{{ route('admin.users.password.reveal-temporary', $user) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md border border-sky-300 px-3 py-2 text-xs font-semibold text-sky-800 transition hover:bg-sky-50">
                            Reveal Temporary Password
                        </button>
                    </form>
                @else
                    <p class="text-xs text-amber-700">No active temporary password is available for this account.</p>
                    <form method="POST" action="{{ route('admin.users.password.reset-default', $user) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">
                            Issue Temporary Password
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if($user->id !== auth()->id())
        @php
            $currentViewer = auth()->user();
            $canToggleAccount = $currentViewer->can('toggleStatus', $user);
            $canDeleteAccount = $currentViewer->can('delete', $user);
        @endphp
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Account Actions</h3>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <div class="space-y-3">
                    @if($canToggleAccount)
                        <button
                            type="button"
                            class="js-toggle-user-status w-full rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                            data-user-id="{{ $user->id }}"
                            data-user-name="{{ $user->name }}"
                            data-new-status="{{ $user->is_active ? '0' : '1' }}">
                            {{ $user->is_active ? 'Deactivate Account' : 'Activate Account' }}
                        </button>
                    @endif
                    @if($canDeleteAccount)
                        <button
                            type="button"
                            class="delete-user-btn w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                            data-user-id="{{ $user->id }}"
                            data-user-name="{{ $user->name }}">
                            Delete Account
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
