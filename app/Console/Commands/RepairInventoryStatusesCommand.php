<?php

namespace App\Console\Commands;

use App\Models\Lot;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RepairInventoryStatusesCommand extends Command
{
    protected $signature = 'tretech:repair-inventory-statuses
                            {--fix : Apply the repairs. Without this option the command only previews them.}
                            {--product-id= : Limit the report or repair to one product ID.}';

    protected $description = 'Restore replenished lots to available status when they contain available quantity.';

    public function handle(): int
    {
        $productId = $this->validatedProductId();

        if ($productId === false) {
            return self::FAILURE;
        }

        $query = $this->affectedLotsQuery($productId);
        $affectedCount = (clone $query)->count();
        $affectedQuantity = (int) (clone $query)->sum('quantity_available');
        $preview = (clone $query)
            ->with('product:id,ref_num,product_name')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $this->table(
            ['Lot ID', 'Product', 'Lot number', 'Current status', 'Available quantity'],
            $preview->map(fn (Lot $lot): array => [
                $lot->id,
                $lot->product
                    ? "{$lot->product->ref_num} - {$lot->product->product_name}"
                    : (string) $lot->product_id,
                $lot->lot_number,
                $lot->status,
                $lot->quantity_available,
            ])->all(),
        );

        if ($affectedCount > $preview->count()) {
            $this->line(sprintf('Showing the first %d of %d affected lots.', $preview->count(), $affectedCount));
        }

        $this->info(sprintf(
            'Affected lots: %d; hidden available quantity: %d.',
            $affectedCount,
            $affectedQuantity,
        ));

        if ($affectedCount === 0) {
            $this->info('No inconsistent inventory lot statuses were found.');

            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->warn('Preview only: no records were changed. Re-run with --fix to apply the repairs.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(fn (): int => $this->affectedLotsQuery($productId)->update([
            'status' => 'available',
            'updated_at' => now(),
        ]));

        $this->info("Repair complete: {$updated} lot(s) changed to available.");

        return self::SUCCESS;
    }

    private function validatedProductId(): int|false|null
    {
        $value = $this->option('product-id');

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            $this->error('The --product-id option must be a positive integer.');

            return false;
        }

        return (int) $value;
    }

    private function affectedLotsQuery(?int $productId): Builder
    {
        return Lot::query()
            ->where('quantity_available', '>', 0)
            ->whereNotIn('status', ['available', 'holding'])
            ->when($productId !== null, fn (Builder $query) => $query->where('product_id', $productId));
    }
}
