<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

/**
 * U19: a field with up-validate sends its form when it changes, with the header X-Up-Validate.
 * The rules run as usual (FormRequest, one set of rules), but a valid form only returns to its
 * page instead of being saved; an invalid one returns with its errors. Unpoly then replaces the
 * group of the changed field from that page.
 *
 * The page of the form names itself in X-Pentahoot-Return: a form in a modal sits on another
 * page than the one behind it. Only addresses of this site are followed.
 *
 * @mixin FormRequest
 */
trait LiveValidation
{
    protected function passedValidation(): void
    {
        if ($this->isLiveValidation()) {
            // Passwords are never sent back (U17, U19).
            throw new HttpResponseException(redirect()->to($this->getRedirectUrl())->withInput($this->except(['password', 'password_confirmation'])));
        }
    }

    protected function getRedirectUrl(): string
    {
        $return = $this->header('X-Pentahoot-Return');
        $site = rtrim(url('/'), '/').'/';

        if ($this->isLiveValidation() && is_string($return) && Str::startsWith($return, $site)) {
            return $return;
        }

        return parent::getRedirectUrl();
    }

    private function isLiveValidation(): bool
    {
        return $this->headers->has('X-Up-Validate');
    }
}
