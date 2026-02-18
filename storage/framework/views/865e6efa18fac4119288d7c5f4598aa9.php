<?php $__env->startSection('title', 'User Details - iOne Resources Ticketing'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="<?php echo e(route('admin.users.index')); ?>" class="text-gray-500 hover:text-gray-700 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">User Details</h1>
                    <p class="mt-1 text-sm text-gray-600">View user information and activity</p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="<?php echo e(route('admin.users.edit', $user)); ?>" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Information -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">User Information</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Personal details and account information</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full name</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($user->name); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email address</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($user->email); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone number</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($user->phone ?? 'Not provided'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Department</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($user->department ?? 'Not specified'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Role</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php if($user->role === 'super_admin'): ?> bg-purple-100 text-purple-800
                                    <?php elseif($user->role === 'admin'): ?> bg-blue-100 text-blue-800
                                    <?php else: ?> bg-gray-100 text-gray-800
                                    <?php endif; ?>">
                                    <?php echo e(ucfirst(str_replace('_', ' ', $user->role))); ?>

                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo e($user->is_active ? 'Active' : 'Inactive'); ?>

                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account created</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($user->created_at->format('F j, Y \a\t g:i A')); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last updated</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($user->updated_at->format('F j, Y \a\t g:i A')); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="mt-6 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Tickets</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Latest tickets created or assigned to this user</p>
                </div>
                <div class="border-t border-gray-200">
                    <ul class="divide-y divide-gray-200">
                        <?php $__empty_1 = true; $__currentLoopData = $user->tickets()->latest()->take(5)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <li class="px-4 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center min-w-0">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ticket->status_color); ?>">
                                                <?php echo e(ucfirst(str_replace('_', ' ', $ticket->status))); ?>

                                            </span>
                                        </div>
                                        <div class="ml-4 min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                <a href="<?php echo e(route('admin.tickets.show', $ticket)); ?>" class="hover:text-indigo-600">
                                                    <?php echo e($ticket->subject); ?>

                                                </a>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo e($ticket->ticket_number); ?>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ticket->priority_color); ?> mr-2">
                                            <?php echo e(ucfirst($ticket->priority)); ?>

                                        </span>
                                        <div class="text-sm text-gray-500">
                                            <?php echo e($ticket->created_at->diffForHumans()); ?>

                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <li class="px-4 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No tickets</h3>
                                <p class="mt-1 text-sm text-gray-500">This user hasn't created any tickets yet.</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Sidebar -->
        <div class="space-y-6">
            <!-- Statistics Card -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">User Statistics</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Total Tickets</dt>
                            <dd class="text-sm text-gray-900"><?php echo e($user->tickets()->count()); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Open Tickets</dt>
                            <dd class="text-sm text-gray-900"><?php echo e($user->tickets()->whereIn('status', ['open', 'in_progress', 'pending'])->count()); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Closed Tickets</dt>
                            <dd class="text-sm text-gray-900"><?php echo e($user->tickets()->whereIn('status', ['resolved', 'closed'])->count()); ?></dd>
                        </div>
                        <?php if($user->canManageTickets()): ?>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Assigned Tickets</dt>
                                <dd class="text-sm text-gray-900"><?php echo e($user->assignedTickets()->count()); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Account Actions -->
            <?php if($user->id !== auth()->id()): ?>
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Account Actions</h3>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <div class="space-y-3">
                            <button onclick="toggleUserStatus(<?php echo e($user->id); ?>, <?php echo e($user->is_active ? 'false' : 'true'); ?>)"
                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                                <?php echo e($user->is_active ? 'Deactivate Account' : 'Activate Account'); ?>

                            </button>
                            <?php if(!$user->isSuperAdmin()): ?>
                                <button onclick="deleteUser(<?php echo e($user->id); ?>)"
                                        class="w-full text-left px-3 py-2 text-sm text-red-700 hover:bg-red-50 rounded-md">
                                    Delete Account
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-2">Delete User</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to delete this user? This action cannot be undone.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300">
                    Delete
                </button>
                <button onclick="closeDeleteModal()" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function deleteUser(userId) {
    document.getElementById('deleteModal').classList.remove('hidden');

    document.getElementById('confirmDelete').onclick = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/users/' + userId;

        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';

        const tokenField = document.createElement('input');
        tokenField.type = 'hidden';
        tokenField.name = '_token';
        tokenField.value = '<?php echo e(csrf_token()); ?>';

        form.appendChild(methodField);
        form.appendChild(tokenField);
        document.body.appendChild(form);
        form.submit();
    };
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function toggleUserStatus(userId, newStatus) {
    fetch(`/admin/users/${userId}/toggle-status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
        },
        body: JSON.stringify({
            is_active: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /opt/DAR/ione-ticketing-system/resources/views/admin/users/show.blade.php ENDPATH**/ ?>