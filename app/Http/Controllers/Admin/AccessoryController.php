<?php

namespace App\Http\Controllers\Admin;

use App\Models\Accessory;

class AccessoryController extends CatalogItemController
{
    protected function modelClass(): string
    {
        return Accessory::class;
    }

    protected function kind(): string
    {
        return 'accessory';
    }
}
