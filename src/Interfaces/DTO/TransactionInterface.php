<?php

namespace viktorprogger\Accounting\Interfaces\DTO;

interface TransactionInterface
{
    public function setStateNew(): void;

    public function setStateSuccess(): void;

    public function setStateFail(): void;

    public function setInvoiceStateFrom($state): void;

    public function setInvoiceStateTo($state): void;
}
