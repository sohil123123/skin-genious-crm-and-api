<?php

namespace App\Support\MediaLibrary;

use App\Models\Expense;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * Stores purchase / expense invoices under their own folder on the disk,
 * e.g. "expense_invoice/{media id}/file.pdf", instead of "{media id}/file.pdf".
 */
class InvoicePathGenerator extends DefaultPathGenerator
{
    public const FOLDERS = [
        Expense::class => 'expense_invoice',
        Purchase::class => 'purchase_invoice',
    ];

    protected function getBasePath(Media $media): string
    {
        $model = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

        $folder = self::FOLDERS[$model] ?? null;

        return $folder
            ? $folder.'/'.$media->getKey()
            : parent::getBasePath($media);
    }
}
