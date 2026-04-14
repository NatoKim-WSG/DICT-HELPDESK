@if(auth()->user()->isSuperAdmin())
    <div class="mb-6 inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
        <a href="{{ route('admin.users.index') }}"
           class="rounded-md px-3 py-1.5 font-medium {{ ($segment ?? 'staff') === 'staff' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Staff
        </a>
        <a href="{{ route('admin.users.clients') }}"
           class="rounded-md px-3 py-1.5 font-medium {{ ($segment ?? 'staff') === 'clients' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Clients
        </a>
    </div>
@endif
