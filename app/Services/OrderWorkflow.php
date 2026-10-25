<?php

namespace App\Services;

use App\Enums\FundStatus;
use App\Enums\MediaType;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every order status change goes through here so the rules and the status log stay in one place.
 */
class OrderWorkflow
{
    public const REASON_FINAL_COST_REJECTED = 'Biaya final ditolak';

    public const REASON_CUSTOMER_CANCELLED = 'Dibatalkan pelanggan';

    public function __construct(private TechnicianSearch $search) {}

    /**
     * L-04: a customer sends a quote request to a technician.
     *
     * @param  array<string, mixed>  $details  Order columns from the request form.
     * @param  list<UploadedFile>  $media
     */
    public function submit(User $customer, TechnicianProfile $technician, array $details, array $media = []): Order
    {
        return DB::transaction(function () use ($customer, $technician, $details, $media) {
            $order = $customer->orders()->create([
                ...$details,
                'technician_id' => $technician->id,
                'warranty_fee' => ($details['warranty'] ?? false) ? (int) config('sejuk.warranty.fee') : 0,
                'status' => OrderStatus::MenungguRespons,
                'response_deadline' => $this->deadline(),
            ]);

            foreach ($media as $file) {
                $order->media()->create([
                    'path' => $file->store("order-media/{$order->id}", 'local'),
                    'type' => Str::startsWith((string) $file->getMimeType(), 'video/') ? MediaType::Video : MediaType::Foto,
                ]);
            }

            $order->offers()->create(['technician_id' => $technician->id, 'status' => OfferStatus::Menunggu]);
            $this->log($order, null, OrderStatus::MenungguRespons, $customer);

            return $order;
        });
    }

    /**
     * Re-send an order whose technician declined, timed out, or whose estimate was rejected,
     * without the customer filling in the form again.
     *
     * @param  array<string, mixed>  $details  Updated order columns, if the customer edited the form.
     */
    public function reassign(Order $order, TechnicianProfile $technician, User $actor, array $details = []): Order
    {
        $this->ensureStatus($order, [OrderStatus::CariCadangan]);

        if ($order->offers()->where('technician_id', $technician->id)->exists()) {
            throw new InvalidOrderTransition('Teknisi ini sudah pernah menerima pesanan ini.');
        }

        return DB::transaction(function () use ($order, $technician, $actor, $details) {
            $order->fill([
                ...$details,
                'technician_id' => $technician->id,
                'estimate_min' => null,
                'estimate_max' => null,
                'estimate_note' => null,
                'scheduled_date' => null,
                'scheduled_window' => null,
                'response_deadline' => $this->deadline(),
            ]);

            if (array_key_exists('warranty', $details)) {
                $order->warranty_fee = $details['warranty'] ? (int) config('sejuk.warranty.fee') : 0;
            }

            $order->offers()->create(['technician_id' => $technician->id, 'status' => OfferStatus::Menunggu]);
            $this->transition($order, OrderStatus::MenungguRespons, $actor, 'Dikirim ke '.$technician->business_name);

            return $order;
        });
    }

    /**
     * The assigned technician declines, the response window lapses, or the customer stops waiting.
     */
    public function releaseToBackup(Order $order, OfferStatus $offerOutcome, ?User $actor, string $note): void
    {
        $this->ensureStatus($order, [OrderStatus::MenungguRespons]);

        DB::transaction(function () use ($order, $offerOutcome, $actor, $note) {
            $order->offers()
                ->where('technician_id', $order->technician_id)
                ->where('status', OfferStatus::Menunggu)
                ->update(['status' => $offerOutcome, 'responded_at' => now()]);

            $order->response_deadline = null;
            $this->transition($order, OrderStatus::CariCadangan, $actor, $note);
        });
    }

