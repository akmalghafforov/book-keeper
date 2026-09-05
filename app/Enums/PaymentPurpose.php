<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case OnBehalfOf = 'on_behalf_of';
    case VehicleAndLabor = 'vehicle_and_labor';
    case Vehicle = 'vehicle';
    case Labor = 'labor';

    public function label(?string $payerName = null): string
    {
        return match ($this) {
            self::OnBehalfOf => __('On behalf of :payerName', [
                'payerName' => $payerName === null || $payerName === '' ? ':payerName' : $payerName,
            ]),
            self::VehicleAndLabor => __('Money for vehicle and labor'),
            self::Vehicle => __('Money for vehicle'),
            self::Labor => __('Money for labor'),
        };
    }
}
