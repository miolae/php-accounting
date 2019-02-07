<?php

namespace miolae\Accounting;

use miolae\Accounting\Exceptions\WrongStateException;
use miolae\Accounting\Interfaces\Models\AccountInterface;
use miolae\Accounting\Interfaces\Models\InvoiceInterface;
use miolae\Accounting\Interfaces\ModuleInterface;
use miolae\Accounting\Interfaces\ServiceContainerInterface;
use miolae\Accounting\Interfaces\Services\InvoiceInterface as InvoiceService;

class Module implements ModuleInterface
{
    /** @var ServiceContainerInterface */
    protected $container;

    public function __construct(ServiceContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param AccountInterface $accountFrom
     * @param AccountInterface $accountTo
     * @param float            $amount
     *
     * @return InvoiceService
     */
    public function createInvoice(AccountInterface $accountFrom, AccountInterface $accountTo, float $amount): InvoiceService
    {
        $invoiceService = $this->container->getInvoiceService();
        $invoiceService->createNewInvoice($accountFrom, $accountTo, $amount);
        $invoiceService->saveModel();

        return $invoiceService;
    }

    /**
     * @param InvoiceInterface $invoice
     *
     * @return InvoiceService
     */
    public function hold(InvoiceInterface $invoice): InvoiceService
    {
        $invoiceService = $this->container->getInvoiceService($invoice);
        if (!$invoiceService->isStateCreated()) {
            throw new WrongStateException('Invoice can\'t be held because its state is not "created"');
        }

        $this->container->getDB()->beginTransaction();

        $transactionService = $this->container->getTransactionService();
        $transactionService->createNewTransaction($invoice);
        $transactionService->setTypeHold();
        $transactionService->saveModel();

        $accountFrom = $invoiceService->getAccountFrom();
        $accountFromService = $this->container->getAccountService($accountFrom);
        $accountFromService->hold($invoiceService->getAmount());
        $accountFromService->saveModel();

        $invoiceService->setStateHold();
        $invoiceService->saveModel();

        $transactionService->setStateSuccess();
        $transactionService->saveModel();

        $this->container->getDB()->commit();

        return $invoiceService;
    }

    public function finish(InvoiceInterface $invoice): InvoiceService
    {
        $invoiceService = $this->container->getInvoiceService($invoice);
        if (!$invoiceService->isStateHold()) {
            throw new WrongStateException('Invoice can\'t be held because its state is not "hold"');
        }

        $this->container->getDB()->beginTransaction();

        $transactionService = $this->container->getTransactionService();
        $transactionService->createNewTransaction($invoice);
        $transactionService->setTypeFinish();
        $transactionService->saveModel();

        $amount = $invoiceService->getAmount();

        $accountFrom = $invoiceService->getAccountFrom();
        $accountFromService = $this->container->getAccountService($accountFrom);
        $accountFromService->withdraw($amount);
        $accountFromService->saveModel();

        $accountTo = $invoiceService->getAccountTo();
        $accountToService = $this->container->getAccountService($accountTo);
        $accountToService->add($amount);
        $accountToService->saveModel();

        $invoiceService->setStateHold();
        $invoiceService->saveModel();

        $transactionService->setStateSuccess();
        $transactionService->saveModel();

        $this->container->getDB()->commit();

        return $invoiceService;
    }
}