    /**
     * The technician answers with an estimate and a visit slot.
     */
    public function submitEstimate(Order $order, TechnicianProfile $technician, int $minimum, int $maximum, CarbonInterface $date, TimeWindow $window, ?string $note): void
    {
        $this->ensureStatus($order, [OrderStatus::MenungguRespons]);
        $this->ensureAssigned($order, $technician);

        DB::transaction(function () use ($order, $technician, $minimum, $maximum, $date, $window, $note) {
            $offer = $order->offers()
                ->where('technician_id', $technician->id)
                ->where('status', OfferStatus::Menunggu)
                ->firstOrFail();

            $offer->update(['status' => OfferStatus::Diterima, 'responded_at' => now()]);
            $this->recordResponseTime($technician, (int) round($offer->created_at?->diffInMinutes(now()) ?? 0));

            $order->fill([
                'estimate_min' => $minimum,
                'estimate_max' => $maximum,
                'estimate_note' => $note,
                'scheduled_date' => $date->toDateString(),
                'scheduled_window' => $window,
                'response_deadline' => null,
            ]);

            $this->transition($order, OrderStatus::EstimasiMasuk, $technician->user);
        });
    }

    public function approveEstimate(Order $order, User $customer): void
    {
        $this->ensureStatus($order, [OrderStatus::EstimasiMasuk]);

        $this->transition($order, OrderStatus::Terjadwal, $customer);
    }

    /**
     * L-05C: the customer turns the estimate down and looks for someone else. The form stays saved.
     */
    public function rejectEstimate(Order $order, User $customer): void
    {
        $this->ensureStatus($order, [OrderStatus::EstimasiMasuk]);

        DB::transaction(function () use ($order, $customer) {
            $order->offers()
                ->where('technician_id', $order->technician_id)
                ->where('status', OfferStatus::Diterima)
                ->update(['status' => OfferStatus::Ditolak]);

            $order->fill(['estimate_min' => null, 'estimate_max' => null, 'estimate_note' => null, 'scheduled_date' => null, 'scheduled_window' => null]);
            $this->transition($order, OrderStatus::CariCadangan, $customer, 'Estimasi ditolak pelanggan');
        });
    }

    /**
     * L-07 (technician side): after inspecting the AC the technician sends the final cost lines.
     * The warranty fee the customer chose is added automatically. Sending again while the
     * customer has not decided replaces the previous lines.
     *
     * @param  list<array{label: string, amount: int, is_additional: bool}>  $items
     */
    public function submitFinalCost(Order $order, TechnicianProfile $technician, array $items): void
    {
        $this->ensureStatus($order, [OrderStatus::Terjadwal, OrderStatus::MenungguPersetujuanBiaya]);
        $this->ensureAssigned($order, $technician);

        if ($items === []) {
            throw new InvalidOrderTransition('Isi minimal satu rincian biaya.');
        }

        DB::transaction(function () use ($order, $technician, $items) {
            $order->costItems()->delete();
            $order->costItems()->createMany($items);

            if ($order->warranty) {
                $order->costItems()->create([
                    'label' => 'Garansi Servis '.config('sejuk.warranty.days').' Hari',
                    'amount' => $order->warranty_fee,
                    'is_additional' => false,
                ]);
            }

            $order->final_total = (int) $order->costItems()->sum('amount');
            $order->final_cost_submitted_at = now();

            if ($order->status === OrderStatus::MenungguPersetujuanBiaya) {
                $order->save();
                $this->log($order, OrderStatus::MenungguPersetujuanBiaya, OrderStatus::MenungguPersetujuanBiaya, $technician->user, 'Biaya final diperbarui');

                return;
            }

            $this->transition($order, OrderStatus::MenungguPersetujuanBiaya, $technician->user);
        });
    }

    public function approveFinalCost(Order $order, User $customer): void
    {
        $this->ensureStatus($order, [OrderStatus::MenungguPersetujuanBiaya]);

        $this->transition($order, OrderStatus::MenungguPembayaran, $customer);
    }

    /**
     * Payment confirmed by Midtrans: the money is held by Sejuk and the technician starts work.
     */
    public function markPaid(Order $order, ?string $note = null): void
    {
        $this->ensureStatus($order, [OrderStatus::MenungguPembayaran]);

        $order->fund_status = FundStatus::Ditahan;
        $this->transition($order, OrderStatus::Dikerjakan, null, $note ?? 'Pembayaran diterima, dana ditahan Sejuk');
    }

