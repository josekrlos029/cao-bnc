<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Detalle de Transacción</h1>
        
        <div class="mb-4">
            <a href="<?php echo e(route('transactions.index')); ?>" class="text-blue-600 hover:text-blue-800">← Volver a la lista</a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-semibold mb-2">Información Básica</h3>
                <dl class="space-y-2">
                    <div>
                        <dt class="font-medium text-gray-700">Número de Orden:</dt>
                        <dd class="text-gray-900"><?php echo e($transaction->order_number); ?></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700">Tipo:</dt>
                        <dd class="text-gray-900"><?php echo e(ucfirst(str_replace('_', ' ', $transaction->transaction_type))); ?></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700">Activo:</dt>
                        <dd class="text-gray-900"><?php echo e($transaction->asset_type); ?> <?php if($transaction->fiat_type): ?>/ <?php echo e($transaction->fiat_type); ?><?php endif; ?></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700">Estado:</dt>
                        <dd class="text-gray-900"><?php echo e(ucfirst($transaction->status)); ?></dd>
                    </div>
                </dl>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold mb-2">Información Financiera</h3>
                <dl class="space-y-2">
                    <?php if($transaction->quantity): ?>
                    <div>
                        <dt class="font-medium text-gray-700">Cantidad:</dt>
                        <dd class="text-gray-900"><?php echo e(number_format($transaction->quantity, 8)); ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($transaction->price): ?>
                    <div>
                        <dt class="font-medium text-gray-700">Precio:</dt>
                        <dd class="text-gray-900"><?php echo e(number_format($transaction->price, 8)); ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($transaction->total_price): ?>
                    <div>
                        <dt class="font-medium text-gray-700">Precio Total:</dt>
                        <dd class="text-gray-900"><?php echo e(number_format($transaction->total_price, 8)); ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <?php if($transaction->notes): ?>
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-2">Notas</h3>
            <p class="text-gray-700"><?php echo e($transaction->notes); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-2">Información del Sistema</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="font-medium text-gray-700">ID:</dt>
                    <dd class="text-gray-900">#<?php echo e($transaction->id); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Fuente:</dt>
                    <dd class="text-gray-900">
                        <?php if($transaction->is_manual_entry): ?>
                            Entrada Manual
                        <?php else: ?>
                            Sincronizado
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Creado:</dt>
                    <dd class="text-gray-900"><?php echo e($transaction->created_at->format('d/m/Y H:i:s')); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Actualizado:</dt>
                    <dd class="text-gray-900"><?php echo e($transaction->updated_at->format('d/m/Y H:i:s')); ?></dd>
                </div>
            </dl>
        </div>
        
        <?php if($transaction->is_manual_entry): ?>
        <div class="mt-6">
            <a href="<?php echo e(route('transactions.edit', $transaction)); ?>" 
               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                ✏️ Editar Transacción
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/transactions/show.blade.php ENDPATH**/ ?>