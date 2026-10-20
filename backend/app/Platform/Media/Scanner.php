<?php

namespace App\Platform\Media;

interface Scanner
{
    /** Returns clean, infected or unavailable. Unavailable must never release bytes. */
    public function scan(string $path): string;
}
