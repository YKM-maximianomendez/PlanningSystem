<?php

namespace App\Http\Controllers\MRP\Workflow;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DeliveryInstructionController extends Controller
{
    public function index(string $vendorCode)
    {
        $deliveryInstructions = [];

        return Inertia::render('mrp/delivery-instruction/index', [
            'deliveryInstructions' => $deliveryInstructions,
        ]);
    }
}
