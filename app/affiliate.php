<?php

function app_affiliate_deposit_commission(float $depositAmount): float
{
    if (!is_finite($depositAmount) || $depositAmount <= 0) return 0.0;
    return round($depositAmount * 0.50, 2, PHP_ROUND_HALF_UP);
}
