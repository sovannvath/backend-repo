<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Order;
use App\Models\StockRequest;
use App\Models\Notification;
use App\Models\Cart;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot()
    {
        $this->registerPolicies();

        Gate::define('view', function (User $user, $model) {
            if ($model instanceof User) {
                return $user->id === $model->id || $user->isAdmin();
            }
            if ($model instanceof Order) {
                return $user->id === $model->user_id || $user->isAdmin() || $user->isStaff();
            }
            if ($model instanceof StockRequest) {
                return $user->id === $model->requested_by || $user->isAdmin() || $user->isWarehouseManager();
            }
            if ($model instanceof Notification) {
                return $user->id === $model->user_id || $user->isAdmin();
            }
            if ($model instanceof Cart) {
                return $user->id === $model->user_id || $user->isAdmin();
            }
            return $user->isAdmin();
        });

        Gate::define('update', function (User $user, $model) {
            if ($model instanceof User) {
                return $user->id === $model->id || $user->isAdmin();
            }
            if ($model instanceof Notification) {
                return $user->id === $model->user_id || $user->isAdmin();
            }
            if ($model instanceof Cart) {
                return $user->id === $model->user_id || $user->isAdmin();
            }
            return $user->isAdmin();
        });

        Gate::define('delete', function (User $user, $model) {
            if ($model instanceof Notification) {
                return $user->id === $model->user_id || $user->isAdmin();
            }
            if ($model instanceof Cart) {
                return $user->id === $model->user_id || $user->isAdmin();
            }
            return $user->isAdmin();
        });
    }
}