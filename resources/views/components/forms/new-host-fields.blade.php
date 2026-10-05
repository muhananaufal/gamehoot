@props([
    // False when another form on the page was sent: its old input and errors are not ours.
    'submitted' => true,
])
{{-- U13, C-6: the fields of a new host account, shared by the Add host dialog and its full page. --}}
<x-field name="name" :label="__('accounts.name')" :submitted="$submitted" autocomplete="off" required maxlength="100" />
<x-field name="username" :label="__('accounts.username')" :submitted="$submitted" :hint="__('accounts.username_hint')" autocomplete="off" autocapitalize="none" spellcheck="false" required maxlength="30"
    pattern="[a-z0-9._\-]{3,30}" :data-pattern-message="__('accounts.username_rule')" />
<x-field name="email" type="email" :label="__('accounts.email')" :submitted="$submitted" autocomplete="off" required />
<x-field name="password" type="password" :label="__('accounts.password')" :submitted="$submitted" :hint="__('accounts.password_hint')" autocomplete="new-password" required />
<x-field name="password_confirmation" type="password" :label="__('accounts.password_confirmation')" :submitted="$submitted" autocomplete="new-password" required />
