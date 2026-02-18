<?php $__env->startSection('title', 'Ticket #' . $ticket->ticket_number . ' - iOne Resources'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="<?php echo e(route('client.tickets.index')); ?>" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Tickets
        </a>
    </div>

    <!-- Ticket Header -->
    <div class="bg-white shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900"><?php echo e($ticket->subject); ?></h1>
                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                        <span class="font-medium"><?php echo e($ticket->ticket_number); ?></span>
                        <span>•</span>
                        <span>Created <?php echo e($ticket->created_at->format('M j, Y \a\t g:i A')); ?></span>
                        <?php if($ticket->assignedUser): ?>
                            <span>•</span>
                            <span>Assigned to <?php echo e($ticket->assignedUser->name); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo e($ticket->status_color); ?>">
                        <?php echo e(ucfirst(str_replace('_', ' ', $ticket->status))); ?>

                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo e($ticket->priority_color); ?>">
                        <?php echo e(ucfirst($ticket->priority)); ?>

                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Original Message -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Original Message</h3>
                    <p class="mt-1 text-sm text-gray-500"><?php echo e($ticket->created_at->diffForHumans()); ?></p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <div class="prose max-w-none">
                        <?php echo nl2br(e($ticket->description)); ?>

                    </div>
                    <?php if($ticket->attachments->count() > 0): ?>
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Attachments</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php $__currentLoopData = $ticket->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <a href="<?php echo e(Storage::url($attachment->file_path)); ?>" target="_blank"
                                       class="flex items-center p-3 border border-gray-200 rounded-md hover:bg-gray-50">
                                        <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo e($attachment->original_filename); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo e(number_format($attachment->file_size / 1024, 1)); ?> KB</div>
                                        </div>
                                    </a>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Replies -->
            <?php $__currentLoopData = $ticket->replies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reply): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(!$reply->is_internal): ?>
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <h4 class="text-sm font-medium text-gray-900">
                                        <?php echo e($reply->user->name); ?>

                                        <?php if($reply->user->role === 'admin' || $reply->user->role === 'super_admin'): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                Support Team
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                </div>
                                <p class="text-sm text-gray-500"><?php echo e($reply->created_at->diffForHumans()); ?></p>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                            <div class="prose max-w-none">
                                <?php echo nl2br(e($reply->message)); ?>

                            </div>
                            <?php if($reply->attachments && $reply->attachments->count() > 0): ?>
                                <div class="mt-4">
                                    <h5 class="text-sm font-medium text-gray-900 mb-2">Attachments</h5>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <?php $__currentLoopData = $reply->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <a href="<?php echo e(Storage::url($attachment->file_path)); ?>" target="_blank"
                                               class="flex items-center p-2 border border-gray-200 rounded hover:bg-gray-50 text-sm">
                                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                </svg>
                                                <?php echo e($attachment->original_filename); ?>

                                            </a>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <!-- Reply Form -->
            <?php if(!in_array($ticket->status, ['closed'])): ?>
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Add Reply</h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <form action="<?php echo e(route('client.tickets.reply', $ticket)); ?>" method="POST" enctype="multipart/form-data" class="px-4 py-5 sm:px-6">
                            <?php echo csrf_field(); ?>
                            <div class="space-y-4">
                                <div>
                                    <label for="message" class="form-label">Message</label>
                                    <textarea name="message" id="message" rows="5" required
                                            class="form-input" placeholder="Type your reply..."></textarea>
                                </div>

                                <div>
                                    <label for="attachments" class="form-label">Attachments (optional)</label>
                                    <input type="file" name="attachments[]" id="attachments" multiple
                                           class="form-input" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
                                    <p class="mt-1 text-xs text-gray-500">Max 10MB per file. Allowed: JPG, PNG, PDF, DOC, DOCX, TXT</p>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary">
                                        Add Reply
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Ticket Details -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Ticket Details</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Category</dt>
                            <dd class="text-sm text-gray-900"><?php echo e($ticket->category->name); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Priority</dt>
                            <dd class="text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ticket->priority_color); ?>">
                                    <?php echo e(ucfirst($ticket->priority)); ?>

                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ticket->status_color); ?>">
                                    <?php echo e(ucfirst(str_replace('_', ' ', $ticket->status))); ?>

                                </span>
                            </dd>
                        </div>
                        <?php if($ticket->assignedUser): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Assigned To</dt>
                                <dd class="text-sm text-gray-900"><?php echo e($ticket->assignedUser->name); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if($ticket->due_date): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Due Date</dt>
                                <dd class="text-sm <?php echo e($ticket->due_date->isPast() ? 'text-red-600' : 'text-gray-900'); ?>">
                                    <?php echo e($ticket->due_date->format('M j, Y \a\t g:i A')); ?>

                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if($ticket->resolved_at): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Resolved At</dt>
                                <dd class="text-sm text-gray-900"><?php echo e($ticket->resolved_at->format('M j, Y \a\t g:i A')); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if($ticket->closed_at): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Closed At</dt>
                                <dd class="text-sm text-gray-900"><?php echo e($ticket->closed_at->format('M j, Y \a\t g:i A')); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Actions</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6 space-y-4">
                    <?php if($ticket->status === 'resolved' && !$ticket->satisfaction_rating): ?>
                        <!-- Satisfaction Rating -->
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-green-800 mb-3">Rate Our Support</h4>
                            <form action="<?php echo e(route('client.tickets.rate', $ticket)); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <div class="space-y-3">
                                    <div>
                                        <label class="form-label text-green-700">Rating (1-5 stars)</label>
                                        <select name="rating" class="form-input" required>
                                            <option value="">Select Rating</option>
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Good</option>
                                            <option value="3">3 - Average</option>
                                            <option value="2">2 - Poor</option>
                                            <option value="1">1 - Very Poor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-green-700">Comment (optional)</label>
                                        <textarea name="comment" rows="3" class="form-input" placeholder="Tell us about your experience..."></textarea>
                                    </div>
                                    <button type="submit" class="btn-success w-full">Submit Rating</button>
                                </div>
                            </form>
                        </div>
                    <?php elseif($ticket->satisfaction_rating): ?>
                        <!-- Show Rating -->
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Your Rating</h4>
                            <div class="flex items-center mb-2">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-4 h-4 <?php echo e($i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-gray-300'); ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <?php if($ticket->satisfaction_comment): ?>
                                <p class="text-sm text-blue-700"><?php echo e($ticket->satisfaction_comment); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if(!in_array($ticket->status, ['closed'])): ?>
                        <!-- Close Ticket -->
                        <form action="<?php echo e(route('client.tickets.close', $ticket)); ?>" method="POST" onsubmit="return confirm('Are you sure you want to close this ticket?')">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <button type="submit" class="btn-secondary w-full">
                                Close Ticket
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /opt/DAR/ione-ticketing-system/resources/views/client/tickets/show.blade.php ENDPATH**/ ?>