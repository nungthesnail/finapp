<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiModelController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BudgetPlanController;
use App\Http\Controllers\CategoryDefaultController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IncomeCategoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'status']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/me', [ProfileController::class, 'me']);
    Route::put('/me', [ProfileController::class, 'update']);

    Route::get('/admin/users', [AdminController::class, 'users']);

    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/{account}', [AccountController::class, 'show']);
    Route::put('/accounts/{account}', [AccountController::class, 'update']);
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);

    Route::get('/income-categories', [IncomeCategoryController::class, 'index']);
    Route::post('/income-categories', [IncomeCategoryController::class, 'store']);
    Route::put('/income-categories/{id}', [IncomeCategoryController::class, 'update']);
    Route::delete('/income-categories/{id}', [IncomeCategoryController::class, 'destroy']);

    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store']);
    Route::put('/expense-categories/{id}', [ExpenseCategoryController::class, 'update']);
    Route::delete('/expense-categories/{id}', [ExpenseCategoryController::class, 'destroy']);

    Route::get('/users/category-defaults', [CategoryDefaultController::class, 'show']);
    Route::put('/users/category-defaults', [CategoryDefaultController::class, 'update']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    Route::get('/recurring-transactions', [RecurringTransactionController::class, 'index']);
    Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store']);
    Route::put('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'update']);
    Route::delete('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'destroy']);

    Route::get('/budget-plans', [BudgetPlanController::class, 'index']);
    Route::post('/budget-plans', [BudgetPlanController::class, 'store']);
    Route::put('/budget-plans/{budgetPlan}', [BudgetPlanController::class, 'update']);
    Route::delete('/budget-plans/{budgetPlan}', [BudgetPlanController::class, 'destroy']);

    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/timeseries', [AnalyticsController::class, 'timeseries']);
    Route::get('/analytics/categories', [AnalyticsController::class, 'categories']);

    Route::get('/ai/chats', [AiChatController::class, 'index']);
    Route::post('/ai/chats', [AiChatController::class, 'create']);
    Route::get('/ai/models', [AiModelController::class, 'index']);
    Route::get('/ai/chats/last-active', [AiChatController::class, 'lastActive']);
    Route::get('/ai/chats/{conversationId}/messages', [AiChatController::class, 'messages']);
    Route::post('/ai/chats/{conversationId}/messages/stream', [AiChatController::class, 'stream']);

    Route::get('/tariffs', [BillingController::class, 'tariffs']);
    Route::get('/billing/overview', [BillingController::class, 'overview']);
    Route::post('/subscriptions/checkout', [BillingController::class, 'checkout']);
    Route::post('/payments/yoomoney/webhook', [BillingController::class, 'webhook']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/push/subscriptions', [NotificationController::class, 'savePushSubscription']);

    Route::get('/support/chats', [SupportController::class, 'userChats']);
    Route::post('/support/chats', [SupportController::class, 'createUserChat']);
    Route::get('/support/chats/{chatId}/messages', [SupportController::class, 'userMessages']);
    Route::post('/support/chats/{chatId}/messages', [SupportController::class, 'sendUserMessage']);

    Route::get('/admin/support/chats', [SupportController::class, 'adminChats']);
    Route::get('/admin/support/chats/{chatId}/messages', [SupportController::class, 'adminMessages']);
    Route::post('/admin/support/chats/{chatId}/messages', [SupportController::class, 'sendAdminMessage']);
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/admin/ai/models', [AiModelController::class, 'adminIndex']);
    Route::post('/admin/ai/models', [AiModelController::class, 'store']);
    Route::put('/admin/ai/models/{id}', [AiModelController::class, 'update']);
    Route::post('/admin/subscriptions/{subscriptionId}/cancel', [AdminController::class, 'cancelSubscription']);
    Route::post('/admin/users/{userId}/credit-adjustment', [AdminController::class, 'adjustCredit']);
    Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs']);
});
