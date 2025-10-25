<?php

namespace App\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

trait Authorizable
{
    /**
     * Default controller method to permission mappings
     */
    private array $abilities = [
        'index' => 'view',
        'show' => 'view',
        'create' => 'create',
        'store' => 'add',
        'edit' => 'edit',
        'update' => 'edit',
        'destroy' => 'delete',
    ];

    /**
     * Methods to exclude from authorization check
     */
    protected array $exceptAuthorization = [];

    /**
     * Override callAction to perform authorization automatically
     */
    public function callAction($method, $parameters)
    {
        if (!in_array($method, $this->exceptAuthorization)) {
            if ($permission = $this->getPermission($method)) {
                // dd($permission);
                $this->authorize($permission);
            }
        }

        return parent::callAction($method, $parameters);
    }

    /**
     * Get the permission name for the given controller method
     */
    protected function getPermission(string $method): ?string
    {
        $route = Route::currentRouteName();

        if (!$route) {
            return null;
        }

        $action = Arr::get($this->getAbilities(), $method);
        if (!$action) {
            return null;
        }

        // Extract the resource from route name: `admin.posts.edit` → `posts`
        $parts = explode('.', $route);
        $resource = $parts[count($parts) - 2] ?? $parts[0];

        return "{$action}_{$resource}";
    }

    /**
     * Get ability mappings
     */
    protected function getAbilities(): array
    {
        return $this->abilities;
    }

    /**
     * Override abilities at runtime
     */
    public function setAbilities(array $abilities): self
    {
        $this->abilities = $abilities;
        return $this;
    }

    /**
     * Set methods that should skip authorization
     */
    public function skipAuthorizationFor(array $methods): self
    {
        $this->exceptAuthorization = $methods;
        return $this;
    }
}
