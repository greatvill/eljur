<?php

namespace App\Http\Resources\PO;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PedObservationCollection extends ResourceCollection
{
    public static $wrap = null;
    public $collects = PedObservationItem::class;
}
