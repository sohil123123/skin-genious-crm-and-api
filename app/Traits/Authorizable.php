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
        'index' => 'ViewAny',
        'show' => 'View',
        'create' => 'Create',
        'store' => 'Create',
        'edit' => 'Update',
        'update' => 'Update',
        'destroy' => 'Delete',
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
        $action = Arr::get($this->getAbilities(), $method);
        
        if (!$action) {
            return null;
        }

        $modelName = null;

        if (property_exists($this, 'model') && $this->model) {
            $modelName = class_basename($this->model);
        }

        if (!$modelName) {
            $modelName = str_replace('Controller', '', class_basename($this));
        }

        return "{$action}:{$modelName}";
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
