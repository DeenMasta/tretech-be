<?php

namespace App\Services\MasterData;

use App\Exceptions\BusinessLogicException;
use App\Models\InstrumentSet;
use App\Models\SetInstrument;
use Illuminate\Database\Eloquent\Collection;

class SetInstrumentService
{
    /**
     * @return Collection<int, SetInstrument>
     */
    public function listBySet(InstrumentSet $instrumentSet): Collection
    {
        return $instrumentSet->setInstruments()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(InstrumentSet $instrumentSet, array $data): SetInstrument
    {
        $code = $this->normalizeCode($data['code'] ?? null);

        if ($code !== null) {
            $exists = SetInstrument::query()
                ->where('instrument_set_id', $instrumentSet->id)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                throw new BusinessLogicException("An instrument with code {$code} already exists in this set.");
            }
        }

        return SetInstrument::query()->create([
            'instrument_set_id' => $instrumentSet->id,
            'code' => $code,
            'name' => trim((string) $data['name']),
            'quantity' => (int) ($data['quantity'] ?? 1),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'remarks' => $data['remarks'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(InstrumentSet $instrumentSet, SetInstrument $instrument, array $data): SetInstrument
    {
        $this->ensureBelongsToSet($instrumentSet, $instrument);

        if (array_key_exists('code', $data)) {
            $code = $this->normalizeCode($data['code']);
            $data['code'] = $code;

            if ($code !== null) {
                $clash = SetInstrument::query()
                    ->where('instrument_set_id', $instrumentSet->id)
                    ->where('code', $code)
                    ->where('id', '!=', $instrument->id)
                    ->exists();

                if ($clash) {
                    throw new BusinessLogicException("Another instrument with code {$code} already exists in this set.");
                }
            }
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
        }

        $instrument->fill($data)->save();

        return $instrument->refresh();
    }

    public function delete(InstrumentSet $instrumentSet, SetInstrument $instrument): void
    {
        $this->ensureBelongsToSet($instrumentSet, $instrument);
        $instrument->delete();
    }

    private function ensureBelongsToSet(InstrumentSet $instrumentSet, SetInstrument $instrument): void
    {
        if ($instrument->instrument_set_id !== $instrumentSet->id) {
            throw new BusinessLogicException('Set instrument does not belong to the provided set.');
        }
    }

    private function normalizeCode(mixed $value): ?string
    {
        $code = trim((string) ($value ?? ''));

        return $code === '' ? null : $code;
    }
}
