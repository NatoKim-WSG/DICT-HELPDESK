<?php $__env->startSection('title', 'User Management - iOne Resources Ticketing'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
                <p class="mt-1 text-sm text-gray-600">Manage system users and their roles</p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="<?php echo e(route('admin.users.create')); ?>" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add User
                </a>
            </div>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg">
            <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Department
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">
                                                <?php echo e(strtoupper(substr($user->name, 0, 2))); ?>

                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($user->name); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo e($user->email); ?></div>
                                        <?php if($user->phone): ?>
                                            <div class="text-sm text-gray-500"><?php echo e($user->phone); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php if($user->role === 'super_admin'): ?> bg-purple-100 text-purple-800
                                    <?php elseif($user->role === 'admin'): ?> bg-blue-100 text-blue-800
                                    <?php else: ?> bg-gray-100 text-gray-800
                                    <?php endif; ?>">
                                    <?php echo e(ucfirst(str_replace('_', ' ', $user->role))); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e($user->department ?? '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button onclick="toggleUserStatus(<?php echo e($user->id); ?>, <?php echo e($user->is_active ? 'false' : 'true'); ?>)"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer
                                        <?php echo e($user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo e($user->is_active ? 'Active' : 'Inactive'); ?>

                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo e($user->created_at->format('M d, Y')); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-4">
                                    <a href="<?php echo e(route('admin.users.show', $user)); ?>"
                                       class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 transition-colors duration-150">
                                        View
                                    </a>
                                    <?php if(auth()->user()->isSuperAdmin() || ($user->role === 'client')): ?>
                                        <a href="<?php echo e(route('admin.users.edit', $user)); ?>"
                                           class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-blue-600 hover:text-blue-900 hover:bg-blue-50 transition-colors duration-150">
                                            Edit
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-gray-400">
                                            Restricted
                                        </span>
                                    <?php endif; ?>
                                    <?php if($user->role === 'client' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin())): ?>
                                        <button type="button"
                                                class="delete-user-btn inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-red-600 hover:text-red-900 hover:bg-red-50 transition-colors duration-150 cursor-pointer"
                                                data-user-id="<?php echo e($user->id); ?>"
                                                data-user-name="<?php echo e($user->name); ?>"
                                                title="Delete <?php echo e($user->name); ?>">
                                            Delete
                                        </button>
                                    <?php elseif($user->role === 'admin' && auth()->user()->isSuperAdmin() && $user->id !== auth()->id()): ?>
                                        <button type="button"
                                                class="delete-user-btn inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-red-600 hover:text-red-900 hover:bg-red-50 transition-colors duration-150 cursor-pointer"
                                                data-user-id="<?php echo e($user->id); ?>"
                                                data-user-name="<?php echo e($user->name); ?>"
                                                title="Delete <?php echo e($user->name); ?>">
                                            Delete
                                        </button>
                                    <?php elseif($user->isSuperAdmin() && $user->id !== auth()->id()): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    <?php elseif($user->id === auth()->id() && !$user->isSuperAdmin()): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-gray-400">
                                            You
                                        </span>
                                    <?php elseif($user->id === auth()->id() && $user->isSuperAdmin()): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-blue-400">
                                            Super Admin
                                        </span>
                                    <?php elseif(($user->role === 'admin' || $user->isSuperAdmin()) && !auth()->user()->isSuperAdmin()): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No users found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by creating a new user.</p>
                                <div class="mt-6">
                                    <a href="<?php echo e(route('admin.users.create')); ?>" class="btn-primary">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Add User
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($users->hasPages()): ?>
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <?php echo e($users->links()); ?>

            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
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
                    Are you sure you want to delete <strong id="deleteUserName"></strong>? This action cannot be undone.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300">
                    Delete User
                </button>
                <button id="cancelDelete" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let deleteUserId = null;
    let deleteInProgress = false;

    const modal = document.getElementById('deleteModal');
    const confirmButton = document.getElementById('confirmDelete');
    const cancelButton = document.getElementById('cancelDelete');
    const userNameSpan = document.getElementById('deleteUserName');

    // Debug: Check if all elements exist
    console.log('Modal found:', !!modal);
    console.log('Confirm button found:', !!confirmButton);
    console.log('Cancel button found:', !!cancelButton);
    console.log('User name span found:', !!userNameSpan);

    if (modal) {
        console.log('Initial modal classes:', modal.className);
        console.log('Initial modal display:', window.getComputedStyle(modal).display);
    }

    // Function to show modal
    function showDeleteModal(userId, userName) {
        if (deleteInProgress) {
            console.log('Delete already in progress, ignoring request');
            return;
        }

        console.log('Showing delete modal for user:', userId);
        console.log('Modal element exists:', !!modal);
        console.log('Modal classes before:', modal ? modal.className : 'NO MODAL');

        deleteUserId = parseInt(userId);

        if (userNameSpan) {
            userNameSpan.textContent = userName;
            console.log('Set user name to:', userName);
        } else {
            console.log('userNameSpan not found');
        }

        if (modal) {
            modal.classList.remove('hidden');
            // Force modal to show with inline styles
            modal.style.display = 'flex';
            modal.style.zIndex = '9999';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(75, 85, 99, 0.5)';

            console.log('Modal classes after removing hidden:', modal.className);
            console.log('Modal style display:', window.getComputedStyle(modal).display);
            console.log('Modal forced to show with inline styles');
        } else {
            console.log('Modal element not found!');
        }
    }

    // Function to hide modal
    function hideDeleteModal() {
        if (modal) {
            modal.classList.add('hidden');
            // Clear inline styles
            modal.style.display = '';
            modal.style.zIndex = '';
            modal.style.position = '';
            modal.style.top = '';
            modal.style.left = '';
            modal.style.width = '';
            modal.style.height = '';
            modal.style.backgroundColor = '';
        }
        deleteUserId = null;
        deleteInProgress = false;

        // Reset confirm button
        if (confirmButton) {
            confirmButton.disabled = false;
            confirmButton.textContent = 'Delete User';
        }
    }

    // Prevent multiple rapid clicks
    let lastClickTime = 0;
    const CLICK_DELAY = 500; // 500ms between clicks

    // Event delegation for delete buttons
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();

            const now = Date.now();
            if (now - lastClickTime < CLICK_DELAY) {
                console.log('Click ignored - too rapid');
                return;
            }
            lastClickTime = now;

            const userId = deleteBtn.getAttribute('data-user-id');
            const userName = deleteBtn.getAttribute('data-user-name');

            console.log('Delete button clicked for user:', userId);
            showDeleteModal(userId, userName);
        }
    });

    // Confirm delete button
    if (confirmButton) {
        confirmButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (deleteInProgress || !deleteUserId) {
                console.log('Delete already in progress or no user ID:', {deleteInProgress, deleteUserId});
                return;
            }

            deleteInProgress = true;
            console.log('Confirming delete for user:', deleteUserId);

            // Disable the button to prevent double clicks
            confirmButton.disabled = true;
            confirmButton.textContent = 'Deleting...';

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `<?php echo e(route('admin.users.index')); ?>/${deleteUserId}`;
            form.style.display = 'none';

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

            console.log('Submitting delete form to:', form.action);
            console.log('CSRF Token:', tokenField.value);

            hideDeleteModal();

            // Submit form immediately
            form.submit();
        });
    }

    // Cancel button
    if (cancelButton) {
        cancelButton.addEventListener('click', function(e) {
            e.preventDefault();
            hideDeleteModal();
        });
    }

    // Click outside to close
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideDeleteModal();
            }
        });
    }
});

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
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\iOne5\Desktop\app\Ticketing\ione-ticketing-system\resources\views/admin/users/index.blade.php ENDPATH**/ ?>