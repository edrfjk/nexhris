<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveLedgerEntry extends Model
{
    protected $fillable = [
        'user_id', 'period_from', 'period_to', 'year_label', 'remarks', 'type', 'ledger',
        'vl_earned', 'vl_used', 'vl_used_wop', 'vl_balance',
        'sl_earned', 'sl_used', 'sl_used_wop', 'sl_balance',
        'service_earned', 'service_used', 'service_balance',
        'leave_application_id', 'encoded_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveApplication()
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function encoder()
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    /**
     * The "FROM / TO" period as the official ledger card prints it — a single
     * date when the period covers one day, a range otherwise.
     */
    public function periodLabel(): string
    {
        if (! $this->period_from) {
            return '—';
        }

        if (! $this->period_to || $this->period_from->eq($this->period_to)) {
            return $this->period_from->format('M j, Y');
        }

        return $this->period_from->format('M j') . ' – ' . $this->period_to->format('M j, Y');
    }

    /** The card this line is written on. */
    public const LEAVE = 'leave';
    public const SERVICE = 'service';

    public function scopeOnLeaveCard($query)
    {
        return $query->where('ledger', self::LEAVE);
    }

    public function scopeOnServiceCard($query)
    {
        return $query->where('ledger', self::SERVICE);
    }

    public function isOnServiceCard(): bool
    {
        return $this->ledger === self::SERVICE;
    }

    /**
     * True when the row belongs to the service credit card.
     *
     * Which card a line sits on is decided by HR when the leave is posted,
     * not inferred from its figures: a day charged to service credits is
     * still recorded in the sick or vacation column of that card.
     */
    public function touchesServiceCredits(): bool
    {
        return $this->isOnServiceCard();
    }

    /**
     * Days this line takes off the balance of the card it sits on.
     *
     * On the service card a sick or vacation day is charged to service
     * credits, which is the whole reason the two cards are kept apart.
     */
    public function daysCharged(): float
    {
        if ($this->isOnServiceCard()) {
            return round(
                (float) $this->vl_used + (float) $this->sl_used + (float) $this->service_used, 3
            );
        }

        return round((float) $this->vl_used + (float) $this->sl_used, 2);
    }
}
