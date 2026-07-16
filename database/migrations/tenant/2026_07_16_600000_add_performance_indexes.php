<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders_of_service', function (Blueprint $table) {
            $table->index(['status', 'expected_end_date'], 'oos_status_expected_end_idx');
            $table->index('actual_end_date', 'oos_actual_end_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'issue_date'], 'invoices_status_issue_idx');
            $table->index('created_at', 'invoices_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_auditable_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_issue_idx');
            $table->dropIndex('invoices_created_at_idx');
        });

        Schema::table('orders_of_service', function (Blueprint $table) {
            $table->dropIndex('oos_status_expected_end_idx');
            $table->dropIndex('oos_actual_end_idx');
        });
    }
};
