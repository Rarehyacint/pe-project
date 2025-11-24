<?php

namespace App\Rules;

use App\Models\AcademicYear;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoAcademicYearCollisions implements ValidationRule
{
 protected $startDate;
    protected $idToIgnore;

    public function __construct($startDate, $idToIgnore = null)
    {
        $this->startDate = $startDate;
        $this->idToIgnore = $idToIgnore;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
        {
            $endDate = $value;
            $start_date_formatted = \Carbon\Carbon::parse($this->startDate)->toDateString();
            $end_date_formatted = \Carbon\Carbon::parse($endDate)->toDateString();

            $query = AcademicYear::where(function ($query) use ($start_date_formatted, $end_date_formatted) {
                    $query->whereBetween('start_date', [$start_date_formatted, $end_date_formatted])
                        ->orWhereBetween('end_date', [$start_date_formatted, $end_date_formatted])
                        ->orWhere(function ($query) use ($start_date_formatted, $end_date_formatted) {
                            $query->where('start_date', '<=', $start_date_formatted)
                                    ->where('end_date', '>=', $end_date_formatted);
                        });
                });

            if ($this->idToIgnore) {
                $query->where('id', '!=', $this->idToIgnore);
            }

            if ($query->count() > 0) {
                $fail('The academic year dates overlap with an existing academic year.');
            }
        }
}
