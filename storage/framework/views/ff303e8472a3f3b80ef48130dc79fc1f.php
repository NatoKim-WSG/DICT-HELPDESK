<?php $__env->startSection('title', 'All Tickets - iOne Resources Admin'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">All Tickets</h1>
                <p class="mt-1 text-sm text-gray-600">Manage all support tickets in the system</p>
            </div>
            <div class="flex space-x-3">
                <a href="<?php echo e(route('admin.dashboard')); ?>" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 13-3 3-3-3"/>
                    </svg>
                    Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo e(request('search')); ?>"
                           class="form-input" placeholder="Search tickets...">
                </div>

                <div>
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-input">
                        <option value="all">All Statuses</option>
                        <option value="open" <?php echo e(request('status') === 'open' ? 'selected' : ''); ?>>Open</option>
                        <option value="in_progress" <?php echo e(request('status') === 'in_progress' ? 'selected' : ''); ?>>In Progress</option>
                        <option value="pending" <?php echo e(request('status') === 'pending' ? 'selected' : ''); ?>>Pending</option>
                        <option value="resolved" <?php echo e(request('status') === 'resolved' ? 'selected' : ''); ?>>Resolved</option>
                        <option value="closed" <?php echo e(request('status') === 'closed' ? 'selected' : ''); ?>>Closed</option>
                    </select>
                </div>

                <div>
                    <label for="priority" class="form-label">Priority</label>
                    <select name="priority" id="priority" class="form-input">
                        <option value="all">All Priorities</option>
                        <option value="urgent" <?php echo e(request('priority') === 'urgent' ? 'selected' : ''); ?>>Urgent</option>
                        <option value="high" <?php echo e(request('priority') === 'high' ? 'selected' : ''); ?>>High</option>
                        <option value="medium" <?php echo e(request('priority') === 'medium' ? 'selected' : ''); ?>>Medium</option>
                        <option value="low" <?php echo e(request('priority') === 'low' ? 'selected' : ''); ?>>Low</option>
                    </select>
                </div>

                <div>
                    <label for="category" class="form-label">Category</label>
                    <select name="category" id="category" class="form-input">
                        <option value="all">All Categories</option>
                        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($category->id); ?>" <?php echo e(request('category') == $category->id ? 'selected' : ''); ?>>
                                <?php echo e($category->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div>
                    <label for="assigned_to" class="form-label">Assigned To</label>
                    <select name="assigned_to" id="assigned_to" class="form-input">
                        <option value="all">All Admins</option>
                        <option value="unassigned" <?php echo e(request('assigned_to') === 'unassigned' ? 'selected' : ''); ?>>Unassigned</option>
                        <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($agent->id); ?>" <?php echo e(request('assigned_to') == $agent->id ? 'selected' : ''); ?>>
                                <?php echo e($agent->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="flex items-end col-span-full">
                    <button type="submit" class="btn-primary mr-2">Filter</button>
                    <a href="<?php echo e(route('admin.tickets.index')); ?>" class="btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <?php if($tickets->count() > 0): ?>
            <ul class="divide-y divide-gray-200">
                <?php $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>
                        <a href="<?php echo e(route('admin.tickets.show', $ticket)); ?>" class="block hover:bg-gray-50 px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ticket->status_color); ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $ticket->status))); ?>

                                        </span>
                                    </div>
                                    <div class="ml-4 min-w-0 flex-1">
                                        <div class="text-sm font-medium text-gray-900 truncate">
                                            <?php echo e($ticket->subject); ?>

                                        </div>
                                        <div class="text-sm text-gray-500 flex items-center mt-1">
                                            <span><?php echo e($ticket->ticket_number); ?></span>
                                            <span class="mx-2">•</span>
                                            <span><?php echo e($ticket->user->name); ?> (<?php echo e($ticket->user->email); ?>)</span>
                                            <span class="mx-2">•</span>
                                            <span><?php echo e($ticket->category->name); ?></span>
                                            <?php if($ticket->assignedUser): ?>
                                                <span class="mx-2">•</span>
                                                <span>Assigned to <?php echo e($ticket->assignedUser->name); ?></span>
                                            <?php else: ?>
                                                <span class="mx-2">•</span>
                                                <span class="text-red-600">Unassigned</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ticket->priority_color); ?> mr-3">
                                        <?php echo e(ucfirst($ticket->priority)); ?>

                                    </span>
                                    <div class="text-sm text-gray-500 text-right">
                                        <div><?php echo e($ticket->created_at->format('M j, Y')); ?></div>
                                        <div><?php echo e($ticket->created_at->diffForHumans()); ?></div>
                                        <?php if($ticket->due_date): ?>
                                            <div class="text-xs <?php echo e($ticket->due_date->isPast() ? 'text-red-600' : 'text-yellow-600'); ?>">
                                                Due: <?php echo e($ticket->due_date->format('M j')); ?>

                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <svg class="ml-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <?php echo e($tickets->appends(request()->query())->links()); ?>

            </div>
        <?php else: ?>
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tickets found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?php if(request()->hasAny(['search', 'status', 'priority', 'category', 'assigned_to']) && !request()->query->every(fn($value) => in_array($value, ['all', '']))): ?>
                        No tickets match your current filters.
                    <?php else: ?>
                        No support tickets have been created yet.
                    <?php endif; ?>
                </p>
                <div class="mt-6">
                    <?php if(request()->hasAny(['search', 'status', 'priority', 'category', 'assigned_to']) && !request()->query->every(fn($value) => in_array($value, ['all', '']))): ?>
                        <a href="<?php echo e(route('admin.tickets.index')); ?>" class="btn-secondary">
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\iOne5\Desktop\app\Ticketing\ione-ticketing-system\resources\views/admin/tickets/index.blade.php ENDPATH**/ ?>