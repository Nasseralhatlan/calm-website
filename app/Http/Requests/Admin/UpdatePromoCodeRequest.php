<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/** Same rules as create — the unique check already ignores the edited code. */
class UpdatePromoCodeRequest extends StorePromoCodeRequest {}
