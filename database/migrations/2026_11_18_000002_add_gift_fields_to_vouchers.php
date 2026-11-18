<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voucher tặng riêng một khách (tri ân khách mua nhiều): chỉ khách đó thấy và dùng được.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('active')->constrained('users')->nullOnDelete();
            $table->string('note', 150)->default('')->after('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn('note');
        });
    }
};
