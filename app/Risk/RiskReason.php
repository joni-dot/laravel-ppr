<?php

namespace App\Risk;

/**
 * Why a package is at risk. The cases are in the order pages list them: the most serious kind first.
 * Pages get the value and put it into words themselves.
 */
enum RiskReason: string
{
    // Someone marked the package as high risk by hand, with a reason.
    case ManualFlag = 'manual_flag';
    // A milestone that hasn't happened was expected before today.
    case Overdue = 'overdue';
    // The float, counting sub-packages, is below the project's amber or red warning.
    case Late = 'late';
    // The need date isn't planned, so the float can't be judged.
    case NoNeedDate = 'no_need_date';
    case NoOwner = 'no_owner';
    case OwnerDeactivated = 'owner_deactivated';
    case NoBuyer = 'no_buyer';
    case BuyerDeactivated = 'buyer_deactivated';
}
