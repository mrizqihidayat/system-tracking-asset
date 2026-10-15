<?php

namespace App\Http\Controllers;

use App\Enums\DamageConfirmation;
use App\Enums\LoanStatus;
use App\Http\Requests\CancelLoanRequest;
use App\Http\Requests\ConfirmDamageRequest;
use App\Http\Requests\IndexLoanRequest;
use App\Http\Requests\ReturnLoanRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    public function __construct(private LoanService $loans) {}

    /**
     * Daftar transaksi + filter. Transaksi aktif diurutkan tempo terdekat, selainnya tanggal pinjam terbaru.
     */
    public function index(IndexLoanRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Loan::query()
            ->with($this->relations())
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('overdue'), fn ($query) => $query->overdue())
            ->when($request->boolean('due_today'), fn ($query) => $query->dueToday())
            ->when($request->boolean('pending_confirmation'), fn ($query) => $query->where('damage_confirmation', DamageConfirmation::MenungguKonfirmasi->value))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('borrow_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('borrow_date', '<=', $to))
            ->when($filters['asset_id'] ?? null, fn ($query, $assetId) => $query->where('asset_id', $assetId));

        $this->applySearch(
            $query,
            $filters['search'] ?? null,
            ['loans.borrower_name', 'loans.borrower_unit'],
            fn ($query, string $pattern) => $query->orWhereHas('asset', fn ($asset) => $asset->where(
                fn ($asset) => $asset->whereRaw($this->likeSql('assets.code'), [$pattern])->orWhereRaw($this->likeSql('assets.name'), [$pattern]),
            )),
        );

        $loans = $query
            ->orderByRaw('case when status = ? then 0 else 1 end', [LoanStatus::Dipinjam->value])
            ->orderByRaw('case when status = ? then due_date end asc', [LoanStatus::Dipinjam->value])
            ->orderByDesc('borrow_date')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? IndexLoanRequest::DEFAULT_PER_PAGE)
            ->withQueryString();

        return LoanResource::collection($loans);
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $loan = $this->loans->borrow($request->validated(), $request->user());

        return LoanResource::make($loan->load($this->relations()))->response()->setStatusCode(201);
    }

    public function show(Loan $loan): LoanResource
    {
        return LoanResource::make($loan->load($this->relations()));
    }

    /**
     * Terima kembali (result=kembali, default) atau hilang saat dipinjam (result=hilang).
     */
    public function giveBack(ReturnLoanRequest $request, Loan $loan): LoanResource
    {
        $data = $request->validated();

        $loan = $request->isLost()
            ? $this->loans->markLost($loan, $data['return_date'] ?? null, $data['note'], $request->user())
            : $this->loans->giveBack($loan, $data, $request->user());

        return LoanResource::make($loan->load($this->relations()));
    }

    public function cancel(CancelLoanRequest $request, Loan $loan): LoanResource
    {
        $loan = $this->loans->cancel($loan, $request->validated('cancel_reason'), $request->user());

        return LoanResource::make($loan->load($this->relations()));
    }

    public function confirmDamage(ConfirmDamageRequest $request, Loan $loan): LoanResource
    {
        $loan = $this->loans->confirmDamage(
            $loan,
            $request->validated('damage_confirmation_note'),
            $request->validated('damage_confirmation_document'),
            $request->user(),
        );

        return LoanResource::make($loan->load($this->relations()));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function relations(): array
    {
        return [
            'asset.room.building',
            'destinationRoom.building',
            'returnRoom.building',
            'borrowedBy',
            'receivedBy',
            'damageConfirmedBy',
        ];
    }
}
