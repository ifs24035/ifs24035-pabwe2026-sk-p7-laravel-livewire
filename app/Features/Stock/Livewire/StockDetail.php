<?php

namespace App\Features\Stock\Livewire;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class StockDetail extends Component
{
    use WithFileUploads;

    public Stock $stock;
    public string $name = '';
    public ?string $sku = null;
    public ?string $description = '';
    public int|string|null $quantity = 0;
    public string $unit = 'pcs';
    public int|float|string|null $price = null;
    public int|string|null $minStock = 5;
    public string $status = 'available';
    public mixed $coverPhoto = null;
    public bool $showEditModal = false;
    public bool $confirmingStockDeletion = false;
    public bool $confirmingCoverDeletion = false;

    public function mount(Stock $stock): void
    {
        Gate::authorize('view', $stock);

        $this->stock = $stock;
        $this->fillFormFromStock();
    }

    /**
     * Copy the current stock attributes into the editable form properties.
     */
    protected function fillFormFromStock(): void
    {
        $this->name = $this->stock->name;
        $this->sku = $this->stock->sku;
        $this->description = $this->stock->description ?? '';
        $this->quantity = $this->stock->quantity;
        $this->unit = $this->stock->unit;
        $this->price = $this->stock->price;
        $this->minStock = $this->stock->min_stock;
        $this->status = $this->stock->status;
    }

    /**
     * Keep the status field in sync while the user edits the quantity.
     */
    public function updatedQuantity(): void
    {
        $this->syncStatus();
    }

    /**
     * Keep the status field in sync while the user edits the minimum stock.
     */
    public function updatedMinStock(): void
    {
        $this->syncStatus();
    }

    /**
     * Derive the status from quantity vs min stock (only when both are valid numbers).
     */
    protected function syncStatus(): void
    {
        if (is_numeric($this->quantity) && is_numeric($this->minStock)) {
            $this->status = StockStatus::fromQuantity((int) $this->quantity, (int) $this->minStock)->value;
        }
    }

    public function openEditModal(): void
    {
        Gate::authorize('update', $this->stock);

        $this->resetErrorBag();
        $this->fillFormFromStock();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
    }

    public function updatedCoverPhoto(StockService $stockService): void
    {
        Gate::authorize('update', $this->stock);

        $this->validate([
            'coverPhoto' => ['required', 'image', 'max:5120'], // max 5MB
        ]);

        $stockService->updateCoverPhoto($this->stock, $this->coverPhoto);
        $this->stock->refresh();
        $this->reset('coverPhoto');

        session()->flash('success', 'Cover stok berhasil diperbarui.');
    }

    public function confirmDeleteCover(): void
    {
        $this->confirmingCoverDeletion = true;
    }

    public function cancelDeleteCover(): void
    {
        $this->confirmingCoverDeletion = false;
    }

    public function deleteCoverPhoto(StockService $stockService): void
    {
        Gate::authorize('update', $this->stock);

        $stockService->deleteCoverPhoto($this->stock);
        $this->stock->refresh();
        $this->confirmingCoverDeletion = false;

        session()->flash('success', 'Cover stok berhasil dihapus.');
    }

    public function saveStock(StockService $stockService): void
    {
        Gate::authorize('update', $this->stock);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'minStock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(StockStatus::values())],
        ]);

        $stockService->updateStock($this->stock, [
            'name' => $validated['name'],
            'sku' => $validated['sku'] !== '' ? $validated['sku'] : null,
            'description' => $validated['description'],
            'quantity' => (int) $validated['quantity'],
            'unit' => $validated['unit'],
            'price' => $validated['price'] !== '' ? $validated['price'] : null,
            'min_stock' => (int) $validated['minStock'],
            'status' => $validated['status'],
        ]);

        $this->stock->refresh();
        $this->fillFormFromStock();
        $this->closeEditModal();

        session()->flash('success', 'Informasi stok berhasil disimpan.');
    }

    /**
     * Increase (+) or decrease (-) the quantity; the status follows automatically.
     */
    public function adjustQuantity(int $delta, StockService $stockService): void
    {
        Gate::authorize('update', $this->stock);

        $stockService->adjustQuantity($this->stock, $delta);
        $this->stock->refresh();
        $this->fillFormFromStock();

        session()->flash('success', 'Quantity stok berhasil diperbarui.');
    }

    public function confirmDeleteStock(): void
    {
        $this->confirmingStockDeletion = true;
    }

    public function cancelDeleteStock(): void
    {
        $this->confirmingStockDeletion = false;
    }

    public function deleteStock(StockService $stockService): mixed
    {
        Gate::authorize('delete', $this->stock);

        $this->confirmingStockDeletion = false;
        $stockService->deleteStock($this->stock);

        session()->flash('success', 'Stok berhasil dihapus.');

        return redirect()->route('stocks.index');
    }

    public function render(): mixed
    {
        return view('livewire.stock.stock-detail', [
            'statuses' => StockStatus::cases(),
        ])->layout('components.layouts.app', ['title' => 'Detail Stok: ' . $this->stock->name]);
    }
}
