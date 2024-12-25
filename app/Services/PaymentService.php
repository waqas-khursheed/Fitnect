<?php

namespace App\Services;

use App\Exceptions\CustomValidationException;
use App\Models\ProfileView;
use App\Models\User;
use App\Services\BaseService;
use Stripe\StripeClient;

class PaymentService extends BaseService
{

    private $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(env('STRIPE_SECRET_KEY'));
    }

    public function createCustomer($id)
    {
        $customer = $this->stripe->customers->create([
            'name' => $id,
        ]);
        return $customer;
    }

    public function createExpressAccount()
    {
        try {
            $express_account = $this->stripe->accounts->create([
                'type' => 'express',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            return $express_account;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function accountLink($account_id)
    {
        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account' => $account_id,
                'refresh_url' => url('home'),
                'return_url' => url('thankyou'),
                'type' => 'account_onboarding'
            ]);

            return $accountLink;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function accountsRetrieve($account_id)
    {
        try {
            $accountLink = $this->stripe->accounts->retrieve($account_id);
            return $accountLink;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function assignCardToCustomer($customerId, $cardTokenId)
    {
        try {
            $card = $this->stripe->customers->createSource(
                $customerId,
                ['source' => $cardTokenId]
            );

            return $card;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function getAllCards($customerId)
    {
        try {
            $cards = $this->stripe->customers->allSources(
                $customerId
            );
            return $cards;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function setCardToDefault($customerId, $cardId)
    {
        try {
            $default = $this->stripe->customers->update(
                $customerId,
                ['default_source' => $cardId]
            );
            return $default;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function deleteCard($customerId, $cardId)
    {
        try {
            $deleteCard = $this->stripe->customers->deleteSource(
                $customerId,
                $cardId,
                []
            );
            return $deleteCard;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function chargeAmount($cardId, $customerId, $amount, $description)
    {
        try {
            $charge = $this->stripe->charges->create([
                'amount'        => round(($amount) * 100),
                'currency'      => 'USD',
                'customer'      => $customerId,
                'source'        => $cardId,
                'description'   => $description
            ]);
            return $charge;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }
    public function transfers($fee, $account_id, $description)
    {
        try {
            $trasnfer = $this->stripe->transfers->create([
                'amount'         =>  $fee,
                'currency'       =>  'usd',
                'destination'    =>  $account_id,
                'transfer_group' =>  'ORDER_95',
                'description'    =>  $description
            ]);

            return $trasnfer;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }

    public function refund($chargeId)
    {
        try {
            $refund = $this->stripe->refunds->create([
                'charge' => $chargeId,
            ]);
            return $refund;
        } catch (\Exception $e) {
            throw new CustomValidationException($e->getMessage());
        }
    }
}