    /**
     * The customer declines the final cost: work does not start and the order closes.
     */
    public function rejectFinalCost(Order $order, User $customer): void
    {
        $this->ensureStatus($order, [OrderStatus::MenungguPersetujuanBiaya]);

        $order->cancelled_reason = self::REASON_FINAL_COST_REJECTED;
        $this->transition($order, OrderStatus::Dibatalkan, $customer, 'Biaya final ditolak pelanggan');
    }

    /**
     * Free cancellation by the customer before the technician sets off.
     */
    public function cancelByCustomer(Order $order, User $customer): void
    {
        if (! $order->status->isCancellableByCustomer()) {
            throw new InvalidOrderTransition('Pesanan ini sudah tidak bisa dibatalkan.');
        }

        DB::transaction(function () use ($order, $customer) {
            $order->offers()->where('status', OfferStatus::Menunggu)->update(['status' => OfferStatus::Kedaluwarsa, 'responded_at' => now()]);

            $order->fill(['cancelled_reason' => self::REASON_CUSTOMER_CANCELLED, 'response_deadline' => null]);
            $this->transition($order, OrderStatus::Dibatalkan, $customer);
        });
    }

    /**
     * Expire one request whose response window has passed, so pages stay correct between scheduler runs.
     */
    public function expireIfOverdue(Order $order): bool
    {
        if ($order->status !== OrderStatus::MenungguRespons || ! $order->response_deadline?->isPast()) {
            return false;
        }

        $this->releaseToBackup($order, OfferStatus::Kedaluwarsa, null, 'Teknisi tidak merespons dalam '.config('sejuk.response_minutes').' menit');

        return true;
    }

    /**
     * Expire every request whose response window has passed.
     */
    public function expireOverdueResponses(): int
    {
        $overdue = Order::query()
            ->where('status', OrderStatus::MenungguRespons)
            ->where('response_deadline', '<=', now())
            ->get();

        $overdue->each(fn (Order $order) => $this->expireIfOverdue($order));

        return $overdue->count();
    }

    /**
     * L-05B: up to three alternative technicians offering the same service, available today first, then nearest.
     *
     * @return Collection<int, TechnicianProfile>
     */
    public function backupCandidates(Order $order): Collection
    {
        $alreadyOffered = array_values(array_map('intval', $order->offers()->pluck('technician_id')->all()));

        return $this->search
            ->search(
                latitude: (float) ($order->latitude ?? config('sejuk.default_location.latitude')),
                longitude: (float) ($order->longitude ?? config('sejuk.default_location.longitude')),
                serviceId: $order->service_id,
                radiusKm: 15,
                excludeIds: $alreadyOffered,
            )
            ->filter(fn (TechnicianProfile $technician) => $technician->is_accepting_orders)
            ->sortBy(fn (TechnicianProfile $technician) => [$technician->isAvailableToday() ? 0 : 1, $technician->distanceKm])
            ->take((int) config('sejuk.backup_technicians'))
            ->values();
    }

    private function transition(Order $order, OrderStatus $to, ?User $actor, ?string $note = null): void
    {
        $from = $order->status;
        $order->status = $to;
        $order->save();

        $this->log($order, $from, $to, $actor, $note);
    }

    private function log(Order $order, ?OrderStatus $from, OrderStatus $to, ?User $actor, ?string $note = null): void
    {
        $order->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }

    /**
     * @param  list<OrderStatus>  $allowed
     */
    private function ensureStatus(Order $order, array $allowed): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw new InvalidOrderTransition('Status pesanan sudah berubah. Muat ulang halaman.');
        }
    }

    private function ensureAssigned(Order $order, TechnicianProfile $technician): void
    {
        if ($order->technician_id !== $technician->id) {
            throw new InvalidOrderTransition('Pesanan ini tidak lagi ditujukan kepadamu.');
        }
    }

    private function deadline(): CarbonInterface
    {
        return now()->addMinutes((int) config('sejuk.response_minutes'));
    }

    /**
     * Keep a rolling average of response times for the "Membalas ±N menit" label.
     */
    private function recordResponseTime(TechnicianProfile $technician, int $minutes): void
    {
        $previous = $technician->avg_response_minutes;

        $technician->update([
            'avg_response_minutes' => max(1, $previous === null ? $minutes : (int) round($previous * 0.8 + $minutes * 0.2)),
        ]);
    }
}
