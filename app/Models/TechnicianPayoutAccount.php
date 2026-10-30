<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Where Sejuk transfers a technician's forwarded earnings.
 *
 * @property int $id
 * @property int $technician_id
 * @property string $bank_name
 * @property string $account_name
 * @property string $account_number
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['bank_name', 'account_name', 'account_number'])]
class TechnicianPayoutAccount extends Model
{
    /**
     * Account number with all but the last four digits hidden.
     */
    public function maskedNumber(): string
    {
        return str_repeat('•', max(0, strlen($this->account_number) - 4)).substr($this->account_number, -4);
    }
}
