<?php

namespace App\Http\Requests\Budget;

use App\Http\Requests\Budget\Concerns\HasBudgetItemValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetItemRequest extends FormRequest
{
    use HasBudgetItemValidation;
}
