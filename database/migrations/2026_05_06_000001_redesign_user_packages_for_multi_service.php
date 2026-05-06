<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. Create `user_package_items` table
        // ──────────────────────────────────────────────────────────────
        Schema::create('user_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_package_id')->constrained('user_packages')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('service_snapshot')->nullable();
            $table->unsignedInteger('quantity')->default(1);        // Sessions purchased
            $table->unsignedInteger('used_sessions')->default(0);
            $table->decimal('price_per_unit', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);     // price_per_unit × quantity
            $table->timestamps();

            // Indexes for performance
            $table->index('user_package_id');
            $table->index('service_id');
        });

        // ──────────────────────────────────────────────────────────────
        // 2. Migrate existing single-service data into `user_package_items`
        // ──────────────────────────────────────────────────────────────
        // $existingPackages = DB::table('user_packages')
        //     ->whereNotNull('service_id')
        //     ->get();

        // foreach ($existingPackages as $pkg) {
        //     DB::table('user_package_items')->insert([
        //         'user_package_id'  => $pkg->id,
        //         'service_id'       => $pkg->service_id,
        //         'service_snapshot' => $pkg->service_snapshot,
        //         'quantity'         => $pkg->quantity,
        //         'used_sessions'    => $pkg->used_sessions,
        //         'price_per_unit'   => $pkg->price_per_unit ?? 0,
        //         'total_amount'     => ($pkg->price_per_unit ?? 0) * $pkg->quantity,
        //         'created_at'       => $pkg->created_at,
        //         'updated_at'       => $pkg->updated_at,
        //     ]);
        // }

        // ──────────────────────────────────────────────────────────────
        // 3. Add `user_package_item_id` to `user_package_usages`
        //    and migrate existing FK references
        // ──────────────────────────────────────────────────────────────
        Schema::table('user_package_usages', function (Blueprint $table) {
            $table->foreignId('user_package_item_id')
                ->nullable()
                ->after('user_package_id')
                ->constrained('user_package_items')
                ->cascadeOnDelete();
        });

        // // Map existing usage rows to their corresponding new item row
        // $usages = DB::table('user_package_usages')->get();
        // foreach ($usages as $usage) {
        //     $item = DB::table('user_package_items')
        //         ->where('user_package_id', $usage->user_package_id)
        //         ->first();

        //     if ($item) {
        //         DB::table('user_package_usages')
        //             ->where('id', $usage->id)
        //             ->update(['user_package_item_id' => $item->id]);
        //     }
        // }

        // ──────────────────────────────────────────────────────────────
        // 4. Rename `total_amount` → `subtotal` on user_packages
        //    and drop single-service columns
        // ──────────────────────────────────────────────────────────────
        Schema::table('user_packages', function (Blueprint $table) {
            $table->renameColumn('total_amount', 'subtotal');
        });

        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn([
                'service_id',
                'service_snapshot',
                'quantity',
                'used_sessions',
                'price_per_unit',
            ]);
        });
    }

    public function down(): void
    {
        // Restore columns on user_packages
        Schema::table('user_packages', function (Blueprint $table) {
            $table->renameColumn('subtotal', 'total_amount');
        });

        Schema::table('user_packages', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('user_id')->constrained('products')->nullOnDelete();
            $table->json('service_snapshot')->nullable()->after('service_id');
            $table->unsignedInteger('quantity')->default(1)->after('notes');
            $table->unsignedInteger('used_sessions')->default(0)->after('quantity');
            $table->decimal('price_per_unit', 10, 2)->nullable()->after('used_sessions');
        });

        // Drop new column from usages
        Schema::table('user_package_usages', function (Blueprint $table) {
            $table->dropForeign(['user_package_item_id']);
            $table->dropColumn('user_package_item_id');
        });

        Schema::dropIfExists('user_package_items');
    }
};
