@props([
    // False when another form on the page was sent: its old input and errors are not ours.
    'submitted' => true,
])
{{-- U13, C-6: the fields of a new host account, shared by the Add host dialog and its full page. --}}
<x-field name="name" :placeholder="__('examples.person_name')" :label="__('accounts.name')" :submitted="$submitted" autocomplete="off" required maxlength="100" />
<x-field name="username" :placeholder="__('examples.username')" :label="__('accounts.username')" :submitted="$submitted" :hint="__('accounts.username_hint')" autocomplete="off" autocapitalize="none" spellcheck="false" required maxlength="30"
    pattern="[a-z0-9._\-]{3,30}" :data-pattern-message="__('accounts.username_rule')" />
<x-field name="email" :placeholder="__('examples.email')" type="email" :label="__('accounts.email')" :submitted="$submitted" autocomplete="off" required />
<x-field name="password" :placeholder="__('examples.new_password')" type="password" :label="__('accounts.password')" :submitted="$submitted" :hint="__('accounts.password_hint')" autocomplete="new-password" required minlength="12" pattern="(?=.*[A-Za-z])(?=.*[0-9]).*" :data-pattern-message="__('accounts.password_hint')" />
<x-field name="password_confirmation" :placeholder="__('examples.password_again')" type="password" :label="__('accounts.password_confirmation')" :submitted="$submitted" autocomplete="new-password" required data-same="password" />
