<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesList;
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
    use PaginatesList;

    public function __construct(private LoanService $loans) {}

    /**
     * Daftar transaksi + filter. Transaksi aktif diurutkan tempo terdekat, selainnya tanggal pinjam terbaru.
     */
    public function index(IndexLoanRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Loan::query()->with($this->relations())->filter($filters);

        $loans = $this->paginateList(
            $query, $filters, IndexLoanRequest::SORTABLE, fn ($query) => $query->listOrder(), IndexLoanRequest::DEFAULT_PER_PAGE,
        );

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
