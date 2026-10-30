<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdated;
use App\Services\OrderWorkflow;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the customer and the technician what changed on their order. Nobody is notified about
 * a change they made themselves.
 */
class SendOrderStatusNotifications
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing(['customer', 'technician.user', 'service']);

        foreach ($this->messagesFor($event, $order) as [$recipient, $title, $body]) {
            if ($recipient instanceof User && $recipient->isNot($event->actor)) {
                $recipient->notify(new OrderUpdated($order, $title, $body, $this->urlFor($recipient, $order)));
            }
        }

        if ($event->to === OrderStatus::Sengketa) {
            Notification::send(
                User::where('role', UserRole::Admin)->get(),
                new OrderUpdated($order, "Sengketa pesanan #{$order->code}", 'Pelanggan masih belum puas setelah perbaikan ulang. Mohon ditinjau.', route('admin.orders.show', $order)),
            );
        }
    }

    /**
     * @return list<array{0: User|null, 1: string, 2: string}>
     */
    private function messagesFor(OrderStatusChanged $event, Order $order): array
    {
        $customer = $order->customer;
        $technician = $order->technician?->user;
        $business = $order->technician->business_name ?? 'Teknisi';
        $service = $order->service->name;
        $slot = $order->scheduled_date?->translatedFormat('l, j F').', '.$order->scheduled_window?->range().' WIB';

        return match ($event->to) {
            OrderStatus::MenungguRespons => [
                [$technician, 'Pesanan baru masuk', "{$service} · {$order->brandLabel()} {$order->ac_capacity->label()} di {$order->address}. Tanggapi dalam ".config('sejuk.response_minutes').' menit.'],
            ],
            OrderStatus::CariCadangan => $event->from === OrderStatus::EstimasiMasuk
                ? [[$technician, 'Estimasi ditolak pelanggan', "Pelanggan memilih teknisi lain untuk pesanan #{$order->code}."]]
                : [[$customer, 'Pilih teknisi cadangan', "{$business} belum bisa mengambil pesananmu. Pilih teknisi lain tanpa mengisi formulir ulang."]],
            OrderStatus::EstimasiMasuk => [
                [$customer, 'Estimasi sudah masuk', "{$business} mengirim estimasi ".rupiah($order->estimate_min).' – '.rupiah($order->estimate_max).'. Setujui untuk menjadwalkan kunjungan.'],
            ],
            OrderStatus::Terjadwal => [
                [$technician, 'Estimasi disetujui', "Kunjungan ke {$customer->name} terjadwal {$slot}."],
            ],
            OrderStatus::MenungguPersetujuanBiaya => [
                [$customer, 'Biaya final perlu persetujuanmu', 'Total '.rupiah($order->final_total).'. Pekerjaan baru dimulai setelah kamu menyetujui dan membayar.'],
            ],
            OrderStatus::MenungguPembayaran => [
                [$technician, 'Biaya final disetujui', 'Pelanggan menyetujui '.rupiah($order->final_total).' dan sedang membayar.'],
            ],
            OrderStatus::Dikerjakan => [
                [$customer, 'Pembayaran diterima', 'Dana ditahan Sejuk sampai kamu mengonfirmasi pekerjaan selesai.'],
                [$technician, 'Pembayaran diterima, mulai kerjakan', "Pelanggan sudah membayar pesanan #{$order->code}. Unggah bukti kerja setelah selesai."],
            ],
            OrderStatus::MenungguKonfirmasi => [
                [$customer, 'Cek hasil pekerjaan', "{$business} menandai pekerjaan selesai. Periksa bukti kerja; tanpa konfirmasi, pesanan selesai otomatis dalam ".config('sejuk.auto_complete_hours').' jam.'],
            ],
            OrderStatus::PerbaikanUlang => [
                [$technician, 'Pelanggan meminta perbaikan ulang', "AC pada pesanan #{$order->code} masih bermasalah. Perbaiki tanpa biaya tambahan lalu unggah bukti baru."],
            ],
            OrderStatus::Sengketa => [
                [$customer, 'Pesanan ditinjau tim Sejuk', 'Tim Sejuk akan menghubungimu untuk mencari penyelesaian. Dana tetap ditahan.'],
                [$technician, 'Pesanan ditinjau tim Sejuk', "Pesanan #{$order->code} masuk peninjauan setelah beberapa kali perbaikan ulang."],
            ],
            OrderStatus::Selesai => [
                [$technician, 'Pesanan selesai, dana diteruskan', rupiah($order->technicianPayout())." masuk ke saldo pencairanmu untuk pesanan #{$order->code}."],
                [$event->actor === null || $event->actor->isAdmin() ? $customer : null, 'Pesanan selesai', "Pesanan #{$order->code} ditutup dan dana diteruskan ke {$business}. Jangan lupa beri ulasan."],
            ],
            OrderStatus::Dibatalkan => [
                [$event->from !== OrderStatus::CariCadangan ? $technician : null, 'Pesanan dibatalkan', $order->cancelled_reason === OrderWorkflow::REASON_FINAL_COST_REJECTED
                    ? "Pelanggan menolak biaya final pesanan #{$order->code}. Pekerjaan tidak dimulai."
                    : "Pesanan #{$order->code} dibatalkan."],
                [$customer, 'Pesanan dibatalkan', (string) $order->cancelled_reason],
            ],
        };
    }

    private function urlFor(User $recipient, Order $order): string
    {
        return $recipient->isTechnician() ? route('mitra.orders.show', $order) : route('orders.show', $order);
    }
}
